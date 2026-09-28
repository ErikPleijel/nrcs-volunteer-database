<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Per-identifier failed-login counter, layered on top of the per-IP
 * throttle on the login routes. Keyed by what was typed (lowercased
 * email, or the PhoneNumber-normalised phone), never by IP, so rotating
 * IPs doesn't reset it and one person's lockout never blocks another
 * identifier from the same IP. Thresholds: config('auth.login_throttle').
 */
class LoginThrottle
{
    public static function emailKey(string $email): string
    {
        return 'login-failures:email:'.mb_strtolower(trim($email));
    }

    public static function phoneKey(string $normalizedPhone): string
    {
        return 'login-failures:phone:'.$normalizedPhone;
    }

    public static function tooManyAttempts(string $key): bool
    {
        return RateLimiter::tooManyAttempts($key, self::maxAttempts());
    }

    /**
     * Record one failure. Returns true when this failure triggered the lockout.
     */
    public static function hit(string $key): bool
    {
        RateLimiter::hit($key, self::decayMinutes() * 60);

        return self::tooManyAttempts($key);
    }

    public static function clear(string $key): void
    {
        RateLimiter::clear($key);
    }

    public static function lockoutMessage(string $key): string
    {
        $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

        return 'Too many failed sign-in attempts for this email or phone number. '
            .'For your security, it is locked for '.$minutes.' more '.($minutes === 1 ? 'minute' : 'minutes')
            .'. Please try again after that.';
    }

    private static function maxAttempts(): int
    {
        return max(1, (int) config('auth.login_throttle.max_attempts', 5));
    }

    private static function decayMinutes(): int
    {
        return max(1, (int) config('auth.login_throttle.decay_minutes', 15));
    }
}
