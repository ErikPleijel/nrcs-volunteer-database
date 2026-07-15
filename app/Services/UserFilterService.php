<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class UserFilterService
{
    /**
     * Apply the same filters as UsersController@index.
     *
     * @param  array  $filters  Typically request()->all() or campaign->filter_json
     * @param  string  $accessLevel  'national'|'branch'|'division' (and maybe more later)
     * @param  mixed  $scopedId
     */
    public function apply(Builder $query, array $filters, string $accessLevel, $scopedId): Builder
    {
        // ----------------------------
        // Global access level scope FIRST
        // ----------------------------
        switch ($accessLevel) {
            case 'branch':
                if ($scopedId) {
                    $query->where('branch_id', $scopedId);
                }
                break;

            case 'division':
                if ($scopedId) {
                    $query->where('division_id', $scopedId);
                }
                break;

                // 'national' sees all
        }

        // ----------------------------
        // Search
        // ----------------------------
        if ($this->filled($filters, 'search')) {
            $search = trim((string) $filters['search']);

            $query->where(function ($q) use ($search) {

                // If numeric → ID search
                if (is_numeric($search)) {
                    $q->where('id', $search);
                }

                // Split search into parts (for full names)
                $parts = preg_split('/\s+/', $search) ?: [];

                $q->orWhere(function ($nameQuery) use ($parts) {
                    foreach ($parts as $part) {
                        $nameQuery->where(function ($sub) use ($part) {
                            $sub->where('first_name', 'like', "%{$part}%")
                                ->orWhere('last_name', 'like', "%{$part}%");
                        });
                    }
                });

                // Other fields
                $q->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('telephone1', 'like', "%{$search}%")
                    ->orWhere('telephone2', 'like', "%{$search}%");
            });
        }

        // ----------------------------
        // Location filters
        // ----------------------------

        // Branch (only national)
        if ($accessLevel === 'national' && ! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        // Division (national + branch)
        if (in_array($accessLevel, ['national', 'branch'], true) && ! empty($filters['division_id'])) {
            $query->where('division_id', $filters['division_id']);
        }

        // Unit (any)
        if (! empty($filters['red_cross_unit_id'])) {
            $query->where('red_cross_unit_id', $filters['red_cross_unit_id']);
        }

        // Task Force (any)
        if (! empty($filters['task_force_id'])) {
            $query->whereHas('taskForces', fn ($q) => $q->where('task_forces.id', $filters['task_force_id']));
        }

        // Database Roles
        if ($this->filled($filters, 'database_role_filter')) {
            $drf = $filters['database_role_filter'];

            if ($drf === 'any') {
                $allDbRoles = array_merge(User::NATIONAL_ROLES, User::BRANCH_ROLES, User::DIVISION_ROLES);
                $query->whereHas('roles', fn ($q) => $q->whereIn('name', $allDbRoles));
            } elseif ($drf === 'branch_national') {
                $branchNational = array_merge(User::BRANCH_ROLES, User::NATIONAL_ROLES);
                $query->whereHas('roles', fn ($q) => $q->whereIn('name', $branchNational));
            }
        }

        // Team Leaders
        if ($this->filled($filters, 'team_leader_filter')) {
            $tlf = $filters['team_leader_filter'];

            if ($tlf === 'rc_unit') {
                $query->where(fn ($q) => $q->whereHas('ledRedCrossUnits')->orWhereHas('assistantLedRedCrossUnits'));
            } elseif ($tlf === 'task_force') {
                $query->where(fn ($q) => $q->whereHas('ledTaskForces')->orWhereHas('assistantLedTaskForces'));
            } elseif ($tlf === 'all') {
                $query->where(fn ($q) => $q
                    ->whereHas('ledRedCrossUnits')
                    ->orWhereHas('assistantLedRedCrossUnits')
                    ->orWhereHas('ledTaskForces')
                    ->orWhereHas('assistantLedTaskForces'));
            }
        }

        // ----------------------------
        // Demography
        // ----------------------------
        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        // Age bracket → populate age_min / age_max so the conversion below works unchanged
        if (! empty($filters['age_bracket'])) {
            $parts = explode('|', $filters['age_bracket']);
            $filters['age_min'] = $parts[0] !== '' ? $parts[0] : null;
            $filters['age_max'] = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null;
        }

        // Age range
        if ($this->filled($filters, 'age_min')) {
            $maxBirthYear = Carbon::now()->subYears((int) $filters['age_min'])->year;
            $query->where('birth_year', '<=', $maxBirthYear);
        }

        if ($this->filled($filters, 'age_max')) {
            $minBirthYear = Carbon::now()->subYears((int) $filters['age_max'])->year;
            $query->where('birth_year', '>=', $minBirthYear);
        }

        // ----------------------------
        // Membership / volunteer intent flags
        // ----------------------------
        if (($filters['is_member'] ?? null) == '1') {
            $query->hasValidMembership();
        }

        if (($filters['is_volunteer'] ?? null) == '1') {
            $query->whereNotNull('red_cross_unit_id');
        }

        if (($filters['wants_membership'] ?? null) == '1') {
            $query->where('can_contribute_member', true);
        }

        if (($filters['wants_volunteer'] ?? null) == '1') {
            $query->where('can_contribute_volunteering', true);
        }

        if (($filters['org_representatives'] ?? null) == '1') {
            $query->whereHas('organisations', fn ($q) => $q->whereNull('deleted_at'));
        }

        // ----------------------------
        // Dormancy (digital) filter
        // ----------------------------
        if ($this->filled($filters, 'dormancy_filter')) {
            $df = $filters['dormancy_filter'];

            if ($df === 'digital_active') {
                $query->whereNotNull('last_login_at')
                    ->where('last_login_at', '>=', now()->copy()->subMonths(6));

            } elseif ($df === 'digital_dormant') {
                $query->where(function ($q) {
                    $q->whereNull('last_login_at')
                        ->orWhere('last_login_at', '<', now()->copy()->subMonths(6));
                });

            } elseif ($df === 'never_logged_in') {
                $query->whereNull('last_login_at');
            }
        }

        // Volunteer filter (scope)
        if ($this->filled($filters, 'volunteer_filter')) {
            $query->volunteerFilter($filters['volunteer_filter']);
        }

        // Donation filter (scope)
        if ($this->filled($filters, 'donation_filter')) {
            if ($filters['donation_filter'] === 'has') {
                $query->hasDonations(true);
            } elseif ($filters['donation_filter'] === 'none') {
                $query->hasDonations(false);
            }
        }

        // Campaign message count filter — value format: "{purpose_slug}|{count_expr}[|{days}]"
        // e.g. "training_expiry|0", "membership_post_expiry|<=1", "membership_pre_expiry|0|180"
        if ($this->filled($filters, 'campaign_msg')) {
            $parts = explode('|', (string) $filters['campaign_msg']);
            $slug = $parts[0] ?? '';
            $expr = $parts[1] ?? '';
            $days = isset($parts[2]) && is_numeric($parts[2]) ? (int) $parts[2] : null;

            if ($slug !== '' && $expr !== '') {
                $purpose = \App\Models\CampaignPurpose::where('slug', $slug)->first();
                if ($purpose) {
                    // Parse operator + number from expr like "0", "1", "<=2", ">=3"
                    if (preg_match('/^(<=|>=|<|>|=)?(\d+)$/', trim($expr), $m)) {
                        $operator = $m[1] !== '' ? $m[1] : '=';
                        $number = (int) $m[2];

                        $dateCondition = $days
                            ? "AND messaging_recipients.sent_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"
                            : '';

                        $query->whereRaw(
                            "(SELECT COUNT(*)
                              FROM messaging_recipients
                              INNER JOIN messaging_campaigns
                                      ON messaging_recipients.messaging_campaign_id = messaging_campaigns.id
                              WHERE messaging_campaigns.purpose_id = ?
                                AND messaging_recipients.recipient_type = ?
                                AND messaging_recipients.recipient_id = users.id
                                AND messaging_recipients.status = 'sent'
                                {$dateCondition}
                             ) {$operator} {$number}",
                            [$purpose->id, 'App\\Models\\User']
                        );
                    }
                }
            }
        }

        // Donation recency vs. last appreciation contact — value format: "{purpose_slug}|{never|since_last}"
        // Unlike campaign_msg (a count-in-window), this compares dates directly:
        //   'never'      → has a qualifying donation AND has never received a 'sent' message for that purpose.
        //   'since_last' → their most recent donation date is AFTER their most recent 'sent' message date
        //                  for that purpose (implies at least one message was sent — NULL > date is NULL/false,
        //                  so donors who were never contacted are naturally excluded from this branch).
        // The two branches are mutually exclusive by construction, so no shared/duplicated subquery is needed.
        if ($this->filled($filters, 'donation_since_contact')) {
            $parts = explode('|', (string) $filters['donation_since_contact'], 2);
            $slug = $parts[0] ?? '';
            $mode = $parts[1] ?? '';

            if ($slug !== '' && in_array($mode, ['never', 'since_last'], true)) {
                $purpose = \App\Models\CampaignPurpose::where('slug', $slug)->first();

                if ($purpose) {
                    if ($mode === 'never') {
                        $query->whereRaw(
                            'EXISTS (
                                SELECT 1 FROM donations d
                                WHERE d.user_id = users.id
                                  AND d.is_deleted = 0
                                  AND d.anonymous = 0
                                  AND d.approval_status = \'approved\'
                                  AND d.removed_date IS NULL
                            )
                            AND NOT EXISTS (
                                SELECT 1 FROM messaging_recipients mr
                                INNER JOIN messaging_campaigns mc
                                        ON mr.messaging_campaign_id = mc.id
                                WHERE mc.purpose_id = ?
                                  AND mr.recipient_type = ?
                                  AND mr.recipient_id = users.id
                                  AND mr.status = \'sent\'
                            )',
                            [$purpose->id, 'App\\Models\\User']
                        );
                    } elseif ($mode === 'since_last') {
                        $query->whereRaw(
                            '(SELECT MAX(d.date_donation) FROM donations d
                                WHERE d.user_id = users.id
                                  AND d.is_deleted = 0
                                  AND d.anonymous = 0
                                  AND d.approval_status = \'approved\'
                                  AND d.removed_date IS NULL)
                             >
                             (SELECT MAX(mr.sent_at) FROM messaging_recipients mr
                                INNER JOIN messaging_campaigns mc
                                        ON mr.messaging_campaign_id = mc.id
                                WHERE mc.purpose_id = ?
                                  AND mr.recipient_type = ?
                                  AND mr.recipient_id = users.id
                                  AND mr.status = \'sent\')',
                            [$purpose->id, 'App\\Models\\User']
                        );
                    }
                }
            }
        }

        // ----------------------------
        // Lifecycle status / archived filter
        // ----------------------------
        $archivedFilter = $filters['archived_filter'] ?? 'operational';

        switch ($archivedFilter) {
            case 'operational':
                $query->whereIn('lifecycle_status', User::OPERATIONAL_STATUSES);
                break;

            case 'pending_engagement':
            case 'active':
            case 'dormant':
            case 'archived':
                $query->where('lifecycle_status', $archivedFilter);
                break;

            case 'all':
                // no filter
                break;

            default:
                $query->whereIn('lifecycle_status', User::OPERATIONAL_STATUSES);
                break;
        }

        // ----------------------------
        // Registration source
        // ----------------------------
        $registrationFilter = $filters['registration_filter'] ?? null; // 'admin','self','all' or null
        $query->registrationSource($registrationFilter);

        // ----------------------------
        // Person type filter (member vs volunteer)
        // ----------------------------
        if ($this->filled($filters, 'person_type')) {
            $personType = $filters['person_type'];

            if ($personType === 'member') {
                // Members: valid payment AND not in an RC unit
                $query->hasValidMembership();

            } elseif ($personType === 'volunteer') {
                // Volunteers: in an RC unit and not archived
                $query->whereNotNull('red_cross_unit_id')
                    ->where('lifecycle_status', '!=', 'archived');

            } elseif ($personType === 'unassigned') {
                // Unassigned ghosts: left their RC unit, no genuine membership to fall back on
                $query->unassignedGhost();
            }
        }

        // ----------------------------
        // Photo / signature filter
        // (Fixed grouping so OR doesn't escape other filters)
        // ----------------------------
        if ($this->filled($filters, 'photo_signature_filter')) {
            $value = $filters['photo_signature_filter'];

            if ($value === 'photo_yes') {
                $query->whereNotNull('picture')->where('picture', '!=', '');

            } elseif ($value === 'photo_no') {
                $query->where(function ($q) {
                    $q->whereNull('picture')->orWhere('picture', '');
                });

            } elseif ($value === 'sign_yes') {
                $query->whereNotNull('signature')->where('signature', '!=', '');

            } elseif ($value === 'sign_no') {
                $query->where(function ($q) {
                    $q->whereNull('signature')->orWhere('signature', '');
                });
            }
        }

        // ----------------------------
        // Training filter
        // ----------------------------
        if ($this->filled($filters, 'training_filter')) {
            $filter = $filters['training_filter'];

            if ($filter === 'has_any') {
                $query->hasTraining(true);

            } elseif ($filter === 'none_any') {
                $query->hasTraining(false);

            } elseif ($filter === 'has_firstaid') {
                $query->whereHas('trainings', fn ($q) => $q
                    ->where('is_deleted', false)
                    ->whereHas('trainingType', fn ($q) => $q->where('is_first_aid', true))
                );

            } elseif ($filter === 'none_firstaid') {
                $query->whereDoesntHave('trainings', fn ($q) => $q
                    ->where('is_deleted', false)
                    ->whereHas('trainingType', fn ($q) => $q->where('is_first_aid', true))
                );

            } elseif (preg_match('/^has_(\d+)$/', $filter, $matches)) {
                $query->hasTrainingType($matches[1]);

            } elseif (preg_match('/^none_(\d+)$/', $filter, $matches)) {
                $query->hasNotTrainingType($matches[1]);
            }
        }

        // Training expiry filter — format: "{training_type_id}|{days|expired}"
        if ($this->filled($filters, 'training_expiry')) {
            $parts = explode('|', (string) $filters['training_expiry'], 2);
            if (count($parts) === 2) {
                [$typeId, $expr] = $parts;
                $trainingType = \App\Models\TrainingType::find((int) $typeId);
                if ($trainingType && $expr === 'expired') {
                    // Mirrors Training::scopeExpired(): expiry is based on the
                    // latest record's own stored valid_years snapshot, not the
                    // training type's current live validity_years_limit, so a
                    // later change to the type's default validity doesn't
                    // retroactively "expire" records created under an older
                    // policy. A NULL valid_years snapshot means never-expiring.
                    $query->whereRaw(
                        'EXISTS (
                            SELECT 1 FROM trainings t
                            WHERE t.user_id = users.id
                              AND t.training_type_id = ?
                              AND t.is_deleted = 0
                              AND t.id = (
                                    SELECT t2.id FROM trainings t2
                                    WHERE t2.user_id = users.id
                                      AND t2.training_type_id = ?
                                      AND t2.is_deleted = 0
                                    ORDER BY t2.training_date DESC, t2.id DESC
                                    LIMIT 1
                              )
                              AND t.valid_years IS NOT NULL
                              AND DATE_ADD(t.training_date, INTERVAL t.valid_years YEAR) < CURDATE()
                        )',
                        [$typeId, $typeId]
                    );
                } elseif ($trainingType && $trainingType->validity_years_limit) {
                    $years = (int) $trainingType->validity_years_limit;
                    // NOTE: same live-vs-stored validity_years_limit issue as the
                    // old 'expired' branch — this "expiring within N days" window
                    // also uses the training type's current live validity_years_limit
                    // rather than each record's stored valid_years, and can therefore
                    // misclassify records created under a different validity policy.
                    // Flagged, not fixed — out of scope for this change.
                    if (ctype_digit((string) $expr)) {
                        $days = (int) $expr;
                        $query->whereRaw(
                            'EXISTS (
                                SELECT 1 FROM trainings t
                                WHERE t.user_id = users.id
                                  AND t.training_type_id = ?
                                  AND t.is_deleted = 0
                                  AND DATE_ADD(
                                        (SELECT t2.training_date FROM trainings t2
                                         WHERE t2.user_id = users.id
                                           AND t2.training_type_id = ?
                                           AND t2.is_deleted = 0
                                         ORDER BY t2.training_date DESC LIMIT 1),
                                        INTERVAL ? YEAR
                                      ) >= CURDATE()
                                  AND DATE_ADD(
                                        (SELECT t2.training_date FROM trainings t2
                                         WHERE t2.user_id = users.id
                                           AND t2.training_type_id = ?
                                           AND t2.is_deleted = 0
                                         ORDER BY t2.training_date DESC LIMIT 1),
                                        INTERVAL ? YEAR
                                      ) <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                            )',
                            [$typeId, $typeId, $years, $typeId, $years, $days]
                        );
                    }
                }
            }
        }

        // ----------------------------
        // First Aid Refresher — users whose most recent first-aid training has gone stale.
        // Value = month threshold (12–60). NULL/never-trained are excluded by design.
        // last_first_aid_at is indexed, so this stays cheap at scale.
        // ----------------------------
        if ($this->filled($filters, 'first_aid_refresher')) {
            $months = (int) $filters['first_aid_refresher'];

            if ($months >= 12 && $months <= 60) {
                $query->whereNotNull('last_first_aid_at')
                    ->whereDate('last_first_aid_at', '<', now()->subMonths($months)->toDateString());
            }
        }

        // ----------------------------
        // Membership filter
        // ----------------------------
        if ($this->filled($filters, 'membership_filter')) {
            $filter = $filters['membership_filter'];

            if ($filter === 'members') {
                $query->hasValidMembership();

            } elseif (in_array($filter, ['expiring_14', 'expiring_28'], true)) {
                $days = $filter === 'expiring_14' ? 14 : 28;
                $start = now()->startOfDay();
                $end = now()->addDays($days)->endOfDay();

                $query->whereHas('currentMembershipPayment', function ($q) use ($start, $end) {
                    $q->personal()->whereBetween('expiry_date', [$start, $end]);
                });

            } elseif ($filter === 'expired_members') {
                $query->whereHas('membershipPayments', fn ($q) => $q->personal())
                    ->whereDoesntHave('currentMembershipPayment', fn ($q) => $q->personal());

            } elseif ($filter === 'non_members') {
                $query->whereDoesntHave('membershipPayments', fn ($q) => $q->personal());

            } elseif ($filter === 'wants_membership') {
                $query->where('can_contribute_member', true)
                    ->whereDoesntHave('currentMembershipPayment', fn ($q) => $q->personal());

            } elseif ($filter === 'high_value_members') {
                $medianFeeAmount = \App\Models\MembershipFee::highValueFeeThreshold();

                $query->whereHas('currentMembershipPayment', function ($q) use ($medianFeeAmount) {
                    $q->personal()->whereHas('membershipFee', function ($q2) use ($medianFeeAmount) {
                        $q2->where('amount', '>=', $medianFeeAmount);
                    });
                });

            } else {
                // treat anything else as a membership fee name via current payment
                $feeName = $filter;

                $query->whereHas('currentMembershipPayment', function ($q) use ($feeName) {
                    $q->personal()->whereHas('membershipFee', function ($q2) use ($feeName) {
                        $q2->where('name', $feeName);
                    });
                });
            }
        }

        // ----------------------------
        // Sorting (optional – keep it here so wizard previews match)
        // ----------------------------
        $sortBy = $filters['sort_by'] ?? 'created_at_desc';

        switch ($sortBy) {
            case 'created_at_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'first_name_asc':
                $query->orderBy('first_name', 'asc')->orderBy('last_name', 'asc');
                break;
            case 'first_name_desc':
                $query->orderBy('first_name', 'desc')->orderBy('last_name', 'desc');
                break;
            case 'created_at_desc':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        return $query;
    }

    /**
     * Equivalent to $request->filled('key') for an array.
     */
    protected function filled(array $filters, string $key): bool
    {
        if (! array_key_exists($key, $filters)) {
            return false;
        }

        $v = $filters[$key];

        if (is_array($v)) {
            return ! empty($v);
        }

        return trim((string) $v) !== '';
    }
}
