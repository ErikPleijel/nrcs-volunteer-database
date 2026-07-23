<?php

namespace App\Support\Filters;

use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\TaskForce;
use App\Models\TrainingType;
use Illuminate\Support\Arr;

class UserFilterDescriber
{
    /**
     * MAIN ENTRY POINT
     *
     * Input: filter array (from request OR campaign->filter_json)
     * Output: array of human readable labels
     */
    public static function labels(array $filters): array
    {
        $labels = [];

        $get = fn (string $key, $default = null) => Arr::get($filters, $key, $default);

        // --------------------------------------------------
        // Lazy lookups (only loaded once per request)
        // --------------------------------------------------
        static $branches, $divisions, $units, $taskForces, $trainingTypes;

        $branches ??= Branch::select('id', 'name')->get()->keyBy('id');
        $divisions ??= Division::select('id', 'name')->get()->keyBy('id');
        $units ??= RedCrossUnit::select('id', 'name')->get()->keyBy('id');
        $taskForces ??= TaskForce::select('id', 'name')->get()->keyBy('id');
        $trainingTypes ??= TrainingType::active()->select('id', 'name')->get()->keyBy('id');

        // --------------------------------------------------
        // Search
        // --------------------------------------------------
        if ($search = $get('search')) {
            $labels[] = 'Search: "'.$search.'"';
        }

        // --------------------------------------------------
        // Location
        // --------------------------------------------------
        if ($id = $get('branch_id')) {
            if (isset($branches[$id])) {
                $labels[] = 'Branch: '.$branches[$id]->name;
            }
        }

        if ($id = $get('division_id')) {
            if (isset($divisions[$id])) {
                $labels[] = 'Division: '.$divisions[$id]->name;
            }
        }

        if ($id = $get('red_cross_unit_id')) {
            if (isset($units[$id])) {
                $labels[] = 'Unit: '.$units[$id]->name;
            }
        }

        if ($id = $get('task_force_id')) {
            if (isset($taskForces[$id])) {
                $labels[] = 'Task Force: '.$taskForces[$id]->name;
            }
        }

        // --------------------------------------------------
        // Membership
        // --------------------------------------------------
        if ($mf = $get('membership_filter')) {
            $labels[] = match ($mf) {
                'members' => 'Members only',
                'expiring_14' => 'Membership expiring within 14 days',
                'expiring_28' => 'Membership expiring within 28 days',
                'expired_members' => 'Expired members',
                'non_members' => 'Non-members',
                'wants_membership' => 'Wants membership',
                'high_value_members' => 'High-value members (above median fee tier)',
                default => 'Membership fee: '.$mf,
            };
        }

        // --------------------------------------------------
        // Photo / signature
        // --------------------------------------------------
        if ($ps = $get('photo_signature_filter')) {
            $labels[] = match ($ps) {
                'photo_yes' => 'With profile photo',
                'photo_no' => 'No profile photo',
                'sign_yes' => 'With signature',
                'sign_no' => 'No signature',
                default => null,
            };
        }

        // --------------------------------------------------
        // Verification
        // --------------------------------------------------
        if ($vf = $get('verification_filter')) {
            $labels[] = match ($vf) {
                'unverified' => 'Unverified email',
                default => null,
            };
        }

        // --------------------------------------------------
        // Lifecycle (archived_filter)
        // --------------------------------------------------
        $labels[] = match ($get('archived_filter', 'operational')) {
            'operational' => 'Lifecycle: Active-Dormant',
            'pending_engagement' => 'Lifecycle: Pending Engagement',
            'active' => 'Lifecycle: Active',
            'dormant' => 'Lifecycle: Dormant',
            'archived' => 'Lifecycle: Archived',
            'all' => 'Lifecycle: All (Incl. Archived)',
            default => null,
        };

        // --------------------------------------------------
        // Digital activity
        // --------------------------------------------------
        if ($df = $get('dormancy_filter')) {
            $labels[] = match ($df) {
                'digital_active' => 'Digital: Active (≤6mo)',
                'digital_dormant' => 'Digital: Dormant (>6mo)',
                'never_logged_in' => 'Digital: Never logged in',
                default => null,
            };
        }

        // --------------------------------------------------
        // --------------------------------------------------
        // Training
        // --------------------------------------------------
        if ($tf = $get('training_filter')) {
            if ($tf === 'has_any') {
                $labels[] = 'Has any training';
            } elseif ($tf === 'none_any') {
                $labels[] = 'No trainings at all';
            } elseif ($tf === 'has_firstaid') {
                $labels[] = 'Has first aid training';
            } elseif ($tf === 'none_firstaid') {
                $labels[] = 'No first aid training';
            } elseif (preg_match('/^has_(\d+)$/', $tf, $m) && isset($trainingTypes[$m[1]])) {
                $labels[] = 'Has training in '.strtolower($trainingTypes[$m[1]]->name);
            } elseif (preg_match('/^none_(\d+)$/', $tf, $m) && isset($trainingTypes[$m[1]])) {
                $labels[] = 'No training in '.strtolower($trainingTypes[$m[1]]->name);
            }
        }

        // Training expiry filter
        if ($te = $get('training_expiry')) {
            $parts = explode('|', (string) $te, 2);
            if (count($parts) === 2) {
                [$typeId, $expr] = $parts;
                $type = \App\Models\TrainingType::find((int) $typeId);
                $name = $type?->name ?? "Training #{$typeId}";
                $labels[] = match ($expr) {
                    '28' => "{$name} expires within 28 days",
                    '21' => "{$name} expires within 21 days",
                    '14' => "{$name} expires within 14 days",
                    '7' => "{$name} expires within 7 days",
                    'expired' => "{$name} has expired (not renewed)",
                    default => "{$name} expiry: {$expr}",
                };
            }
        }

        // First Aid Refresher
        if ($far = $get('first_aid_refresher')) {
            $months = (int) $far;
            if ($months >= 12 && $months <= 60) {
                $labels[] = "First aid older than {$months} months";
            }
        }

        // --------------------------------------------------
        // Gender
        // --------------------------------------------------
        if ($gender = $get('gender')) {
            $g = strtolower($gender);
            $labels[] = in_array($g, ['male', 'female', 'other'], true)
                ? ucfirst($g)
                : 'Gender: '.$gender;
        }

        // --------------------------------------------------
        // Age
        // --------------------------------------------------
        $bracketLabels = [
            '1|17' => 'Under 18',
            '18|35' => 'Youth (18–35)',
            '36|59' => 'Adults (36–59)',
            '60|' => 'Elderly (60+)',
            '1|5' => 'Toddlers & pre-school (1–5)',
            '6|11' => 'Primary school (6–11)',
            '12|14' => 'Junior secondary (12–14)',
            '15|17' => 'Senior secondary (15–17)',
            '18|25' => 'Young adults (18–25)',
            '26|35' => 'Adults (26–35)',
            '36|50' => 'Middle-aged (36–50)',
            '51|65' => 'Senior adults (51–65)',
            '66|' => 'Elderly (66+)',
        ];

        $bracket = $filters['age_bracket'] ?? null;
        if ($bracket && isset($bracketLabels[$bracket])) {
            $labels[] = 'Age: '.$bracketLabels[$bracket];
        } else {
            $ageMin = $get('age_min');
            $ageMax = $get('age_max');
            $currentYear = now()->year;

            if ($ageMin !== null && $ageMax !== null && $ageMin !== '' && $ageMax !== '') {
                $olderBirthYear = $currentYear - (int) $ageMin;
                $youngerBirthYear = $currentYear - (int) $ageMax;
                $labels[] = "Age: {$ageMin}–{$ageMax} years  (born {$youngerBirthYear}–{$olderBirthYear})";
            } elseif ($ageMin !== null && $ageMin !== '') {
                $labels[] = "Age: {$ageMin} years or older  (born ".($currentYear - (int) $ageMin).' or earlier)';
            } elseif ($ageMax !== null && $ageMax !== '') {
                $labels[] = "Age: up to {$ageMax} years  (born ".($currentYear - (int) $ageMax).' or later)';
            }
        }

        // --------------------------------------------------
        // Misc
        // --------------------------------------------------
        if ($get('donation_filter') === 'has') {
            $labels[] = 'Has made donations';
        }
        if ($get('donation_filter') === 'none') {
            $labels[] = 'No donations';
        }

        // Campaign message count filter
        if (! empty($filters['campaign_msg'])) {
            $parts = explode('|', (string) $filters['campaign_msg']);
            $slug = $parts[0] ?? '';
            $countExpr = trim($parts[1] ?? '');
            $days = isset($parts[2]) && is_numeric($parts[2]) ? (int) $parts[2] : null;

            if ($slug !== '' && $countExpr !== '') {
                $purpose = \App\Models\CampaignPurpose::where('slug', $slug)->first();
                $name = $purpose?->name ?? $slug;

                $label = match (true) {
                    $countExpr === '0' => "{$name}: not yet contacted",
                    $countExpr === '<=1' => "{$name}: contacted at most once",
                    $countExpr === '<=2' => "{$name}: contacted at most twice",
                    $countExpr === '>=3' => "{$name}: contacted 3 or more times",
                    default => "{$name}: {$countExpr} messages",
                };

                if ($days) {
                    $months = round($days / 30);
                    $timeLabel = $months >= 1 ? "in the past {$months} months" : "in the past {$days} days";
                    $label .= " ({$timeLabel})";
                }

                $labels[] = $label;
            }
        }
        // Donation recency vs. last appreciation contact
        if (! empty($filters['donation_since_contact'])) {
            $parts = explode('|', (string) $filters['donation_since_contact'], 2);
            $slug = $parts[0] ?? '';
            $mode = $parts[1] ?? '';

            if ($slug !== '') {
                $purpose = \App\Models\CampaignPurpose::where('slug', $slug)->first();
                $name = $purpose?->name ?? $slug;

                $label = match ($mode) {
                    'never' => "{$name}: has donated, never thanked",
                    'since_last' => "{$name}: donated again since being thanked",
                    default => null,
                };

                if ($label) {
                    $labels[] = $label;
                }
            }
        }

        if (! empty($filters['volunteer_filter'])) {
            $labels[] = match ($filters['volunteer_filter']) {
                'wants_volunteer' => 'Wants to contribute as volunteer',
                'wants_member' => 'Wants to contribute as member',
                'is_volunteer' => 'Volunteers only',
                default => 'Volunteer filter: '.$filters['volunteer_filter'],
            };
        }

        if (! empty($filters['database_role_filter'])) {
            $labels[] = match ($filters['database_role_filter']) {
                'any' => 'All Database Roles',
                'branch_national' => 'Database Roles (Branch & National)',
                default => null,
            };
        }

        if (! empty($filters['team_leader_filter'])) {
            $labels[] = match ($filters['team_leader_filter']) {
                'rc_unit' => 'RC Unit Team Leaders',
                'task_force' => 'Task Force Team Leaders',
                'all' => 'All Team Leaders',
                default => null,
            };
        }

        if (! empty($filters['person_type'])) {
            $labels[] = match ($filters['person_type']) {
                'volunteer' => 'Volunteers only',
                'member' => 'Members only',
                'unassigned' => 'Volunteers in Limbo (left unit, not reassigned)',
                default => 'Person type: '.$filters['person_type'],
            };
        }
        if ($get('include_deactivated') === '1') {
            $labels[] = 'Deactivated only';
        }
        if ($get('email_status') === 'with') {
            $labels[] = 'With email';
        }
        if ($get('email_status') === 'without') {
            $labels[] = 'Without email';
        }
        if ($get('org_representatives') == '1') {
            $labels[] = 'Org representatives only';
        }

        return array_values(array_filter($labels));
    }

    /**
     * Convenience helper if you want ready-to-print HTML
     */
    public static function description(array $filters, string $empty = 'Showing all users'): string
    {
        $labels = self::labels($filters);

        if (empty($labels)) {
            return e($empty);
        }

        return implode(
            ' <span class="text-gray-400 px-1">&middot;</span> ',
            array_map(fn ($l) => e($l), $labels)
        );
    }
}
