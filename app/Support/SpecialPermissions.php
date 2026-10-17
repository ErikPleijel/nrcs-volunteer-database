<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * The "special permission" tick boxes on users/roles: extra permissions given
 * DIRECTLY to a user (model_has_permissions), never through the role, so they
 * can differ person by person. Each tick box belongs to one or more roles and
 * may stand for several permissions (approve_financial_records = approve_payments
 * + approve_donations). They only exist while the person holds a role that
 * offers them: removing or changing the role removes them.
 *
 * 'set_by' lists the access levels allowed to change a box; everyone else sees
 * it read-only. super-admin never changes them (it only appoints national DB
 * administrators).
 */
final class SpecialPermissions
{
    public const FINANCIAL_APPROVAL = 'approve_financial_records';

    public const UPLOAD_IMAGES = 'upload_user_images';

    public const REGISTER_USERS = 'register_users';

    /** What "Can approve financial records" grants. Branch secretaries and national admins get these via their role. */
    public const FINANCIAL_APPROVAL_PERMISSIONS = ['approve_payments', 'approve_donations'];

    private const DIVISION_OPTIONS = [
        self::UPLOAD_IMAGES => [
            'label' => 'Can upload photos and signatures',
            'help' => 'Only for people in their own division who hold no admin role.',
            'permissions' => ['upload_user_images'],
            'default' => false,
            'set_by' => ['national', 'branch'],
        ],
        self::REGISTER_USERS => [
            'label' => 'Can register persons',
            'help' => 'Only in their own division.',
            'permissions' => ['add_user'],
            'default' => false,
            'set_by' => ['national', 'branch'],
        ],
    ];

    /** role => [tick box key => definition] */
    public const OPTIONS = [
        'national_db_assistant' => [
            'campaign_request_approve' => [
                'label' => 'Campaign Request Approve',
                'help' => null,
                'permissions' => ['campaign_request_approve'],
                'default' => false,
                'set_by' => ['national'],
            ],
            'print_certificates' => [
                'label' => 'Print Certificates',
                'help' => null,
                'permissions' => ['print_certificates'],
                'default' => false,
                'set_by' => ['national'],
            ],
            'print_idcards' => [
                'label' => 'Print Idcards',
                'help' => null,
                'permissions' => ['print_idcards'],
                'default' => false,
                'set_by' => ['national'],
            ],
        ],
        'branch_db_administrator' => [
            self::FINANCIAL_APPROVAL => [
                'label' => 'Can approve financial records',
                'help' => 'Donations and membership/fee payments. On by default; only HQ can change it.',
                'permissions' => self::FINANCIAL_APPROVAL_PERMISSIONS,
                'default' => true,
                'set_by' => ['national'],
            ],
        ],
        'division_db_assistant_finance' => self::DIVISION_OPTIONS,
        'division_db_assistant_operations' => self::DIVISION_OPTIONS,
    ];

    /** Tag text on the roles page's "Users by Role" table, where the permission name reads badly. */
    private const TAG_LABELS = [
        'add_user' => 'Register persons',
    ];

    /** The tag shown for a direct permission on the roles page's "Users by Role" table. */
    public static function tagLabel(string $permission): string
    {
        return self::TAG_LABELS[$permission] ?? str_replace('_', ' ', $permission);
    }

    /** @return array<string, array> the tick boxes a role offers (none for most roles) */
    public static function forRole(?string $role): array
    {
        return self::OPTIONS[$role] ?? [];
    }

    /** @return list<string> every tick box key */
    public static function allKeys(): array
    {
        return array_values(array_unique(array_merge(...array_map('array_keys', array_values(self::OPTIONS)))));
    }

    /** @return list<string> every permission any tick box can grant */
    public static function allPermissionNames(): array
    {
        $names = [];
        foreach (self::OPTIONS as $options) {
            foreach ($options as $option) {
                $names = array_merge($names, $option['permissions']);
            }
        }

        return array_values(array_unique($names));
    }

