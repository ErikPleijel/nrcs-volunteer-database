<?php

namespace App\Support;

use App\Models\User;

/**
 * Pick-list reasons for rejecting a photo or signature on the bulk ID card
 * page. The key is stored in users.{picture|signature}_rejection_reason; the
 * label is what admins and the member see. "other" requires a note.
 *
 * Route/UI types are 'photo' and 'signature'; the users column prefix for a
 * photo is 'picture' (the column that holds it).
 */
final class ImageRejection
{
    public const TYPES = ['photo', 'signature'];

    public const OTHER = 'other';

    public const NOTE_MAX = 500;

    public const REASONS = [
        'photo' => [
            'face_not_visible' => 'Face not clearly visible',
            'dark_or_blurry' => 'Too dark or blurry',
            'not_passport_style' => 'Not a passport-style photo',
            'wrong_person' => 'Wrong person',
            self::OTHER => 'Other',
        ],
        'signature' => [
            'not_readable' => 'Not readable',
            'not_a_signature' => 'Not a signature',
            'too_small_or_cut_off' => 'Too small or cut off',
            self::OTHER => 'Other',
        ],
    ];

    /** users column prefix for a type: 'picture' or 'signature'. */
    public static function column(string $type): string
    {
        return $type === 'photo' ? 'picture' : 'signature';
    }

    /** @return array<string, string> key => label */
    public static function reasons(string $type): array
    {
        return self::REASONS[$type] ?? [];
    }

    /**
     * Readable reason for a user's rejected image, e.g. "Too dark or blurry"
     * or "Other: wearing sunglasses". Null when the image is not rejected or
     * was rejected before reasons existed.
     */
    public static function describe(User $user, string $type): ?string
    {
        $column = self::column($type);

        if ($user->{$column.'_rejected_at'} === null) {
            return null;
        }

        $key = $user->{$column.'_rejection_reason'};
        $note = trim((string) $user->{$column.'_rejection_note'});
        $label = $key ? (self::REASONS[$type][$key] ?? null) : null;

        if ($key === self::OTHER) {
            return $note !== '' ? $note : null;
        }

        return match (true) {
            $label && $note !== '' => $label.' — '.$note,
            (bool) $label => $label,
            $note !== '' => $note,
            default => null,
        };
    }
}
