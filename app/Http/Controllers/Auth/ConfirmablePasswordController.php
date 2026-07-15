<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConfirmablePasswordController extends Controller
{
    /**
     * Show the password confirmation form.
     */
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    /**
     * Confirm the user's password, then continue to the intended (settings) URL.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        $password = (string) $request->input('password');

        if (! $user || ! $this->passwordMatches($user, $password)) {
            throw ValidationException::withMessages([
                'password' => [__('auth.password')],
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('admin.settings.index', absolute: false));
    }

    /**
     * Verify the supplied password against the user's current (or legacy) hash.
     * Mirrors LoginController's md5 → bcrypt fallback so migrated users can confirm.
     */
    private function passwordMatches(User $user, string $password): bool
    {
        if (! empty($user->password) && Hash::check($password, $user->password)) {
            return true;
        }

        if (empty($user->password) && ! empty($user->legacy_password_hash) && md5($password) === $user->legacy_password_hash) {
            // Upgrade legacy hash to Laravel hashing on successful confirmation.
            $user->password = Hash::make($password);
            $user->legacy_password_hash = null;
            $user->save();

            return true;
        }

        return false;
    }
}