    /** Whether $actor may change this tick box (others see it read-only). */
    public static function canSet(User $actor, string $role, string $key): bool
    {
        $option = self::forRole($role)[$key] ?? null;

        return $option !== null
            && ! $actor->hasRole('super-admin')
            && in_array($actor->getAccessLevel(), $option['set_by'], true);
    }

    /** Whether $actor may change at least one tick box of $role. */
    public static function canSetAny(User $actor, ?string $role): bool
    {
        foreach (array_keys(self::forRole($role)) as $key) {
            if (self::canSet($actor, $role, $key)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> the tick box keys of $role that $user currently holds (all its permissions, directly) */
    public static function heldKeys(User $user, ?string $role): array
    {
        $direct = $user->getDirectPermissions()->pluck('name')->all();

        return array_keys(array_filter(
            self::forRole($role),
            fn (array $option) => array_diff($option['permissions'], $direct) === []
        ));
    }

    /**
     * The direct permissions $target should hold after $actor gives them
     * $incomingRole ('' = no role). $submittedKeys is the ticked boxes, or null
     * when the form did not include the tick boxes at all.
     *
     * Per box: a box the actor may change takes the submitted value; otherwise
     * it keeps the target's current value if their previous role offered the
     * same box (unchanged role, or finance <-> operations), else its default.
     * Boxes the new role does not offer are dropped.
     *
     * @return list<string>
     */
    public static function resolve(User $actor, User $target, string $incomingRole, ?array $submittedKeys): array
    {
        $previousRole = $target->getRoleNames()->first();
        $previousKeys = array_keys(self::forRole($previousRole));
        $heldKeys = self::heldKeys($target, $previousRole);

        $permissions = [];
        foreach (self::forRole($incomingRole) as $key => $option) {
            if ($submittedKeys !== null && self::canSet($actor, $incomingRole, $key)) {
                $on = in_array($key, $submittedKeys, true);
            } elseif (in_array($key, $previousKeys, true)) {
                $on = in_array($key, $heldKeys, true);
            } else {
                $on = $option['default'];
            }

            if ($on) {
                $permissions = array_merge($permissions, $option['permissions']);
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Give $user the tick boxes that are ON by default for the roles they hold
     * (today: "Can approve financial records" for branch DB administrators).
     * For every path that assigns a role outside users/roles, e.g. the legacy
     * migration. Adds only; never removes.
     */
    public static function grantDefaults(User $user): void
    {
        $permissions = [];
        foreach ($user->getRoleNames() as $role) {
            foreach (self::forRole($role) as $option) {
                if ($option['default']) {
                    $permissions = array_merge($permissions, $option['permissions']);
                }
            }
        }

        if ($permissions !== []) {
            $user->givePermissionTo(array_map(
                fn (string $name) => Permission::findOrCreate($name, 'web'),
                array_values(array_unique($permissions))
            ));
        }
    }

    /**
     * How many OTHER people in $target's branch can still approve financial
     * records: branch secretaries, plus branch DB administrators who hold the
     * tick box. Archived and anonymized people are not counted. National admins
     * (HQ) can always approve and are not counted here.
     */
    public static function otherBranchFinancialApprovers(User $target): int
    {
        if (! $target->branch_id) {
            return 0;
        }

        return User::query()
            ->where('branch_id', $target->branch_id)
            ->where('id', '!=', $target->id)
            ->where('lifecycle_status', '!=', 'archived')
            ->whereNull('anonymized_at')
            ->where(fn ($q) => $q
                ->whereHas('roles', fn ($r) => $r->where('name', 'branch_secretary'))
                ->orWhere(fn ($q) => $q
                    ->whereHas('roles', fn ($r) => $r->where('name', 'branch_db_administrator'))
                    ->whereHas('permissions', fn ($p) => $p->whereIn('name', self::FINANCIAL_APPROVAL_PERMISSIONS), '=', count(self::FINANCIAL_APPROVAL_PERMISSIONS))))
            ->count();
    }
}
