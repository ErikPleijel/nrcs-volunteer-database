<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Traits\HandlesImageUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log; // Import the trait
use Illuminate\Support\Facades\Validator; // Ensure Log is imported for trait methods

class RegisterController extends Controller
{
    use HandlesImageUploads; // Use the trait

    /**
     * Show the registration form
     */
    public function showRegistrationForm()
    {
        $branches = Branch::orderBy('name')->get();
        $divisions = collect(); // Empty collection initially - will be loaded via AJAX
        $redCrossUnits = RedCrossUnit::all();

        return view('auth.register', compact('branches', 'divisions', 'redCrossUnits'));
    }

    /**
     * Get divisions by branch via AJAX
     */
    public function getDivisions(Request $request)
    {
        $branchId = $request->get('branch_id');

        if (! $branchId) {
            return response()->json([]);
        }

        $divisions = Division::where('branch_id', $branchId)
            ->orderBy('name')
            ->get(['id', 'name']); // Only select needed fields

        return response()->json($divisions);
    }

    /**
     * Handle registration request
     */
    public function register(Request $request)
    {
        $validator = $this->validator($request->all());

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user = $this->create($request->all());

        // CRITICAL: Log the user in immediately
        auth()->login($user);

        // Send custom verification email via SendGrid
        $this->sendCustomVerificationEmail($user);

        return redirect()->route('registration.success')
            ->with('user_name', $user->first_name)
            ->with('email', $user->email)
            ->with('user_id', $user->id);
    }

    /**
     * Send custom verification email via SendGrid
     */
    private function sendCustomVerificationEmail($user)
    {
        try {
            // Send the verification email using your existing notification
            $user->notify(new VerifyEmailNotification);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send verification email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get a validator for an incoming registration request
     */
    protected function validator(array $data)
    {
        $data['can_contribute_volunteering'] = ($data['contribution_type'] ?? null) === 'volunteering' ? true : null;
        $data['can_contribute_member'] = ($data['contribution_type'] ?? null) === 'member' ? true : null;

        return Validator::make($data, [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $existing = User::where('email', $value)->first();

                    if (! $existing) {
                        return;
                    }

                    if ($existing->lifecycle_status === 'archived') {
                        $fail(
                            'This email address is already registered. '.
                            'If you previously had an account with us, contact your '.
                            'branch administrator to reactivate it. '.
                            'Otherwise, please register with a different email address.'
                        );

                        return;
                    }

                    $fail('This email address is already registered.');
                },
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'national_id_number' => ['required', 'string', 'max:255'],
            'telephone1' => ['required', 'string', 'max:20'],
            'telephone2' => ['nullable', 'string', 'max:20'],
            'residential_address' => ['nullable', 'string', 'max:500'],
            'workplace_address' => ['nullable', 'string', 'max:500'],
            'organisation' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['required', 'exists:branches,id'],
            'division_id' => [
                'required',
                'exists:divisions,id',
                function ($attribute, $value, $fail) use ($data) {
                    if ($value && isset($data['branch_id'])) {
                        $division = Division::find($value);
                        if ($division && $division->branch_id != $data['branch_id']) {
                            $fail('The selected division does not belong to the selected branch.');
                        }
                    }
                },
            ],
            'disciplin' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'birth_year' => ['required', 'integer', 'min:1900', 'max:'.date('Y')],
            'gender' => ['required', 'in:male,female'],
            'marital_status' => ['nullable', 'in:single,married,other'],
            'personal_info' => ['nullable', 'string', 'max:1000'],
            'contribution_type' => ['required', 'in:volunteering,member'],
            'picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'captured_photo' => ['nullable', 'string'],
            'signature_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:1024'],
            'captured_signature' => ['nullable', 'string'],

            // NEW: CoC commitments
            'coc_commitment_1' => ['accepted'],
            'coc_commitment_2' => ['accepted'],
            'coc_commitment_3' => ['accepted'],
            'coc_commitment_4' => ['accepted'],
        ], [], [
            'contribution_type' => 'contribution type',
            'coc_commitment_1' => 'Code of Conduct confirmation 1',
            'coc_commitment_2' => 'Code of Conduct confirmation 2',
            'coc_commitment_3' => 'Code of Conduct confirmation 3',
            'coc_commitment_4' => 'NDPA data consent',
        ]);
    }

    /**
     * Create a new user instance after a valid registration
     */
    protected function create(array $data)
    {
        $userData = [
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'national_id_number' => $data['national_id_number'] ?? null,
            'telephone1' => $data['telephone1'],
            'telephone2' => $data['telephone2'] ?? null,
            'residential_address' => $data['residential_address'] ?? null,
            'workplace_address' => $data['workplace_address'] ?? null,
            'organisation' => $data['organisation'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'division_id' => $data['division_id'] ?? null,
            'disciplin' => $data['disciplin'],
            'occupation' => $data['occupation'] ?? null,
            'birth_year' => $data['birth_year'],
            'gender' => $data['gender'],
            'marital_status' => $data['marital_status'] ?? null,
            'personal_info' => $data['personal_info'] ?? null,
            'can_contribute_volunteering' => ($data['contribution_type'] ?? null) === 'volunteering',
            'can_contribute_member' => ($data['contribution_type'] ?? null) === 'member',

            // NEW: mark CoC as accepted
            'code_of_conduct_accepted_at' => now(),
        ];

        $request = request();

        $pictureFilename = $this->processPhotoUpload($request, 'profile', 'picture', 'captured_photo');
        if ($pictureFilename) {
            $userData['picture'] = $pictureFilename;
            $userData['image_upload_date'] = now();
        }

        $signatureFilename = $this->processPhotoUpload($request, 'signatures', 'signature_file', 'captured_signature');
        if ($signatureFilename) {
            $userData['signature'] = $signatureFilename;
        }

        $user = User::create($userData);

        $user->consent_obtained_at = now();
        $user->consent_obtained_by_id = $user->id;
        $user->consent_notes = 'self-registered via public form, NDPA checkbox accepted';
        $user->save();

        return $user;
    }
}
