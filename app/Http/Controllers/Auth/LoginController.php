<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DbNumber;
use App\Support\LoginThrottle;
use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Where to redirect users after login.
     *
     * '/profile' — the resolved path of route('profile.show') (routes/web.php:232).
     * Not called via route() here: PHP property defaults must be constant
     * expressions, and this controller doesn't use AuthenticatesUsers, so
     * there's no redirectTo() method hook feeding the
     * redirect()->intended($this->redirectTo) call sites — only the literal
     * property is read.
     */
    protected $redirectTo = '/profile';

    /**
     * Session key holding the phone-collision disambiguation state. The
     * current step and the remaining candidate IDs live server-side, so a
     * client can't skip a step or widen the candidate set.
     */
    private const PHONE_FLOW = 'phone_login';

    private const PHONE_FLOW_TTL_MINUTES = 15;

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
            $normalizedLogin = $this->normalizePhone($loginRaw);
            $throttleKey = LoginThrottle::phoneKey($normalizedLogin);
            $this->ensureNotLockedOut($throttleKey);

            // SQL suffix match is only a cheap pre-filter here: any row that
            // truly matches must end with $normalizedLogin once its own
            // spacing/dashes/plus are stripped, so this can only return a
            // superset. The exact decision is made below by comparing both
            // sides through the SAME bounded normalisation, so a number that
            // merely shares a digit suffix with another (e.g. '0123456789'
            // vs '080123456789') can no longer be mistaken for a match.
            $candidates = User::whereRaw(
                "REPLACE(REPLACE(REPLACE(telephone1, ' ', ''), '-', ''), '+', '') LIKE ?",
                ['%' . $normalizedLogin]
            )->get()->filter(
                fn (User $user) => $this->normalizePhone((string) $user->telephone1) === $normalizedLogin
            )->values();

            if ($candidates->count() === 0) {
                throw ValidationException::withMessages([
                    'login' => ['No account found with this phone number.'],
                ]);
            }

            // Phone login is permitted ONLY for accounts WITHOUT an email, so
            // only those can collide: an emailed account sharing the number
            // logs in by email and must not block someone else's phone login.
            $phoneOnly = $candidates->filter(fn (User $user) => empty($user->email))->values();

            // Archived duplicates (e.g. from users:archive-phone-duplicates)
            // don't block the live account. Only when every email-less match
            // is archived do they count, so a lone archived account still
            // reaches the archived-account page below.
            $live = $phoneOnly->reject(fn (User $user) => $user->lifecycle_status === 'archived')->values();
            if ($live->isNotEmpty()) {
                $phoneOnly = $live;
            }

            if ($phoneOnly->count() > 1) {
                // The password typed so far can't be checked against any one
                // account yet; it's discarded, not carried in the session.
                return $this->startPhoneFlow($request, $normalizedLogin, $phoneOnly, $remember);
            }

            if ($phoneOnly->isEmpty()) {
                throw ValidationException::withMessages([
                    'login' => ['This account has an email address. '
                              . 'Please log in with your email instead.'],
                ]);
            }

            return $this->attemptPasswordFor($phoneOnly->first(), $password, $remember, $request, $throttleKey)
                ?? $this->failLogin($request, $throttleKey);
        }

        // Email path
        $throttleKey = LoginThrottle::emailKey($loginRaw);
        $this->ensureNotLockedOut($throttleKey);

        $credentials = ['email' => $loginRaw, 'password' => $password];

        // 1) Normal auth attempt
        if (Auth::attempt($credentials, $remember)) {
            LoginThrottle::clear($throttleKey);

            return $this->completeLogin($request, Auth::user());
        }

        // 2) Legacy password fallback (md5 → bcrypt upgrade)
        $user = User::where('email', $loginRaw)->first();

        if ($legacyUser = $this->attemptLegacyLogin($user, $password, $remember, $request)) {
            LoginThrottle::clear($throttleKey);

            return $this->completeLegacyLogin($request, $legacyUser);
        }

        // Authentication failed
        return $this->failLogin($request, $throttleKey);
    }

    /**
     * Show the current step of the phone-collision disambiguation flow.
     */
    public function showPhoneStep(Request $request)
    {
        $flow = $this->phoneFlow($request);
        if (! $flow) {
            return $this->phoneFlowExpired($request);
        }

        $choices = $flow['step'] === 'pick'
            ? User::whereIn('id', $flow['candidates'])->orderBy('first_name')->orderBy('id')->get(['id', 'first_name'])
            : collect();

        return view('auth.phone-login', [
            'step'      => $flow['step'],
            'choices'   => $choices,
            'phoneTail' => substr($flow['phone'], -4),
        ]);
    }

    /**
     * Handle one step of the phone-collision disambiguation flow. The step
     * comes from the session, never from the request.
     */
    public function phoneStep(Request $request)
    {
        $flow = $this->phoneFlow($request);
        if (! $flow) {
            return $this->phoneFlowExpired($request);
        }

        $throttleKey = LoginThrottle::phoneKey($flow['phone']);
        if (LoginThrottle::tooManyAttempts($throttleKey)) {
            return $this->phoneFlowLockedOut($request, $throttleKey);
        }

        return match ($flow['step']) {
            'db_number'  => $this->handleDbNumberStep($request, $flow),
            'name'       => $this->handleNameStep($request, $flow),
            'birth_year' => $this->handleBirthYearStep($request, $flow),
            'pick'       => $this->handlePickStep($request, $flow),
            'password'   => $this->handlePasswordStep($request, $flow),
        };
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

    private function startPhoneFlow(Request $request, string $normalizedPhone, Collection $accounts, bool $remember)
    {
        $request->session()->put(self::PHONE_FLOW, [
            'phone'      => $normalizedPhone,
            'candidates' => $accounts->map(fn (User $user) => (int) $user->id)->all(),
            'step'       => 'db_number',
            'remember'   => $remember,
            'expires_at' => now()->addMinutes(self::PHONE_FLOW_TTL_MINUTES)->getTimestamp(),
        ]);

        return redirect()->route('login.phone');
    }

    /**
     * Step 1 (optional): a DB number, matched ONLY against the accounts that
     * share this phone number — never the wider user base. A number that
     * isn't one of them is a failure, not a silent fall-through to the name
     * step, so the step can't be used to probe which DB numbers exist.
     */
    private function handleDbNumberStep(Request $request, array $flow)
    {
        $raw = trim((string) $request->input('db_number'));

        if ($request->input('action') === 'skip' || $raw === '') {
            return $this->advancePhoneFlow($request, $flow, 'name');
        }

        $id = DbNumber::parse($raw);
        if ($id === null || ! in_array($id, $flow['candidates'], true)) {
            return $this->failPhoneFlowStep($request, $flow);
        }

        return $this->narrowPhoneFlow($request, $flow, [$id], 'password');
    }

    /**
     * Step 2: first AND last name, either order, exact case-insensitive
     * match (identity verification, so no fuzzy/soundex matching).
     */
    private function handleNameStep(Request $request, array $flow)
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
        ]);

        $given = [$this->normalizeName($request->input('first_name')), $this->normalizeName($request->input('last_name'))];
        sort($given);

        $matches = $this->phoneFlowCandidates($flow)->filter(function (User $user) use ($given) {
            $stored = [$this->normalizeName($user->first_name), $this->normalizeName($user->last_name)];
            sort($stored);

            return $stored === $given;
        });

        if ($matches->isEmpty()) {
            return $this->failPhoneFlowStep($request, $flow);
        }

        // Birth year only helps if every remaining account has one recorded
        // (else that person could never pass the step) and they actually
        // differ on it; otherwise go straight to the picker.
        $years = $matches->pluck('birth_year');
        $next = ! $years->contains(null) && $years->unique()->count() > 1 ? 'birth_year' : 'pick';

        return $this->narrowPhoneFlow($request, $flow, $matches->pluck('id')->all(), $next);
    }

    /**
     * Step 3: birth year, only reached when the name left 2+ accounts.
     */
    private function handleBirthYearStep(Request $request, array $flow)
    {
        $request->validate([
            'birth_year' => ['required', 'integer', 'digits:4'],
        ]);

        $year = (int) $request->input('birth_year');
        $matches = $this->phoneFlowCandidates($flow)->filter(fn (User $user) => (int) $user->birth_year === $year);

        if ($matches->isEmpty()) {
            return $this->failPhoneFlowStep($request, $flow);
        }

        return $this->narrowPhoneFlow($request, $flow, $matches->pluck('id')->all(), 'pick');
    }

    /**
     * Step 4: pick from the remaining accounts (shown as first name + DB number).
     */
    private function handlePickStep(Request $request, array $flow)
    {
        $request->validate([
            'account' => ['required', 'integer'],
        ], [
            'account.required' => 'Please choose your account.',
        ]);

        $id = (int) $request->input('account');
        if (! in_array($id, $flow['candidates'], true)) {
            return $this->failPhoneFlowStep($request, $flow);
        }

        return $this->narrowPhoneFlow($request, $flow, [$id], 'password');
    }

    /**
     * Final step: password for the single account the flow resolved to.
     */
    private function handlePasswordStep(Request $request, array $flow)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $candidate = User::find($flow['candidates'][0] ?? null);
        if (! $candidate) {
            return $this->phoneFlowExpired($request);
        }

        $throttleKey = LoginThrottle::phoneKey($flow['phone']);

        return $this->attemptPasswordFor($candidate, (string) $request->input('password'), (bool) $flow['remember'], $request, $throttleKey)
            ?? $this->failPhoneFlowStep($request, $flow);
    }

    /**
     * Keep the given candidates; one left means straight to the password step.
     */
    private function narrowPhoneFlow(Request $request, array $flow, array $ids, string $nextStepIfMany)
    {
        $ids = array_values(array_map('intval', $ids));

        $flow['candidates'] = $ids;

        return $this->advancePhoneFlow($request, $flow, count($ids) === 1 ? 'password' : $nextStepIfMany);
    }

    private function advancePhoneFlow(Request $request, array $flow, string $step)
    {
        $flow['step'] = $step;
        $request->session()->put(self::PHONE_FLOW, $flow);

        return redirect()->route('login.phone');
    }

    /**
     * A wrong answer at any step counts against the phone number's lockout
     * and gets the same generic message, so no step reveals which part was
     * wrong or whether a DB number/name exists elsewhere.
     */
    private function failPhoneFlowStep(Request $request, array $flow)
    {
        $throttleKey = LoginThrottle::phoneKey($flow['phone']);

        if (LoginThrottle::hit($throttleKey)) {
            event(new Lockout($request));

            return $this->phoneFlowLockedOut($request, $throttleKey);
        }

        return redirect()->route('login.phone')
            ->withErrors(['phone_login' => trans('auth.failed')]);
    }

    private function phoneFlowLockedOut(Request $request, string $throttleKey)
    {
        $request->session()->forget(self::PHONE_FLOW);

        return redirect()->route('login')
            ->withErrors(['login' => LoginThrottle::lockoutMessage($throttleKey)]);
    }

    private function phoneFlowExpired(Request $request)
    {
        $request->session()->forget(self::PHONE_FLOW);

        return redirect()->route('login')
            ->withErrors(['login' => 'Your sign-in took too long or was interrupted. Please enter your phone number again.']);
    }

    private function phoneFlow(Request $request): ?array
    {
        $flow = $request->session()->get(self::PHONE_FLOW);

        if (! is_array($flow) || ($flow['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return $flow;
    }

    private function phoneFlowCandidates(array $flow): Collection
    {
        return User::whereIn('id', $flow['candidates'])->get();
    }

    private function normalizeName(?string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $name)));
    }

    /**
     * Check $password against one already-identified account (normal hash,
     * then the legacy md5 fallback). Returns the post-login response, or
     * null on a wrong password — the caller decides how to report that.
     */
    private function attemptPasswordFor(User $candidate, string $password, bool $remember, Request $request, string $throttleKey)
    {
        if (Auth::attempt(['id' => $candidate->id, 'password' => $password], $remember)) {
            LoginThrottle::clear($throttleKey);

            return $this->completeLogin($request, Auth::user());
        }

        if ($legacyUser = $this->attemptLegacyLogin($candidate, $password, $remember, $request)) {
            LoginThrottle::clear($throttleKey);

            return $this->completeLegacyLogin($request, $legacyUser);
        }

        return null;
    }

    /**
     * Throw a lockout error on the login form if $throttleKey is locked.
     * Checked before the password, so even a correct one is refused while
     * locked.
     */
    private function ensureNotLockedOut(string $throttleKey): void
    {
        if (LoginThrottle::tooManyAttempts($throttleKey)) {
            throw ValidationException::withMessages([
                'login' => [LoginThrottle::lockoutMessage($throttleKey)],
            ]);
        }
    }

    /**
     * Count a wrong password against $throttleKey and report it on the login
     * form — as the lockout message if this failure was the one that locked it.
     */
    private function failLogin(Request $request, string $throttleKey)
    {
        if (LoginThrottle::hit($throttleKey)) {
            event(new Lockout($request));

            throw ValidationException::withMessages([
                'login' => [LoginThrottle::lockoutMessage($throttleKey)],
            ]);
        }

        throw ValidationException::withMessages([
            'login' => [trans('auth.failed')],
        ]);
    }

    /**
     * Post-login handling after a successful Auth::attempt().
     */
    private function completeLogin(Request $request, User $loggedInUser)
    {
        $request->session()->forget(self::PHONE_FLOW);

        if ($this->maintenanceGateBlocks($loggedInUser)) {
            return $this->redirectForMaintenanceGate($request);
        }

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

    /**
     * Post-login handling after the legacy md5 path (which has already
     * logged in and regenerated the session).
     */
    private function completeLegacyLogin(Request $request, User $legacyUser)
    {
        $request->session()->forget(self::PHONE_FLOW);

        if ($this->maintenanceGateBlocks($legacyUser)) {
            return $this->redirectForMaintenanceGate($request);
        }

        $this->touchLastLogin();
        return redirect()->intended($this->redirectTo);
    }

    /**
     * See PhoneNumber::normalize() — shared with users:report-phone-duplicates.
     */
    private function normalizePhone(string $raw): string
    {
        return PhoneNumber::normalize($raw);
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
     * Logs the user in and returns them if the legacy hash matches, so
     * callers can run post-login checks (e.g. the maintenance gate) before
     * deciding where to redirect — mirroring the Auth::attempt() branches,
     * which likewise defer touchLastLogin()/redirect until after those
     * checks pass.
     */
    private function attemptLegacyLogin(?User $user, string $password, bool $remember, Request $request): ?User
    {
        if ($user && empty($user->password) && ! empty($user->legacy_password_hash)) {
            if (md5($password) === $user->legacy_password_hash) {
                $user->password = Hash::make($password);
                $user->legacy_password_hash = null;
                $user->save();

                Auth::login($user, $remember);
                $request->session()->regenerate();

                return $user;
            }
        }

        return null;
    }

    /**
     * Maintenance login gate: when enabled via config, only allowlisted
     * user IDs may complete a login. Checked BEFORE the archived-user check
     * in every success path, so a blocked user always sees the generic
     * maintenance message rather than a status-specific one (e.g. archived)
     * that would leak account details during a maintenance window.
     */
    private function maintenanceGateBlocks(User $user): bool
    {
        if (! config('maintenance.login_gate_enabled')) {
            return false;
        }

        $allowedIds = array_map('intval', config('maintenance.allowed_user_ids', []));

        return ! in_array((int) $user->id, $allowedIds, true);
    }

    /**
     * Mirrors the archived-user block: log the user back out and invalidate
     * the session that Auth::attempt()/Auth::login() just created, so a
     * gate-blocked login never leaves an authenticated session behind.
     */
    private function redirectForMaintenanceGate(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('maintenance-gate.show');
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
