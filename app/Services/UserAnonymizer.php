<?php

namespace App\Services;

use App\Models\Log as AuditLog;
use App\Models\User;
use App\Traits\HandlesImageUploads;
use DomainException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permanently anonymizes an archived account (NDPA; Decisions.md 2026-10-08).
 * Used by the scheduled users:anonymize-expired job and by the manual action
 * on users/edit.
 *
 * Kept: id, gender, branch/division/unit, contribution flags, lifecycle
 * status (archived), created_at/archived_at, activity dates, and the
 * membership/training/activity/payment rows (statistics and accounting).
 * birth_year is reduced to its decade. Everything else that identifies the
 * person is removed, including copies in campaign recipients, Paystack
 * payloads and the audit log. Photo and signature files are deleted after
 * the transaction commits.
 */
class UserAnonymizer
{
    use HandlesImageUploads;

    /** Keys removed (at any depth) from Paystack payloads and payment metadata. */
    public const PAYMENT_PERSONAL_KEYS = [
        'email', 'name', 'first_name', 'last_name', 'middle_name', 'full_name',
        'phone', 'phone_number', 'mobile', 'customer', 'ip_address',
        'account_name', 'custom_fields', 'last4', 'bin',
    ];

    /** Keys removed (at any depth) from audit-log old/new values whose subject is the user. */
    public const AUDIT_PERSONAL_KEYS = [
        'first_name', 'last_name', 'middle_name', 'full_name', 'name', 'title',
        'email', 'phone', 'telephone1', 'telephone2', 'user_code',
        'residential_address', 'workplace_address', 'address',
        'national_id_number', 'national_id_number_hash', 'personal_info',
        'birth_year', 'picture', 'signature', 'red_cross_id_number',
    ];

    public const BRANCH_CONTACT_SLOTS = 6;

    public function anonymize(User $user, ?User $actor, string $trigger): void
    {
        $user->refresh();
        $this->assertEligible($user);

        $id = $user->id;
        $oldEmail = $user->email;
        $picture = $user->getRawOriginal('picture');
        $signature = $user->getRawOriginal('signature');
        $names = $this->nameVariants($user);

        DB::transaction(function () use ($user, $actor, $trigger, $id, $oldEmail, $names) {
            // a) The users row. Query builder on purpose: the model's saving/
            //    updating hooks (NIN hash, sensitive-field audit) must not run.
            DB::table('users')->where('id', $id)->update([
                'first_name' => 'Anonymized',
                'last_name' => 'Member',
                'middle_name' => null,
                'title' => null,
                'email' => null,
                'email_verified_at' => null,
                'user_code' => null,
                'telephone1' => null,
                'telephone2' => null,
                'national_id_number' => null,
                'national_id_number_hash' => null,
                'red_cross_id_number' => null,
                'marital_status' => null,
                'disciplin' => null,
                'occupation' => null,
                'personal_info' => null,
                'organisation' => null,
                'organisation_id' => null,
                'residential_address' => null,
                'workplace_address' => null,
                'is_public_contact' => false,
                'public_contact_position' => null,
                'picture' => null,
                'is_picture_confirmed' => null,
                'image_upload_id' => null,
                'image_upload_date' => null,
                'signature' => null,
                'is_signature_confirmed' => null,
                'signature_rejected_at' => null,
                'signature_rejected_by_id' => null,
                'consent_notes' => null,
                'form_reg_id' => null,
                'legacy_role' => null,
                'id_check_token' => null, // old ID-card QR codes stop verifying
                'password' => '',
                'legacy_password_hash' => null,
                'remember_token' => null,
                'birth_year' => $user->birth_year === null ? null : intdiv((int) $user->birth_year, 10) * 10,
                'anonymized_at' => now(),
                'anonymized_by_id' => $actor?->id,
                'updated_at' => now(),
            ]);

            // b) Roles and direct permissions (role holders are refused above,
            //    but direct permissions may remain).
            $user->roles()->detach();
            $user->permissions()->detach();
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            // c) Pointers to the person.
            for ($i = 1; $i <= self::BRANCH_CONTACT_SLOTS; $i++) {
                DB::table('branches')->where("public_contact_user_id_{$i}", $id)->update([
                    "public_contact_user_id_{$i}" => null,
                    "public_contact_position_{$i}" => null,
                ]);
            }
            DB::table('red_cross_units')->where('team_leader_user_id', $id)->update(['team_leader_user_id' => null]);
            DB::table('red_cross_units')->where('assistant_team_leader_user_id', $id)->update(['assistant_team_leader_user_id' => null]);
            DB::table('task_forces')->where('team_leader_user_id', $id)->update(['team_leader_user_id' => null]);
            DB::table('task_forces')->where('assist_team_leader_user_id', $id)->update(['assist_team_leader_user_id' => null]);
            DB::table('organisation_user')->where('user_id', $id)->delete();

            // d) Sessions, password reset tokens, notifications.
            DB::table('sessions')->where('user_id', $id)->delete();
            if ($oldEmail) {
                DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
            }
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $id)
                ->delete();

            // e) Campaign recipient copies of the contact details.
            DB::table('messaging_recipients')
                ->where('recipient_type', User::class)
                ->where('recipient_id', $id)
                ->update(['email' => null, 'phone' => null, 'payload_json' => null]);

            // f) Payment payloads: personal keys out; amounts, references,
            //    status and dates stay.
            foreach (['membership_payments', 'donations'] as $table) {
                $this->scrubJsonColumns($table, $id, ['gateway_response'], self::PAYMENT_PERSONAL_KEYS);
            }
            $this->scrubJsonColumns('payment_transactions', $id, ['meta', 'raw_payload'], self::PAYMENT_PERSONAL_KEYS);
            DB::table('donations')->where('user_id', $id)->update(['submission_name' => null]);

            // g) Audit log.
            $this->scrubAuditLog($id, $names);

            // h) The anonymization itself — no personal data.
            AuditLog::write(
                'user_anonymized',
                $user,
                ['branch_id' => $user->branch_id, 'division_id' => $user->division_id],
                null,
                ['trigger' => $trigger],
                "DB-{$id} anonymized ({$trigger})"
            );
        });

