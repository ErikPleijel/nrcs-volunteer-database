<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Where to redirect users after login.
     */
    protected $redirectTo = '/';

    public function __construct()
    {
        //
    }

    /**
     * Show the application's login form.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle a login request to the application.
     */
    public function login(Request $request)
    {
        // Accept either email OR phone number
        $request->validate([
            'login'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginRaw = trim((string) $request->input('login'));
        $password = (string) $request->input('password');
        $remember = $request->filled('remember');

        $loginType = $this->determineLoginType($loginRaw);
        if ($loginType === 'invalid') {
            throw ValidationException::withMessages([
                'login' => ['Please enter a valid email address or phone number.'],
            ]);
        }

        if ($loginType === 'phone') {
            $digitsOnly = preg_replace('/\D/', '', $loginRaw);

            // Normalised LIKE match handles format variation:
            // 08012345678, +2348012345678, 2348012345678 all resolve to the same suffix.
            $candidates = User::whereRaw(
                "REPLACE(REPLACE(REPLACE(telephone1, ' ', ''), '-', ''), '+', '') LIKE ?",
                ['%' . ltrim($digitsOnly, '0')]
            )->get();

            if ($candidates->count() === 0) {
                throw ValidationException::withMessages([
                    'login' => ['No account found with this phone number.'],
                ]);
            }

            if ($candidates->count() > 1) {
                throw ValidationException::withMessages([
                    'login' => ['Multiple accounts share this phone number. '
                              . 'Please log in with your email address instead.'],
                ]);
            }

            $candidate = $candidates->first();

            // Phone login is permitted ONLY for accounts WITHOUT an email.
            // Accounts that have an email must use it.
            if (! empty($candidate->email)) {
                throw ValidationException::withMessages([
                    'login' => ['This account has an email address. '
                              . 'Please log in with your email instead.'],
                ]);
            }

            if (Auth::attempt(['id' => $candidate->id, 'password' => $password], $remember)) {
                $loggedInUser = Auth::user();

                if ($loggedInUser->lifecycle_status === 'archived') {
                    $branchId      = $loggedInUser->branch_id;
                    $archivedDbRef = $loggedInUser->user_id_reference;
                    $archivedName  = $loggedInUser->full_name;
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    $request->session()->put([
                        'archived_db_ref'    => $archivedDbRef,
                        'archived_name'      => $archivedName,
                        'archived_branch_id' => $branchId,
                    ]);
                    return redirect()->route('archived-account.show', ['branch_id' => $branchId]);
                }

                $request->session()->regenerate();
                $this->touchLastLogin();
                return redirect()->intended($this->redirectTo);
            }

            if ($this->attemptLegacyLogin($candidate, $password, $remember, $request)) {
                return redirect()->intended($this->redirectTo);
            }

            throw ValidationException::withMessages([
                'login' => [trans('auth.failed')],
            ]);
        }

        // Email path
        $credentials = ['email' => $loginRaw, 'password' => $password];

        // 1) Normal auth attempt
        if (Auth::attempt($credentials, $remember)) {
            $loggedInUser = Auth::user();

            if ($loggedInUser->lifecycle_status === 'archived') {
                $branchId      = $loggedInUser->branch_id;
                $archivedDbRef = $loggedInUser->user_id_reference;
                $archivedName  = $loggedInUser->full_name;
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                // Stored in the session (not the URL) so the archived-account page can show the
                // user's reference and pre-fill a rejoin email without exposing/enumerating DB codes.
                $request->session()->put([
                    'archived_db_ref'    => $archivedDbRef,
                    'archived_name'      => $archivedName,
                    'archived_branch_id' => $branchId,
                ]);
                return redirect()->route('archived-account.show', ['branch_id' => $branchId]);
            }

            $request->session()->regenerate();
            $this->touchLastLogin();
            return redirect()->intended($this->redirectTo);
        }

        // 2) Legacy password fallback (md5 → bcrypt upgrade)
        $user = User::where('email', $loginRaw)->first();

        if ($this->attemptLegacyLogin($user, $password, $remember, $request)) {
            return redirect()->intended($this->redirectTo);
        }

        // Authentication failed
        throw ValidationException::withMessages([
            'login' => [trans('auth.failed')],
        ]);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Decide whether the login identifier is an email, a phone number, or invalid.
     */
    private function determineLoginType(string $login): string
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        // Normalise: strip spaces, dashes, leading zeros etc.
        // Accept formats: 08012345678, +2348012345678, 2348012345678
        $digits = preg_replace('/\D/', '', $login);
        if (strlen($digits) >= 7) {
            return 'phone';
        }

        return 'invalid';
    }

    /**
     * Attempt the legacy md5 → bcrypt upgrade path.
     * Returns true and logs the user in if the legacy hash matches.
     */
    private function attemptLegacyLogin(?User $user, string $password, bool $remember, Request $request): bool
    {
        if ($user && empty($user->password) && ! empty($user->legacy_password_hash)) {
            if (md5($password) === $user->legacy_password_hash) {
                $user->password = Hash::make($password);
                $user->legacy_password_hash = null;
                $user->save();

                Auth::login($user, $remember);
                $request->session()->regenerate();

                $this->touchLastLogin();

                return true;
            }
        }

        return false;
    }

    /**
     * Update last_login_at for the authenticated user.
     */
    private function touchLastLogin(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $user->last_login_at = now();
        $user->save();
    }
}
