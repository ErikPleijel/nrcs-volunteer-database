<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Donation;
use App\Models\Training;
use App\Models\Activity;
use App\Models\Branch;
use App\Models\Division;
use App\Traits\HandlesImageUploads;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // Ensure Auth facade is imported
use Illuminate\View\View; // Import the View class
use App\Models\CertificatePrint;
use App\Models\IdCardPrint;
use App\Models\Organisation;
use App\Models\Log as AuditLog;


class ProfileController extends Controller
{
    use HandlesImageUploads;

    public function show()
    {
        $userId = auth()->id();

        $user = User::with([
            'branch',
            'division',
            'redCrossUnit',
            'activeMembershipPayments.membershipFee',
            'activeMembershipPayments.organisation',
            'organisations',
        ])->find($userId);

        // Initialize variables
        $membershipPayments = collect();
        $currentMembership = null;
        $showingLimitMessage = false;
        $donations = collect();
        $donationsLimitMessage = false;
        $trainings = collect();
        $trainingsLimitMessage = false;
        $activities = collect();
        $activitiesLimitMessage = false;

        $certificatePrints = collect();
        $certificatePrintsLimitMessage = false;

        $idCardPrints = collect();
        $idCardPrintsLimitMessage = false;

        if ($user) {
            // Process Membership Payments. Organisation-attributed payments
            // (e.g. this user is the contact person on an org's payment)
            // stay visible here, labeled — this list is "payments I'm
            // involved in," not "my own membership." See $currentMembership
            // below, which is personal-only.
            $allPayments = $user->activeMembershipPayments()
                ->with(['membershipFee', 'organisation'])
                ->orderBy('payment_date', 'desc')
                ->get();

            // Check if we need to show the limit message
            $showingLimitMessage = $allPayments->count() >= 6;

            // Process all payments for display (organisation-linked included)
            $processedPayments = $allPayments->map(function ($payment) {
                return [
                    'payment_date' => $payment->payment_date->format('M d, Y'),
                    'membership_type' => $payment->membershipFee->name ?? 'N/A',
                    'amount' => $payment->membershipFee->amount ?? 0,
                    'formatted_amount' => '₦' . number_format($payment->membershipFee->amount ?? 0, 2),
                    'status' => $this->getPaymentStatus($payment),
                    'expiry_date' => $payment->expiry_date?->format('M d, Y'),
                    'is_valid' => $payment->isValid(),
                    'is_expired' => $payment->isExpired(),
                    'organisation_name' => $payment->organisation->name ?? null,
                ];
            });

            // Take ALL payments (including organisation-linked) for display
            $membershipPayments = $processedPayments;

            // Current membership STATUS badge is personal-only: an
            // organisation's payment isn't this person's own membership.
            $personalCurrentPayment = $user->activeMembershipPayments()
                ->personal()
                ->with('membershipFee')
                ->get()
                ->first(fn ($payment) => $payment->isValid());

            $currentMembership = $personalCurrentPayment ? [
                'membership_type' => $personalCurrentPayment->membershipFee->name ?? 'N/A',
                'formatted_amount' => '₦' . number_format($personalCurrentPayment->membershipFee->amount ?? 0, 2),
                'expiry_date' => $personalCurrentPayment->expiry_date?->format('M d, Y'),
                'expiring_soon' => $personalCurrentPayment?->expiresSoon(28) ?? false,
                'days_until_expiry' => $personalCurrentPayment?->days_until_expiry,
            ] : null;

            // Process Donations. Same rule as membership payments above:
            // organisation-linked donations stay visible, labeled.
            $allDonations = Donation::where('user_id', $userId)
                ->notDeleted()
                ->with('organisation')
                ->orderBy('date_donation', 'desc')
                ->get();

            // Check if we need to show the limit message for donations
            $donationsLimitMessage = $allDonations->count() >= 6;

            // Process donations for display - show ALL donations
            $donations = $allDonations->map(function ($donation) {
                return [
                    'date' => $donation->date_donation->format('M d, Y'),
                    'item' => $this->getDonationItem($donation),
                    'amount' => $this->getDonationAmount($donation),
                    'type' => $donation->in_kind_donation ? 'in-kind' : 'cash',
                    'purpose' => $donation->purpose ?? 'General',
                    'organisation_name' => $donation->organisation->name ?? null,
                ];
            });

            // Process Trainings
            $allTrainings = Training::where('user_id', $userId)
                ->active()
                ->with('trainingType')
                ->orderBy('training_date', 'desc')
                ->get();

            // Check if we need to show the limit message for trainings
            $trainingsLimitMessage = $allTrainings->count() >= 6;

            // Process trainings for display - show ALL trainings
            $trainings = $allTrainings->map(function ($training) {
                return [
                    'date' => $training->training_date->format('M d, Y'),
                    'activity' => $training->trainingType->name ?? 'Training Course',
                    'hours' => $this->getTrainingHours($training),
                    'status' => $this->getTrainingStatus($training),
                    'duration' => $training->duration ?? 0,
                ];
            });

            // Process Activities (Volunteering)
            $allActivities = Activity::where('user_id', $userId)
                ->active()
                ->with(['activityType', 'assignable'])
                ->orderBy('date', 'desc')
                ->get();

            // Check if we need to show the limit message for activities
            $activitiesLimitMessage = $allActivities->count() >= 6;

            // Process activities for display - show ALL activities
            $activities = $allActivities->map(function ($activity) {
                return [
                    'date' => $activity->date->format('M d, Y'),
                    'activity' => $activity->activityType->name ?? 'Volunteer Activity',
                    'hours' => $this->getActivityHours($activity),
                    'hours_numeric' => $activity->hours ?? 0,
                    'unit' => $this->getUnitName($activity),
                    'reference' => $activity->reference ?? null,
                ];
            });

            // Certificate prints
            $allCertificatePrints = CertificatePrint::query()
                ->where('user_id', $userId)
                ->with(['training.trainingType', 'printedBy'])
                ->orderByDesc('printed_at')
                ->get();

            $certificatePrintsLimitMessage = $allCertificatePrints->count() >= 6;

            $certificatePrints = $allCertificatePrints->map(function (CertificatePrint $print) {
                return [
                    'printed_at' => $print->printed_at?->format('M d, Y') ?? '—',
                    'certificate_type' => $print->certificate_type ?? '—',
                    'training' => $print->training?->trainingType?->name
                        ?? $print->training?->title
                            ?? '—',
                    'printed_by' => $print->printedBy?->full_name
                        ?? $print->printedBy?->email
                            ?? '—',
                    'notes' => $print->notes ?: null,
                ];
            });

            // ID card prints
            $allIdCardPrints = IdCardPrint::query()
                ->where('user_id', $userId)
                ->with('printedBy')
                ->orderByDesc('printed_at')
                ->get();

            $idCardPrintsLimitMessage = $allIdCardPrints->count() >= 6;

            $idCardPrints = $allIdCardPrints->map(function (IdCardPrint $print) {
                return [
                    'printed_at' => $print->printed_at?->format('M d, Y') ?? '—',
                    'status' => $print->status ?? '—',
                    'validity_months' => $print->validity_months ?? null,
                    'expiry_date' => $print->expiry_date?->format('M d, Y') ?? '—',
                    'printed_by' => $print->printedBy?->full_name
                        ?? $print->printedBy?->email
                            ?? '—',
                    'notes' => $print->notes ?: null,
                ];
            });
        }

        return view('profile.show', compact(
            'user',
            'membershipPayments',
            'currentMembership',
            'showingLimitMessage',
            'donations',
            'donationsLimitMessage',
            'trainings',
            'trainingsLimitMessage',
            'activities',
            'activitiesLimitMessage',
            'certificatePrints',
            'certificatePrintsLimitMessage',
            'idCardPrints',
            'idCardPrintsLimitMessage'
        ));
    }



