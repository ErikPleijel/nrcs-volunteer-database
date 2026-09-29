<?php

namespace App\Campaigns\Sending;

/**
 * The opt-out line appended to every campaign SMS. One definition for the send runner and
 * the previews/projections, so page counts shown before sending match what is sent.
 */
final class SmsFooter
{
    /** Length of the per-user token in the link (users.id_check_token, Str::random(32)). */
    public const TOKEN_LENGTH = 32;

    public static function for(string $token): string
    {
        return "\nTo stop: ".rtrim((string) config('app.url'), '/').'/u/'.$token.'/sms';
    }

    /** Same length as a real footer, with a placeholder token. */
    public static function preview(): string
    {
        return self::for(str_repeat('X', self::TOKEN_LENGTH));
    }
}