        // After commit: files are not transactional. A missing file is fine.
        if ($picture) {
            $this->deleteUserImage(basename($picture), 'profile');
        }
        if ($signature) {
            $this->deleteUserImage(basename($signature), 'signatures');
        }
    }

    /** @throws DomainException when the account may not be anonymized */
    public function assertEligible(User $user): void
    {
        if ($user->lifecycle_status !== 'archived') {
            throw new DomainException("DB-{$user->id} is not archived; only archived accounts can be anonymized.");
        }
        if ($user->anonymized_at !== null) {
            throw new DomainException("DB-{$user->id} is already anonymized.");
        }
        if ($user->is_super_admin || $user->getRoleNames()->isNotEmpty()) {
            throw new DomainException("DB-{$user->id} holds an administrative role; remove it first.");
        }
    }

    /** The ways the person's name may appear in audit-log descriptions, longest first. */
    private function nameVariants(User $user): array
    {
        $variants = [
            $user->full_middle_name,
            trim(preg_replace('/\s+/', ' ', "{$user->first_name} {$user->middle_name} {$user->last_name}")),
            $user->full_name,
            trim(preg_replace('/\s+/', ' ', "{$user->first_name} {$user->last_name}")),
        ];

        $variants = array_values(array_unique(array_filter(
            array_map('trim', $variants),
            fn ($v) => mb_strlen($v) >= 3
        )));
        usort($variants, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return $variants;
    }

    private function scrubJsonColumns(string $table, int $userId, array $columns, array $keys): void
    {
        DB::table($table)
            ->where('user_id', $userId)
            ->where(function ($q) use ($columns) {
                foreach ($columns as $column) {
                    $q->orWhereNotNull($column);
                }
            })
            ->orderBy('id')
            ->select(array_merge(['id'], $columns))
            ->chunk(200, function ($rows) use ($table, $columns, $keys) {
                foreach ($rows as $row) {
                    $update = [];
                    foreach ($columns as $column) {
                        if ($row->$column !== null) {
                            $update[$column] = $this->scrubJson($row->$column, $keys);
                        }
                    }
                    if ($update) {
                        DB::table($table)->where('id', $row->id)->update($update);
                    }
                }
            });
    }

    private function scrubJson(string $json, array $keys): ?string
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            // Not structured data we can filter: drop it rather than keep unknown content.
            return null;
        }

        return json_encode(self::removeKeys($decoded, $keys));
    }

    /** Recursively remove $keys (case-insensitive) from an array. */
    public static function removeKeys(array $data, array $keys): array
    {
        $keys = array_map('strtolower', $keys);

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $keys, true)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = self::removeKeys($value, $keys);
            }
        }

        return $data;
    }

    /**
     * Rows ABOUT the user (subject): personal keys out of old/new values, and
     * the person's name replaced by DB-{id} in the description. Other rows:
     * only "{name} (DB-{id})" / "{name} DB-{id}" mentions are replaced, so a
     * different person with the same name is never touched. Rows where the
     * user was the ACTOR (logs.user_id) are left as they are.
     */
    private function scrubAuditLog(int $id, array $names): void
    {
        $ref = "DB-{$id}";
        $refPattern = preg_quote($ref, '/').'(?!\d)';

        DB::table('logs')
            ->where('subject_type', User::class)
            ->where('subject_id', $id)
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($ref, $refPattern, $names) {
                foreach ($rows as $row) {
                    $description = $row->description;
                    if ($description !== null) {
                        $description = $this->replaceNameNextToRef($description, $names, $refPattern, $ref);
                        foreach ($names as $name) {
                            $description = str_ireplace($name, $ref, $description);
                        }
                    }

                    DB::table('logs')->where('id', $row->id)->update([
                        'old_values' => $row->old_values === null ? null : $this->scrubJson($row->old_values, self::AUDIT_PERSONAL_KEYS),
                        'new_values' => $row->new_values === null ? null : $this->scrubJson($row->new_values, self::AUDIT_PERSONAL_KEYS),
                        'description' => $description,
                    ]);
                }
            });

        if ($names === []) {
            return;
        }

        DB::table('logs')
            ->where('description', 'like', "%{$ref}%")
            ->where(function ($q) use ($id) {
                $q->where('subject_type', '!=', User::class)
                    ->orWhereNull('subject_type')
                    ->orWhere('subject_id', '!=', $id)
                    ->orWhereNull('subject_id');
            })
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($ref, $refPattern, $names) {
                foreach ($rows as $row) {
                    $description = $this->replaceNameNextToRef($row->description, $names, $refPattern, $ref);
                    if ($description !== $row->description) {
                        DB::table('logs')->where('id', $row->id)->update(['description' => $description]);
                    }
                }
            });
    }

    /** "{name} (DB-{id})" and "{name} DB-{id}" → "DB-{id}". */
    private function replaceNameNextToRef(string $text, array $names, string $refPattern, string $ref): string
    {
        foreach ($names as $name) {
            $text = preg_replace(
                '/'.preg_quote($name, '/').'\s*(?:\('.$refPattern.'\)|'.$refPattern.')/iu',
                $ref,
                $text
            );
        }

        return $text;
    }
}