    /**
     * Show the form for editing the user's profile.
     */
    public function edit()
    {
        $user = Auth::user()->load(['branch', 'division', 'redCrossUnit']); // Load redCrossUnit
        $branches = Branch::orderBy('name')->get();
        // Divisions will be loaded dynamically, but load all for initial state if no branch selected or for fallback
        $divisions = Division::orderBy('name')->get();

        // Pass redCrossUnit to the view
        return view('profile.edit', compact('user', 'branches', 'divisions'));
    }

    /**
     * Show the form for editing the authenticated user's profile photo.
     */
    public function editPhoto(): View
    {
        $user = Auth::user(); // Get the authenticated user
        return view('profile.edit-photo', compact('user'));
    }

    /**
     * Show the form for editing the authenticated user's signature.
     */
    public function editSignature(): View
    {
        $user = Auth::user(); // Get the authenticated user
        return view('profile.edit-signature', compact('user'));
    }


    /**
     * Update the user's profile information.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        try {
            $validator = Validator::make($request->all(), [
                // Personal information
                'first_name' => 'required|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'last_name' => 'required|string|max:255',
                'title' => 'nullable|in:Mr,Mrs,Ms,Miss,Prof.,Chief,Dr.,Hon.',
                'email' => [
                    'nullable',
                    'email',
                    'max:255',
                    Rule::unique('users')->ignore($user->id),
                ],
                'gender' => 'required|in:male,female',
                'birth_year' => 'required|integer|min:1900|max:' . date('Y'),
                'marital_status' => 'nullable|in:single,married,other',
                'national_id_number' => 'nullable|string|max:255',
                'organisation' => 'nullable|string|max:255',
                'occupation' => 'nullable|string|max:255',
                'disciplin' => 'nullable|string|max:255',
                'personal_info' => 'nullable|string|max:1000',

                // Contact information
                'telephone1' => 'required|string|max:20',
                'telephone2' => 'nullable|string|max:20',
                'residential_address' => 'nullable|string|max:500',
                'workplace_address' => 'nullable|string|max:500',

                // Affiliation
                'branch_id' => [
                    Rule::requiredIf(fn () => !$user->redCrossUnit),
                    Rule::exists('branches', 'id'),
                ],
                'division_id' => [
                    Rule::requiredIf(fn () => !$user->redCrossUnit),
                    Rule::exists('divisions', 'id'),
                ],

                // Contribution capabilities
                'contribution_type' => 'required|in:volunteering,member',

                // Authentication
                'password' => 'nullable|string|min:8|confirmed',
                'password_confirmation' => 'nullable|string|min:8',
            ]);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            // Prepare update data
            $updateData = [
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'title' => $request->title,
                'email' => $request->email,
                'gender' => $request->gender,
                'birth_year' => $request->birth_year,
                'marital_status' => $request->marital_status,
                'national_id_number' => $request->national_id_number,
                'organisation' => $request->organisation,
                'occupation' => $request->occupation,
                'disciplin' => $request->disciplin,
                'personal_info' => $request->personal_info,
                'telephone1' => $request->telephone1,
                'telephone2' => $request->telephone2,
                'residential_address' => $request->residential_address,
                'workplace_address' => $request->workplace_address,
                'can_contribute_volunteering' => $request->input('contribution_type') === 'volunteering',
                'can_contribute_member' => $request->input('contribution_type') === 'member',
            ];

            $scopedRoles = [
                'branch_secretary',
                'branch_db_administrator',
                'branch_db_assistant',
                'division_db_assistant_finance',
                'division_db_assistant_operations',
            ];
            $locationLocked = $user->redCrossUnit !== null
                || $user->hasAnyRole($scopedRoles);

            if (!$locationLocked) {
                $updateData['branch_id']   = $request->branch_id;
                $updateData['division_id'] = $request->division_id;
            } else {
                // Ignore submitted location — keep existing values
                $updateData['branch_id']   = $user->branch_id;
                $updateData['division_id'] = $user->division_id;
            }

            // Handle password update if provided
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            // If email changes, set email_verified_at to null
            if ($request->email !== $user->email) {
                $updateData['email_verified_at'] = null;
            }

            // Perform update
            $user->update($updateData);

            return redirect()->route('profile.show')
                ->with('success', 'Profile updated successfully!');

        } catch (\Exception $e) {
            Log::error('Profile update error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while updating your profile. Please try again.');
        }
    }


    /**
     * Update the user's profile picture.
     * This method is specifically for updating *only* the profile picture.
     */
    public function updateProfilePicture(Request $request)
    {
        $user = Auth::user();

        // Check if any new picture input was provided
        if (!$request->hasFile('picture') && !$request->filled('captured_photo')) {
            return back()->with('info', 'No new profile picture was provided.'); // Informational message
        }

        $validatedData = $request->validate([
            'picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'captured_photo' => ['nullable', 'string'], // For base64 captured photos
        ]);

        // Use the trait's unified photo processing method
        $pictureFilename = $this->processPhotoUpload($request, 'profile', 'picture', 'captured_photo');



        if ($pictureFilename) {
            // Delete old photo files if a new one is successfully uploaded/captured
            if ($user->picture) {
                $this->deleteUserImage($user->picture, 'profile');
            }

            $user->picture = $pictureFilename;

            // 🔥 Set/refresh image upload timestamp here
            $user->image_upload_date = now();

            $user->save();

            return back()->with('success', 'Profile picture updated successfully.');
        }

        // If input was provided but processPhotoUpload returned null, it indicates a failure during processing
        return back()->with('error', 'Failed to update profile picture due to a processing error. Please try again.');
    }


    /**
     * Update the user's signature.
     * This method is specifically for updating *only* the signature.
     */
    public function updateSignature(Request $request)
    {
        $user = Auth::user();

        // Check if any new signature input was provided
        if (!$request->hasFile('signature_file') && !$request->filled('captured_signature')) {
            return back()->with('info', 'No new signature was provided.'); // Informational message
        }

        $validatedData = $request->validate([
            'signature_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:1024'],
            'captured_signature' => ['nullable', 'string'], // For base64 captured signatures
        ]);

        // Use the trait's unified photo processing method for signatures
        // Pass 'signatures' as the category for storage and 'signature_file'/'captured_signature' as input names
        $signatureFilename = $this->processPhotoUpload($request, 'signatures', 'signature_file', 'captured_signature');

        if ($signatureFilename) {
            // Delete old signature files if a new one is successfully uploaded/captured
            if ($user->signature) {
                $this->deleteUserImage($user->signature, 'signatures');
            }
            $user->signature = $signatureFilename;
            $user->save();
            return back()->with('success', 'Signature updated successfully.');
        }

        // If input was provided but processPhotoUpload returned null, it indicates a failure during processing
        return back()->with('error', 'Failed to update signature due to a processing error. Please try again.');
    }

    /**
     * Fetch divisions based on the selected branch.
     *
     * @param  \App\Models\Branch  $branch
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDivisionsByBranch(Branch $branch)
    {
        return response()->json($branch->divisions()->orderBy('name')->get());
    }

    public function organisationProfile(Organisation $organisation)
    {
        $authUser = Auth::user();

        if (!$organisation->users()->where('user_id', $authUser->id)->exists()) {
            abort(403);
        }

        $organisation->load(['branch', 'users']);

        $allPayments = $organisation->membershipPayments()
            ->where('is_deleted', false)
            ->with('membershipFee')
            ->orderBy('payment_date', 'desc')
            ->get();

        $showingLimitMessage = $allPayments->count() >= 6;

        $processedPayments = $allPayments->map(function ($payment) {
            return [
                'payment_date'     => $payment->payment_date->format('M d, Y'),
                'membership_type'  => $payment->membershipFee->name ?? 'N/A',
                'formatted_amount' => '₦' . number_format($payment->membershipFee->amount ?? 0, 2),
                'status'           => $this->getPaymentStatus($payment),
                'expiry_date'      => $payment->expiry_date?->format('M d, Y'),
                'is_valid'         => $payment->isValid(),
            ];
        });

        $currentMembership = $processedPayments->firstWhere('is_valid', true);
        $membershipPayments = $processedPayments;

        $allDonations = $organisation->donations()
            ->orderBy('date_donation', 'desc')
            ->get();

        $donationsLimitMessage = $allDonations->count() >= 6;

        $donations = $allDonations->map(function ($donation) {
            return [
                'date'   => $donation->date_donation?->format('M d, Y') ?? '—',
                'item'   => $this->getDonationItem($donation),
                'amount' => $this->getDonationAmount($donation),
                'type'   => $donation->in_kind_donation ? 'in-kind' : 'cash',
            ];
        });

        $allCertificatePrints = $organisation->certificatePrints()
            ->with('printedBy')
            ->orderByDesc('printed_at')
            ->get();

        $certificatePrintsLimitMessage = $allCertificatePrints->count() >= 6;

        $typeLabels = [
            'organisation_membership' => 'Organisation – Membership',
            'organisation_donation'   => 'Organisation – Donation',
        ];

        $certificatePrints = $allCertificatePrints->map(function (CertificatePrint $print) use ($typeLabels) {
            return [
                'printed_at'       => $print->printed_at?->format('M d, Y') ?? '—',
                'certificate_type' => $typeLabels[$print->certificate_type] ?? ucwords(str_replace('_', ' ', $print->certificate_type)),
                'printed_by'       => $print->printedBy?->full_name ?? $print->printedBy?->email ?? '—',
            ];
        });

        return view('profile.organisation', compact(
            'organisation',
            'membershipPayments',
            'currentMembership',
            'showingLimitMessage',
            'donations',
            'donationsLimitMessage',
            'certificatePrints',
            'certificatePrintsLimitMessage'
        ));
    }

    public function updateCommunicationPreferences(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'email_opt_out' => 'nullable|boolean',
            'sms_opt_out'   => 'nullable|boolean',
        ]);

        $wasEmailOptOut = (bool) $user->email_opt_out;
        $wasSmsOptOut   = (bool) $user->sms_opt_out;
        // Checkbox is "Receive" (checked = subscribed), so invert to get opt-out value
        $newEmailOptOut = !$request->boolean('email_opt_out');
        $newSmsOptOut   = !$request->boolean('sms_opt_out');

        $user->email_opt_out = $newEmailOptOut;
        if (!$wasEmailOptOut && $newEmailOptOut) {
            $user->email_opt_out_at = now();
        } elseif ($wasEmailOptOut && !$newEmailOptOut) {
            $user->email_opt_out_at = null;
        }

        $user->sms_opt_out = $newSmsOptOut;
        if (!$wasSmsOptOut && $newSmsOptOut) {
            $user->sms_opt_out_at = now();
        } elseif ($wasSmsOptOut && !$newSmsOptOut) {
            $user->sms_opt_out_at = null;
        }

        $user->save();

        return redirect()->route('profile.show')->with('success', 'Communication preferences updated.');
    }

    /**
     * Self-service account archival.
     */
    public function selfArchive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'confirmation' => 'required|string',
        ]);

        if ($validator->fails() || strtolower(trim($request->input('confirmation'))) !== 'archive') {
            return redirect()->back()->withErrors(['confirmation' => "Please type 'archive' to confirm."]);
        }

        if (auth()->user()->getRoleNames()->isNotEmpty()) {
            return redirect()->back()->withErrors(['confirmation' => 'You hold an administrative role. Ask another administrator to remove it before archiving your own account.']);
        }

        $user = auth()->user();

        $user->lifecycle_status = 'archived';
        $user->save();

        AuditLog::write(
            'user_self_archived',
            $user,
            null,
            null,
            null,
            "{$user->full_name} (DB-{$user->id}) archived their own account."
        );

        $branchId = $user->branch_id;
        $archivedDbRef = $user->user_id_reference;
        $archivedName = $user->full_name;

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // invalidate() wipes all session data before this point, so the values are
        // set afterward (matching LoginController's archived-user interceptor).
        $request->session()->put([
            'archived_db_ref' => $archivedDbRef,
            'archived_name' => $archivedName,
            'archived_branch_id' => $branchId,
            'archived_self_initiated' => true,
        ]);

        return redirect()->route('archived-account.show', ['branch_id' => $branchId, 'self' => 1]);
    }

    // --- Original helper methods remain below ---
    /**
     * Get payment status with styling information
     */
    private function getPaymentStatus($payment)
    {
        if ($payment->isValid()) {
            return [
                'type' => 'valid',
                'text' => 'Valid until ' . $payment->expiry_date->format('M d, Y'),
                'class' => 'bg-green-100 text-green-800'
            ];
        } elseif ($payment->isExpired()) {
            return [
                'type' => 'expired',
                'text' => 'Archived payment',
                'class' => 'bg-red-100 text-red-800'
            ];
        } else {
            return [
                'type' => 'unknown',
                'text' => 'Unknown',
                'class' => 'bg-gray-100 text-gray-800'
            ];
        }
    }

    /**
     * Get donation item display text
     */
    private function getDonationItem($donation)
    {
        if ($donation->in_kind_donation) {
            return $donation->donation_item ?? 'In-kind donation';
        } else {
            return 'Cash donation';
        }
    }

    /**
     * Get donation amount display text
     */
    private function getDonationAmount($donation)
    {
        if ($donation->in_kind_donation) {
            return $donation->donation_estimated_value
                ? '₦' . number_format($donation->donation_estimated_value, 2) . ' (est.)'
                : 'No value estimated';
        } else {
            return $donation->amount
                ? '₦' . number_format($donation->amount, 2)
                : '₦0.00';
        }
    }

    /**
     * Get training hours display text
     */
    private function getTrainingHours($training)
    {
        if ($training->duration) {
            return $training->duration . ' hours';
        }
        return 'Duration not specified';
    }

    /**
     * Get training status
     */
    private function getTrainingStatus($training)
    {
        if ($training->valid_years && $training->training_date) {
            $expiryDate = $training->training_date->addYears($training->valid_years);
            if ($expiryDate->isFuture()) {
                return [
                    'type' => 'valid',
                    'text' => 'Valid until ' . $expiryDate->format('M d, Y'),
                    'class' => 'bg-green-100 text-green-800'
                ];
            } else {
                return [
                    'type' => 'expired',
                    'text' => 'Expired on ' . $expiryDate->format('M d, Y'),
                    'class' => 'bg-red-100 text-red-800'
                ];
            }
        }
        return [
            'type' => 'unknown',
            'text' => 'No expiry date',
            'class' => 'bg-gray-100 text-gray-800'
        ];
    }

    /**
     * Get activity hours display text
     */
    private function getActivityHours($activity)
    {
        if ($activity->hours) {
            return $activity->hours . ' hours';
        }
        return 'Duration not specified';
    }

    /**
     * Get Red Cross Unit name from activity
     */
    private function getUnitName($activity)
    {
        if ($activity->assignable) {
            return $activity->assignable->name ?? 'Unit not specified';
        }

        return 'Unit not specified';
    }
}
