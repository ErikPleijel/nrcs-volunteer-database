<?php

namespace App\Models;

use App\Models\Log as AuditLog;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasRoles, Notifiable; // Add HasApiTokens if needed

    protected $fillable = [
        'id_check_token',
        'first_name',
        'middle_name',
        'last_name',
        'title',
        'email',
        'password',
        'email_verified_at',
        'gender',
        'birth_year',
        'marital_status',
        'national_id_number',
        'organisation',
        'occupation',
        'residential_address',
        'workplace_address',
        'telephone1',
        'telephone2',
        'disciplin',
        'personal_info',
        'branch_id',
        'division_id',
        'red_cross_unit_id',
        'assigned_rcu_date',
        'assigned_rcu_by_id',
        'picture',
        'is_form_registration',
        'form_reg_id',
        'passport_photo',
        'signature',
        'can_contribute_volunteering',
        'can_contribute_member',
        'lifecycle_status',
        'legacy_password_hash', // Added for mass assignment if needed
        'email_opt_out',
        'email_opt_out_at',
        'sms_opt_out',
        'sms_opt_out_at',
        'consent_obtained_at',
        'consent_obtained_by_id',
        'consent_notes',
        'policy_accepted_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'legacy_password_hash', // Added to hide the legacy hash from array/JSON output
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'national_id_number' => 'encrypted',
        'personal_info' => 'encrypted',
        'birth_year' => 'integer',
        'last_login_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'last_first_aid_at' => 'date',
        'last_admin_activity_at' => 'datetime',
        'image_upload_date' => 'datetime',
        'is_inactive' => 'boolean',
        'email_opt_out' => 'boolean',
        'email_opt_out_at' => 'datetime',
        'sms_opt_out' => 'boolean',
        'sms_opt_out_at' => 'datetime',
        'consent_obtained_at' => 'datetime',
        'policy_accepted_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($user) {
            if (empty($user->id_check_token)) {
                $user->id_check_token = Str::random(32);
            }
        });

        static::saving(function (User $user) {
            if (($user->isDirty('division_id') || $user->isDirty('branch_id'))
                && $user->division_id && $user->branch_id) {
                $division = Division::find($user->division_id);
                if ($division && $division->branch_id != $user->branch_id) {
                    throw new \InvalidArgumentException(
                        "User {$user->id}: division_id {$user->division_id} belongs to "
                        ."branch {$division->branch_id}, not the assigned branch_id "
                        ."{$user->branch_id}."
                    );
                }
            }
        });

        static::updating(function (User $user) {
            $sensitiveFields = ['national_id_number', 'personal_info', 'passport_photo', 'signature', 'picture'];
            $changed = array_values(array_filter($sensitiveFields, fn ($f) => $user->isDirty($f)));

            if (empty($changed)) {
                return;
            }

            // Store '[redacted]' for every changed field — never log actual content.
            $redacted = array_fill_keys($changed, '[redacted]');

            AuditLog::write(
                'sensitive_fields_updated',
                $user,
                ['branch_id' => $user->branch_id, 'division_id' => $user->division_id],
                $redacted,
                $redacted,
                'Sensitive fields changed: '.implode(', ', $changed)
            );
        });
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function organisations()
    {
        return $this->belongsToMany(Organisation::class, 'organisation_user', 'user_id', 'organisation_id')
            ->using(OrganisationUser::class)
            ->withPivot(['is_primary_contact', 'linked_at', 'linked_by'])
            ->withTimestamps();
    }

    /**
     * The roles that imply national-level access.
     */
    const NATIONAL_ROLES = [
        'super-admin',
        'national_db_administrator',
        'observer_national_level',
        'national_db_assistant',
    ];

    /**
     * The roles that imply branch-level access (excluding national roles).
     */
    const BRANCH_ROLES = [
        'branch_db_administrator',
        'branch_secretary',
        'branch_db_assistant',
    ];

    /**
     * The roles that imply division-level access (excluding national/branch roles).
     */
    const DIVISION_ROLES = [
        'division_db_assistant_finance',
        'division_db_assistant_operations',

    ];

    /**
     * Lifecycle statuses that make up the "operational" population
     * (the archived_filter default). Excludes pending_engagement and archived.
     */
    const OPERATIONAL_STATUSES = [
        'active',
        'dormant',
    ];

    /**
     * A user belongs to a branch
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * A user belongs to a division
     */
    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the Red Cross Unit for this user.
     */
    public function redCrossUnit()
    {
        return $this->belongsTo(RedCrossUnit::class, 'red_cross_unit_id');
    }

    /**
     * Red cross units where this user is a team leader
     */
    public function ledRedCrossUnits()
    {
        return $this->hasMany(RedCrossUnit::class, 'team_leader_user_id');
    }

    /**
     * Red cross units where this user is an assistant team leader
     */
    public function assistantLedRedCrossUnits()
    {
        return $this->hasMany(RedCrossUnit::class, 'assistant_team_leader_user_id');
    }

    /**
     * Task forces where this user is the team leader
     */
    public function ledTaskForces()
    {
        return $this->hasMany(TaskForce::class, 'team_leader_user_id');
    }

    /**
     * Task forces where this user is the assistant team leader
     */
    public function assistantLedTaskForces()
    {
        return $this->hasMany(TaskForce::class, 'assist_team_leader_user_id');
    }

    public function certificatePrints()
    {
        return $this->hasMany(CertificatePrint::class);
    }

    /**
     * Donations made by this user
     */
    public function donations()
    {
        return $this->hasMany(Donation::class, 'user_id');
    }

    public function tutorialProgress()
    {
        return $this->hasMany(TutorialProgress::class);
    }

    /**
     * Donations entered/created by this user
     */
    public function enteredDonations()
    {
        return $this->hasMany(Donation::class, 'entered_by_user_id');
    }

    /**
     * Donations removed by this user
     */
    public function removedDonations()
    {
        return $this->hasMany(Donation::class, 'removed_by_user_id');
    }

    /**
     * Get the user's profession.
     * Uses 'disciplin' if present, otherwise falls back to 'occupation'.
     */
    public function getProfessionAttribute()
    {
        // Pick disciplin or fallback
        $value = $this->disciplin ?? $this->occupation;

        if (! $value) {
            return null;
        }

        // If the string is ALL CAPS, normalize it (lowercase → first letter uppercase)
        if (strtoupper($value) === $value) {
            $value = strtolower($value);
        }

        // Always capitalize the first letter
        return ucfirst($value);
    }

    /**
     * How many years old the uploaded image is.
     * Returns null if no image upload date exists.
     */
    public function getImageAgeInYearsAttribute()
    {
        if (! $this->image_upload_date) {
            return null;
        }

        $days = Carbon::parse($this->image_upload_date)->diffInDays(now());

        return round($days / 365);  // whole years only
    }

    /**
     * Scope: find users whose image upload is older than X years.
     * Default: 5 years.
     */
    public function scopeImageTooOld($query, $years = 5)
    {
        $cutoff = Carbon::now()->subYears($years);

        return $query->whereNotNull('image_upload_date')
            ->where('image_upload_date', '<', $cutoff);
    }

    public function getImageIsTooOldAttribute()
    {
        return $this->image_age_in_years !== null &&
            $this->image_age_in_years > 5;
    }

    public function getPhotoAgeLabelAttribute(): ?string
    {
        if (is_null($this->image_age_in_years)) {
            return null; // caller can decide what to show for "unknown"
        }

        $ageRaw = (float) $this->image_age_in_years;
        $age = (int) floor($ageRaw); // or round() if you prefer

        if ($age <= 0) {
            return 'Photo: New';
        }

        if ($age === 1) {
            return 'Photo: 1 year old';
        }

        return 'Photo: '.$age.' years old';
    }

    /**
     * Registered by admin (admin form).
     */
    public function scopeAdminRegistered(Builder $query): Builder
    {
        return $query->where('is_form_registration', 1);
    }

    /**
     * Self-registered (public form / user created).
     */
    public function scopeSelfRegistered(Builder $query): Builder
    {
        return $query->where('is_form_registration', 0);
    }

    /**
     * Generic scope for controller filters.
     *
     * @param  string|null  $filter  'admin' | 'self' | null
     */
    public function scopeRegistrationSource(Builder $query, ?string $filter): Builder
    {
        if ($filter === 'admin') {
            return $query->adminRegistered();
        }

        if ($filter === 'self') {
            return $query->selfRegistered();
        }

        // 'all' or null → no extra filter
        return $query;
    }

    /**
     * Get the user's full name
     */
    public function getFullNameAttribute(): string
    {
        return Str::of("{$this->first_name} {$this->last_name}")
            ->squish()
            ->title();
    }

    public function getFullMiddleNameAttribute(): string
    {
        $title = $this->title ? trim($this->title).' ' : '';

        if ($this->middle_name) {
            return "{$title}{$this->first_name} ({$this->middle_name}) {$this->last_name}";
        }

        return "{$title}{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the user's initials
     */
    public function getInitialsAttribute()
    {
        $initials = '';
        if ($this->first_name) {
            $initials .= strtoupper(substr($this->first_name, 0, 1));
        }
        if ($this->last_name) {
            $initials .= strtoupper(substr($this->last_name, 0, 1));
        }

        return $initials;
    }

    public function getProfilePhotoUrlAttribute(): string
    {
        if ($this->picture) {
            return route('photos.show', [$this->id, 'profile']);
        }

        return asset('images/placeholders/profile-placeholder.png');
    }

    public function getOriginalProfilePhotoUrlAttribute(): string
    {
        if ($this->picture) {
            return route('photos.show', [$this->id, 'profile']);
        }

        return asset('images/placeholders/profile-placeholder.png');
    }

    public function getPassportPhotoUrlAttribute(): string
    {
        if ($this->passport_photo && file_exists(storage_path('app/'.$this->passport_photo))) {
            return route('photos.show', [$this->id, 'passport']);
        }

        return asset('images/placeholders/profile-placeholder.png');
    }

    public function getSignatureUrlAttribute(): string
    {
        if ($this->signature) {
            return route('photos.show', [$this->id, 'signature']);
        }

        return asset('images/placeholders/signature-placeholder.jpg');
    }

    /**
     * Check if user has profile photo - Updated to use 'picture' field
     */
    public function hasProfilePhoto()
    {
        return ! empty($this->picture);
    }

    public function hasPassportPhoto(): bool
    {
        return $this->passport_photo
            && file_exists(storage_path('app/'.$this->passport_photo));
    }

    public function hasAcceptedPolicy(): bool
    {
        return $this->policy_accepted_at !== null;
    }

    /**
     * Check if user has signature
     */
    public function hasSignature()
    {
        // Check if the signature attribute is set
        return ! empty($this->signature);
    }

    /**
     * Scope for users with complete profiles
     */
    public function scopeComplete($query)
    {
        return $query->whereNotNull(['first_name', 'last_name', 'email']);
    }

    /**
     * Scope for users by gender
     */
    public function scopeByGender($query, $gender)
    {
        return $query->where('gender', $gender);
    }

    /**
     * Check if user has phone number
     */
    public function hasPhoneNumber()
    {
        return ! empty($this->telephone1) || ! empty($this->telephone2);
    }

    /**
     * Get primary phone number
     */
    public function getPrimaryPhoneAttribute()
    {
        return $this->telephone1;
    }

    public function getSecondaryPhoneAttribute()
    {
        return $this->telephone2;
    }

    /**
     * Check if user is a team leader
     */
    public function isTeamLeader()
    {
        return $this->ledRedCrossUnits()->count() > 0;
    }

    /**
     * Check if user is an assistant team leader
     */
    public function isAssistantTeamLeader()
    {
        return $this->assistantLedRedCrossUnits()->count() > 0;
    }

    public function isArchived(): bool
    {
        return $this->lifecycle_status === 'archived';
    }

    /**
     * Determine if the user account is inactive/deactivated.
     */
    public function isInactive(): bool
    {
        return (bool) $this->is_inactive;
    }

    public function isActive(): bool
    {
        return ! $this->isInactive();
    }

    public function scopeNotInactive(Builder $query): Builder
    {
        return $query->where('lifecycle_status', '!=', 'archived');
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('lifecycle_status', '=', 'archived');
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->where('lifecycle_status', '!=', 'archived');
    }

    /**
     * Get active, non-anonymous donations made by this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function nonAnonymousDonations()
    {
        // NULL anonymous defaults to excluded (treated as anonymous) —
        // a donor whose anonymity flag was never set is NOT assumed
        // non-anonymous. Unlike in_kind_donation (which defaults NULL
        // to cash), this is a privacy-safe default, confirmed
        // deliberately — do not "fix" this to include NULL rows.
        return $this->donations()
            ->where('is_deleted', false)
            ->where('anonymous', false)
            ->personal();
    }

    public function scopeHasDonations($query, $hasDonations = true)
    {
        if ($hasDonations) {
            return $query->whereHas('nonAnonymousDonations');
        } else {
            return $query->whereDoesntHave('nonAnonymousDonations');
        }
    }

    /**
     * Get total donation amount by this user
     */
    public function getTotalDonationAmountAttribute()
    {
        return $this->nonAnonymousDonations()->sum('amount') ?? 0;
    }

    /**
     * Get donation count by this user
     */
    public function getDonationCountAttribute()
    {
        return $this->nonAnonymousDonations()->count();
    }

    /**
     * Count the user's in-kind donations.
     */
    public function countInKindDonations(): int
    {
        return $this->nonAnonymousDonations()->personal()->where('in_kind_donation', true)->count();
    }

    /**
     * Count the user's cash donations. A NULL in_kind_donation (type never set)
     * defaults to cash, so it isn't silently dropped from both counters.
     */
    public function countCashDonations(): int
    {
        return $this->nonAnonymousDonations()
            ->personal()
            ->where(function ($q) {
                $q->where('in_kind_donation', false)->orWhereNull('in_kind_donation');
            })
            ->count();
    }

    /**
     * Get a summary of the user's donations.
     */
    public function getDonationSummary(): string
    {
        $cashDonationsCount = $this->countCashDonations();
        $inKindDonationsCount = $this->countInKindDonations();

        $summaryParts = [];

        if ($cashDonationsCount > 0) {
            $summaryParts[] = sprintf(
                '%d cash %s',
                $cashDonationsCount,
                Str::plural('donation', $cashDonationsCount)
            );
        }

        if ($inKindDonationsCount > 0) {
            $summaryParts[] = sprintf(
                '%d in kind %s',
                $inKindDonationsCount,
                Str::plural('donation', $inKindDonationsCount)
            );
        }

        if (empty($summaryParts)) {
            return '';
        }

        return implode(' and ', $summaryParts);
    }

    /**
     * Get the activities for the user.
     */
    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Get the membership payments for the user.
     */
    public function membershipPayments()
    {
        return $this->hasMany(MembershipPayment::class);
    }

    /**
     * Get the name of the user's current, valid membership.
     */
    public function getCurrentMembershipNameAttribute(): ?string
    {
        if ($payment = $this->currentMembershipPayment()->personal()->first()) {
            return optional($payment->membershipFee)->name;
        }

        return null;
    }

    /**
     * Get the activities submitted by this user (as an admin/submitter).
     */
    public function submittedActivities()
    {
        return $this->hasMany(Activity::class, 'submitted_by_user_id');
    }

    /**
     * Get the membership payments submitted by this user (as an admin/submitter).
     */
    public function submittedMembershipPayments()
    {
        return $this->hasMany(MembershipPayment::class, 'submitted_by_user_id');
    }

    /**
     * Get only active (non-deleted) activities for the user.
     */
    public function activeActivities()
    {
        return $this->hasMany(Activity::class)->where('is_deleted', false);
    }

    /**
     * Get only active (non-deleted) membership payments for the user.
     */
    public function activeMembershipPayments()
    {
        return $this->hasMany(MembershipPayment::class)->where('is_deleted', false);
    }

    /**
     * Get the user's current valid membership payment.
     */
    public function currentMembershipPayment()
    {
        return $this->hasOne(MembershipPayment::class)
            ->where('is_deleted', false)
            ->where('expiry_date', '>=', now()->toDateString())
            ->latest('expiry_date');
    }

    /**
     * Get the user's latest valid membership payment.
     */
    public function latestMembershipPayment()
    {
        return $this->hasOne(MembershipPayment::class)
            ->where('is_deleted', false)
            ->latest('expiry_date');
    }

    /**
     * Get the trainings for the user.
     */
    public function trainings()
    {
        return $this->hasMany(Training::class);
    }

    /**
     * Get the trainings submitted by this user (as an admin/submitter).
     */
    public function submittedTrainings()
    {
        return $this->hasMany(Training::class, 'submitted_by_user_id');
    }

    /**
     * Get the task force memberships for the user.
     */
    public function taskForceMemberships()
    {
        return $this->hasMany(TaskForceMember::class);
    }

    /**
     * Get the task forces this user is a member of.
     */
    public function taskForces()
    {
        return $this->belongsToMany(TaskForce::class, 'task_force_members', 'user_id', 'task_force_id')
            ->withPivot('timestamp')
            ->withTimestamps();
    }

    /**
     * Get only active (non-deleted) trainings for the user.
     */
    public function activeTrainings()
    {
        return $this->hasMany(Training::class)->where('is_deleted', false);
    }

    public function getValidTrainingsCountAttribute(): int
    {
        return $this->validTrainings()->count();
    }

    public function getActiveTrainingsCountAttribute(): int
    {
        return $this->activeTrainings()->count();
    }

    /**
     * Get only valid (non-expired) trainings for the user.
     */
    public function validTrainings()
    {
        return $this->hasMany(Training::class)
            ->where('is_deleted', false)
            ->where(function ($query) {
                $query->whereNull('valid_years')
                    ->orWhereRaw('DATE_ADD(training_date, INTERVAL valid_years YEAR) >= CURDATE()');
            });
    }

    /**
     * Get only expired trainings for the user.
     */
    public function expiredTrainings()
    {
        return $this->hasMany(Training::class)
            ->where('is_deleted', false)
            ->whereNotNull('valid_years')
            ->whereRaw('DATE_ADD(training_date, INTERVAL valid_years YEAR) < CURDATE()');
    }

    /**
     * Check if the user has any non-deleted training record.
     */
    public function hasTraining(): bool
    {
        return $this->activeTrainings()->exists();
    }

    /**
     * Check if the user has any 'First Aid' training (valid or expired).
     */
    public function hasFirstAidTraining(): bool
    {
        return $this->trainings()
            ->where('is_deleted', false)
            ->whereHas('trainingType', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%first aid%'])
            )
            ->exists();
    }

    /**
     * Check if the user has a valid (non-expired) 'First Aid' training.
     */
    public function hasValidFirstAidTraining(): bool
    {
        return $this->trainings()
            ->where('is_deleted', false)
            ->where(function ($query) {
                $query->whereNull('valid_years')
                    ->orWhereRaw('DATE_ADD(training_date, INTERVAL valid_years YEAR) >= CURDATE()');
            })
            ->whereHas('trainingType', function ($query) {
                $query->where('name', 'like', '%First Aid%');
            })
            ->exists();
    }

    /**
     * Returns true if the user has one or more First Aid trainings
     * and all of them are expired (i.e. none are currently valid).
     */
    public function hasOnlyExpiredFirstAidTraining(): bool
    {
        return $this->hasFirstAidTraining() && ! $this->hasValidFirstAidTraining();
    }

    // Scope for general training (any training)
    public function scopeHasTraining($query, $hasTraining = true)
    {
        if ($hasTraining) {
            return $query->whereHas('trainings');
        } else {
            return $query->whereDoesntHave('trainings');
        }
    }

    // Scopes for First Aid filter (place in User model)

    /**
     * Scope for users who have at least one First Aid training (valid or expired)
     */
    public function scopeHasFirstAidTraining($query, $has = true)
    {
        $fn = function ($query) {
            $query->where('is_deleted', false)
                ->whereHas('trainingType', function ($q) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%first aid%']);
                });
        };

        return $has
            ? $query->whereHas('trainings', $fn)
            : $query->whereDoesntHave('trainings', $fn);
    }

    /**
     * Scope for users who have at least one valid (not expired) First Aid training
     */
    public function scopeHasValidFirstAidTraining($query)
    {
        return $query->whereHas('trainings', function ($q) {
            $q->where('is_deleted', false)
                ->where(function ($q2) {
                    $q2->whereNull('valid_years')
                        ->orWhereRaw('DATE_ADD(training_date, INTERVAL valid_years YEAR) >= CURDATE()');
                })
                ->whereHas('trainingType', function ($q) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%first aid%']);
                });
        });
    }

    /**
     * Scope for users who have at least one expired First Aid training (and none valid)
     */
    public function scopeHasOnlyExpiredFirstAidTraining($query)
    {
        return $query
            ->whereHas('trainings', function ($q) {
                $q->where('is_deleted', false)
                    ->whereHas('trainingType', function ($q2) {
                        $q2->whereRaw('LOWER(name) LIKE ?', ['%first aid%']);
                    });
            })
            ->whereDoesntHave('trainings', function ($q) {
                $q->where('is_deleted', false)
                    ->where(function ($q2) {
                        $q2->whereNull('valid_years')
                            ->orWhereRaw('DATE_ADD(training_date, INTERVAL valid_years YEAR) >= CURDATE()');
                    })
                    ->whereHas('trainingType', function ($q2) {
                        $q2->whereRaw('LOWER(name) LIKE ?', ['%first aid%']);
                    });
            });
    }

    /**
     * Scope users whose First Aid training will expire within the given number of days.
     */
    public function scopeHasFirstAidExpiringWithinDays(Builder $query, int $days)
    {
        $start = now()->startOfDay();
        $end = now()->addDays($days)->endOfDay();

        return $query->whereHas('trainings', function ($q) use ($start, $end) {
            $q->where('is_deleted', false)
                ->whereNotNull('valid_years')
                ->whereRaw('DATE_ADD(training_date, INTERVAL valid_years YEAR) BETWEEN ? AND ?', [$start, $end])
                ->whereHas('trainingType', function ($q2) {
                    $q2->whereRaw('LOWER(name) LIKE ?', ['%first aid%']);
                });
        });
    }

    /**
     * Scope users who have completed a specific training type.
     */
    public function scopeHasTrainingType($query, $trainingTypeId)
    {
        return $query->whereHas('activeTrainings', function ($q) use ($trainingTypeId) {
            $q->where('training_type_id', $trainingTypeId);
        });
    }

    /**
     * Scope users who have NOT completed a specific training type.
     */
    public function scopeHasNotTrainingType($query, $trainingTypeId)
    {
        return $query->whereDoesntHave('activeTrainings', function ($q) use ($trainingTypeId) {
            $q->where('training_type_id', $trainingTypeId);
        });
    }

    /**
     * Get only active task force memberships for the user.
     */
    public function activeTaskForceMemberships()
    {
        return $this->hasMany(TaskForceMember::class)
            ->whereHas('taskForce', function ($query) {
                $query->where('inactive', false);
            });
    }

    /**
     * Get only active task forces this user is a member of.
     */
    public function activeTaskForces()
    {
        return $this->belongsToMany(TaskForce::class, 'task_force_members', 'user_id', 'task_force_id')
            ->where('inactive', false)
            ->withPivot('timestamp')
            ->withTimestamps();
    }

    /**
     * Get the user's primary role (first role if multiple)
     */
    public function getPrimaryRoleAttribute()
    {
        return $this->roles->first();
    }

    /**
     * Get the user's primary role name
     */
    public function getPrimaryRoleNameAttribute()
    {
        $role = $this->getPrimaryRoleAttribute();

        return $role ? $role->name : null;
    }

    /**
     * Convert role name to readable format
     * e.g., 'national_db_administrator' -> 'National Db Administrator'
     */
    public function formatRoleName($roleName)
    {
        return ucwords(str_replace('_', ' ', $roleName));
    }

    /**
     * Get user role display name (formatted) - Updated to use the new format function
     */
    public function getRoleDisplayNameAttribute()
    {
        $roleName = $this->getPrimaryRoleNameAttribute();

        return $roleName ? $this->formatRoleName($roleName) : 'Member';
    }

    /**
     * Get all role names as a formatted comma-separated string - Updated
     */
    public function getRoleNamesStringAttribute()
    {
        return $this->getRoleNames()->map(function ($role) {
            return $this->formatRoleName($role);
        })->join(', ');
    }

    /**
     * Get formatted role names collection
     */
    public function getFormattedRoleNamesAttribute()
    {
        return $this->getRoleNames()->map(function ($role) {
            return $this->formatRoleName($role);
        });
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin()
    {
        // This method might need to be re-evaluated given the new access level logic
        // For now, retaining it as is, but consider using getAccessLevel() === 'national'
        return $this->hasRole('admin') || $this->hasRole('super-admin');
    }

    /**
     * Check if user is a branch admin
     */
    public function isBranchAdmin()
    {
        // This method might need to be re-evaluated given the new access level logic
        return $this->hasRole('branch_admin') || $this->hasAnyRole(self::BRANCH_ROLES);
    }

    /**
     * Check if user is a division admin
     */
    public function isDivisionAdmin()
    {
        // This method might need to be re-evaluated given the new access level logic
        return $this->hasRole('division_admin') || $this->hasAnyRole(self::DIVISION_ROLES);
    }

    /**
     * Check if user is a regular member based on valid membership payment.
     *
     * @return bool
     */
    public function isMember()
    {
        // A user is considered a member if they have an active and valid membership payment.
        // The currentMembershipPayment() method already returns the latest valid membership if one exists.
        return (bool) $this->currentMembershipPayment()->first();
    }

    /**
     * Check if the user is a volunteer.
     *
     * A user is considered a volunteer if they have a non-null red_cross_unit_id.
     */
    public function isVolunteer(): bool
    {
        return $this->red_cross_unit_id !== null;
    }

    public function getTotalVolunteeringHoursAttribute(): float
    {
        return (float) $this->activeActivities()->sum('hours');
    }

    /**
     * Check if the user has indicated they want to be a paid member.
     *
     * This checks the 'can_contribute_member' flag.
     */
    public function wantsMembership(): bool
    {
        return (bool) $this->can_contribute_member;
    }

    /**
     * Check if the user has indicated they are interested in volunteering opportunities.
     *
     * This checks the 'can_contribute_volunteering' flag.
     */
    public function wantsVolunteer(): bool
    {
        return (bool) $this->can_contribute_volunteering;
    }

    public function scopeVolunteerFilter($query, $filter)
    {
        if ($filter === 'is_volunteer') {
            return $query->whereNotNull('red_cross_unit_id');
        }
        if ($filter === 'wants_volunteer') {
            return $query->where('can_contribute_volunteering', true)
                ->whereNull('red_cross_unit_id');
        }
        if ($filter === 'wants_member') {
            return $query->where('can_contribute_member', true);
        }

        return $query;
    }

    public function lastActivityAt(): ?Carbon
    {
        return $this->last_activity_at
            ? Carbon::parse($this->last_activity_at)
            : null;
    }

    public function lastLoginAt(): ?Carbon
    {
        return $this->last_login_at
            ? Carbon::parse($this->last_login_at)
            : null;
    }

    public function isDigitallyDormant(int $months = 6): bool
    {
        if (! $this->last_login_at) {
            return true; // Never logged in = digitally dormant
        }

        return $this->last_login_at->lt(now()->subMonths($months));
    }

    public function isOperationallyDormant(int $months = 12): bool
    {
        if (! $this->last_activity_at) {
            return true; // Never participated = operationally dormant
        }

        return $this->last_activity_at->lt(now()->subMonths($months));
    }

    public function isAdministrativelyDormant(int $months = 6): bool
    {
        if (! $this->last_admin_activity_at) {
            return true; // Never administered anything
        }

        return $this->last_admin_activity_at->lt(now()->subMonths($months));
    }

    public function scopeDormancyFilter($query, ?string $filter)
    {
        if (! $filter) {
            return $query;
        }

        $now = now();

        switch ($filter) {
            case 'digital_dormant':
                return $query->where(function ($q) use ($now) {
                    $q->whereNull('last_login_at')
                        ->orWhere('last_login_at', '<', $now->copy()->subMonths(6));
                });

            case 'operational_dormant':
                return $query->where(function ($q) use ($now) {
                    $q->whereNull('last_activity_at')
                        ->orWhere('last_activity_at', '<', $now->copy()->subMonths(12));
                });

            case 'admin_dormant':
                return $query->where(function ($q) use ($now) {
                    $q->whereNull('last_admin_activity_at')
                        ->orWhere('last_admin_activity_at', '<', $now->copy()->subMonths(6));
                });

            case 'fully_dormant':
                return $query
                    ->where(function ($q) use ($now) {
                        $q->whereNull('last_login_at')
                            ->orWhere('last_login_at', '<', $now->copy()->subMonths(6));
                    })
                    ->where(function ($q) use ($now) {
                        $q->whereNull('last_activity_at')
                            ->orWhere('last_activity_at', '<', $now->copy()->subMonths(12));
                    })
                    ->where(function ($q) use ($now) {
                        $q->whereNull('last_admin_activity_at')
                            ->orWhere('last_admin_activity_at', '<', $now->copy()->subMonths(6));
                    });

            default:
                return $query;
        }
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification);
    }

    /**
     * Helper function to format names (capitalize, remove spaces, truncate to 10 chars)
     */
    protected function formatCodeName(?string $name): string
    {
        if (empty($name)) {
            return 'UNK';
        }
        $formatted = strtoupper(str_replace(' ', '_', $name));

        return substr($formatted, 0, 12);
    }

    /**
     * Get the user ID reference in DB-{user_id}/{BRANCH_CODE}/{DIVISION_NAME}/{RED_CROSS_UNIT_NAME} format.
     */
    public function getUserIdReferenceAttribute()
    {
        $branchCode = 'UNK';
        $divisionName = 'UNK';
        $redCrossUnitName = 'NO-UNIT';

        if ($this->branch) {
            $branchCode = $this->branch->getBranchCodeForReference();
        }

        if ($this->division) {
            $divisionName = $this->formatCodeName($this->division->name);
        }

        if ($this->redCrossUnit) {
            $redCrossUnitName = $this->formatCodeName($this->redCrossUnit->name);
        }

        return "DB-{$this->id}/{$branchCode}/{$divisionName}/{$redCrossUnitName}";
    }

    /**
     * Get the short user ID reference: DB-{id}/{BRANCH_CODE}
     */
    public function getUserIdReferenceShortAttribute()
    {
        $branchCode = $this->branch
            ? $this->branch->getBranchCodeForReference()
            : 'UNK';

        $divisionName = $this->division
            ? $this->formatCodeName($this->division->name)
            : 'UNK';

        return "DB-{$this->id}-{$branchCode}-{$divisionName}";
    }

    public function getUserIdReferenceLinkAttribute(): string
    {
        $branchCode = $this->branch
            ? $this->branch->getBranchCodeForReference()
            : 'UNK';

        $divisionName = $this->division
            ? $this->formatCodeName($this->division->name)
            : 'UNK';

        $reference = "DB-{$this->id}-{$branchCode}-{$divisionName}";
        $url = route('users.show', $this->id);

        return "<a href=\"{$url}\" class=\"db-code underline\">{$reference}</a>";
    }

    /**
     * Get the access level of the user based on their roles.
     *
     * @return string 'national', 'branch', 'division', or 'none'
     */
    public function getAccessLevel(): string
    {
        if ($this->hasAnyRole(self::NATIONAL_ROLES)) {
            return 'national';
        }
        if ($this->hasAnyRole(self::BRANCH_ROLES)) {
            return 'branch';
        }
        if ($this->hasAnyRole(self::DIVISION_ROLES)) {
            return 'division';
        }

        return 'none';
    }

    /**
     * Get the specific ID (branch or division) that the user's access is scoped to.
     *
     * @return int|null The ID of the branch or division, or null for national users.
     */
    public function getScopedId(): ?int
    {
        $accessLevel = $this->getAccessLevel();

        if ($accessLevel === 'branch') {
            return $this->branch_id;
        }
        if ($accessLevel === 'division') {
            return $this->division_id;
        }

        return null;
    }

    /**
     * Get the branch ID that the user's access level ultimately resolves to.
     *
     * Division-level users are automatically mapped to the branch that their
     * division belongs to, while branch-level users return their own branch ID.
     * National-level users have no scoped branch and therefore return null.
     *
     * @return int|null The resolved branch ID, or null for national users.
     */
    public function getScopedBranchId(): ?int
    {
        $accessLevel = $this->getAccessLevel();

        if ($accessLevel === 'branch') {
            return $this->branch_id;
        }

        if ($accessLevel === 'division') {
            return $this->division->branch_id ?? null;
        }

        return null;
    }

    /**
     * Check if the current user can view another user's profile based on access hierarchy.
     *
     * @param  User  $viewer  The user trying to view the profile.
     */
    public function isViewableBy(User $viewer): bool
    {
        return Gate::forUser($viewer)->allows('view', $this);

    }

    /**
     * Get a description of the user's search scope for display.
     * e.g., "in [Branch Name] Branch" or "in [Division Name] Division".
     */
    public function getSearchScopeDescriptionAttribute(): string
    {
        $accessLevel = $this->getAccessLevel();

        switch ($accessLevel) {
            case 'national':
                return 'Nigeria';
            case 'branch':
                if ($this->branch) {
                    return $this->branch->name;
                }
                break;
            case 'division':
                if ($this->division && $this->division->branch) {
                    return $this->division->branch->getBranchCodeForReference().'/'.$this->division->name;
                }
                break;

        }

        return ''; // National or no scope returns an empty string
    }

    /**
     * Get the user's own most-specific scope name, e.g. "Lagos" or "Alimosho".
     */
    public function getScopePathAttribute(): string
    {
        return match ($this->getAccessLevel()) {
            'national' => 'National',
            'branch' => $this->branch?->name ?? '—',
            'division' => $this->division?->name ?? '—',
            default => '—',
        };
    }

    // Relationship to IdCardPrint
    public function idCardPrints()
    {
        return $this->hasMany(IdCardPrint::class);
    }

    // Accessor for the latest ID card's expiry date
    public function getLatestIdCardExpiryDateAttribute()
    {
        return $this->idCardPrints()->latest('printed_at')->first()?->expiry_date;
    }

    /**
     * Check if the ID card was included in the latest valid membership payment.
     *
     * @return bool
     */
    public function hasPaidForIdCard()
    {
        // Get the latest valid (non-expired and not deleted) membership payment
        $latestValidPayment = $this->activeMembershipPayments()
            ->valid() // Use the scope to filter for valid payments
            ->latest('expiry_date') // Order by expiry_date descending to get the "latest valid"
            ->first();

        // If a valid payment exists, check if the ID card was included
        if ($latestValidPayment) {
            return (bool) $latestValidPayment->id_card_included;
        }

        // If no valid payments are found, then the card hasn't been paid for.
        return false;
    }

    /**
     * Check if the user currently has a valid (non-expired) printed ID card.
     */
    public function hasValidIdCardPrinted(): bool
    {
        // Get the latest ID card print for the user that is not soft-deleted
        $latestPrint = $this->idCardPrints()
            ->latest('printed_at')
            ->first();

        // An ID card is considered valid if it exists and its expiry_date is in the future
        return (bool) ($latestPrint && $latestPrint->expiry_date && $latestPrint->expiry_date->isFuture());
    }

    public function getIdCardLagDays(): ?int
    {
        // If the controller pre-calculated this value, use it for efficiency.
        if (array_key_exists('id_card_lag_days', $this->attributes)) {
            $lag = (int) $this->attributes['id_card_lag_days'];

            return $lag > 0 ? $lag : 0;
        }

        // Fallback calculation if not present in the query (e.g., when accessed on a single model instance).
        $lastPaymentDate = $this->last_id_card_payment_date;

        if (! $lastPaymentDate) {
            return null; // No payment, no lag.
        }

        $lastPrintDate = $this->idCardPrints()->latest('printed_at')->value('printed_at');

        if (! $lastPrintDate) {
            return -999; // Payment exists, but no print. High lag to prioritize.
        }

        $paymentDate = Carbon::parse($lastPaymentDate)->startOfDay();
        $printDate = Carbon::parse($lastPrintDate)->startOfDay();

        if ($paymentDate->gt($printDate)) {
            return $paymentDate->diffInDays($printDate);
        }

        return 0; // Print is up-to-date.
    }

    /**
     * Determines if a user needs a new ID card printed based on payment and print history.
     */
    public function needsIdCardPrinted(): bool
    {
        $lagDays = -$this->getIdCardLagDays();

        // If lagDays is null, it means no payment, so no print needed yet.
        if (is_null($lagDays)) {
            return false;
        }

        // If lagDays > 0, it means the last print was before the last payment, or no print at all,
        // so a print is needed.
        return $lagDays > 0;
    }

    /**
     * Get the roles that the current user is authorized to assign.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAssignableRoles()
    {
        // Get all permissions for the current user that start with 'authorize_'
        $authorizationPermissions = $this->getAllPermissions()
            ->filter(function ($permission) {
                return Str::startsWith($permission->name, 'authorize_');
            });

        // Extract the role names from these permissions
        $assignableRoleNames = $authorizationPermissions->map(function ($permission) {
            // e.g., 'authorize_branch_secretary' becomes 'branch_secretary'
            return Str::after($permission->name, 'authorize_');
        })->toArray();

        // Fetch the corresponding Role models
        $assignableRoles = Role::whereIn('name', $assignableRoleNames)->orderBy('name')->get();

        return $assignableRoles;
    }

    /**
     * Get the last payment date for an ID card for the user.
     *
     * @return \Carbon\Carbon|null
     */
    public function getLastIdCardPaymentDateAttribute()
    {
        // Get the latest membership payment where 'id_card_included' is true
        $latestIdCardPayment = $this->membershipPayments()
            ->where('id_card_included', true)
            ->latest('payment_date')
            ->first();

        return $latestIdCardPayment ? $latestIdCardPayment->payment_date : null;
    }

    /**
     * Get the last printed date for an ID card for the user.
     *
     * @return \Carbon\Carbon|null
     */
    public function getLastIdCardPrintDateAttribute()
    {
        // Get the latest IdCardPrint record for this user
        $latestIdCardPrint = $this->idCardPrints()->latest('printed_at')->first();

        return $latestIdCardPrint ? $latestIdCardPrint->printed_at : null;
    }

    /**
     * Get the user's age from birth year.
     */
    public function getAgeAttribute(): ?int
    {
        if (! $this->birth_year) {
            return null;
        }

        return Carbon::now()->year - $this->birth_year;
    }

    public function scopeAwaitingEngagement($query)
    {
        return $query->where('lifecycle_status', 'pending_engagement');
    }

    public function scopeActive($query)
    {
        return $query->where('lifecycle_status', 'active');
    }

    public function scopeDormant($query)
    {
        return $query->where('lifecycle_status', 'dormant');
    }

    public function scopeArchived($query)
    {
        return $query->where('lifecycle_status', 'archived');
    }

    /**
     * Structural member definition: has a valid membership payment
     * and is not attached to a Red Cross unit. No lifecycle filter —
     * compose with lifecycle scopes or archived_filter as needed.
     */
    public function scopeHasValidMembership(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('red_cross_unit_id')
            ->whereHas('membershipPayments', fn ($q) => $q->valid()->personal());
    }

    /**
     * Canonical "member" for statistics and reports:
     * structural definition + lifecycle active or dormant.
     */
    public function scopeMembers(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->hasValidMembership()
            ->whereIn('lifecycle_status', self::OPERATIONAL_STATUSES);
    }

    /**
     * Canonical "volunteer": attached to an ACTIVE Red Cross unit,
     * lifecycle active or dormant.
     */
    public function scopeVolunteers(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('lifecycle_status', self::OPERATIONAL_STATUSES)
            ->whereNotNull('red_cross_unit_id')
            ->whereHas('redCrossUnit', fn ($q) => $q->where('is_active', true));
    }

    /**
     * "Unassigned ghost": was once assigned to a Red Cross unit (assigned_rcu_date
     * set) but no longer has one, and holds no current personal membership payment
     * on a genuine (non-volunteer) fee. A payment on a volunteer-associated fee
     * (is_volunteer_fee=true, e.g. "Detachment") does not count as membership
     * evidence — it's a leftover of their volunteer history, not real dues.
     */
    public function scopeUnassignedGhost(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('assigned_rcu_date')
            ->whereNull('red_cross_unit_id')
            ->whereDoesntHave('currentMembershipPayment', fn ($q) => $q->personal()
                ->whereHas('membershipFee', fn ($q2) => $q2->where('is_volunteer_fee', false)));
    }

    public function scopeSelectableForEntry($query)
    {
        return $query->where(function ($q) {
            $q->where('is_super_admin', false)
                ->orWhereNull('is_super_admin');
        })->whereDoesntHave('roles', function ($q) {
            $q->where('name', 'super-admin');
        });
    }

    public function getLifecycleStatusLabelAttribute(): string
    {
        return match ($this->lifecycle_status) {
            'pending_engagement' => 'Pending Engagement',
            'active' => 'Life-cycle: Active',
            'dormant' => 'Life-cycle: Dormant',
            'archived' => 'Life-cycle: Archived',
            default => 'Life-cycle: Unknown',
        };
    }

    /**
     * Mark this user as active and update their last activity timestamp.
     */
    public function markActive(): void
    {
        $this->forceFill([
            'last_activity_at' => now(),
            'lifecycle_status' => 'active',
        ]);

        \Log::debug('User markActive() – before save', [
            'user_id' => $this->id,
            'dirty' => $this->getDirty(),
            'original_lifecycle' => $this->getOriginal('lifecycle_status'),
        ]);

        $this->save();
    }

    /**
     * Forward-bump the denormalized latest first-aid date.
     *
     * Sets last_first_aid_at to $date only when $date is newer than the current value (or the
     * current value is null). It NEVER lowers the value — lowering on edit/delete/reassignment
     * is the job of the nightly firstaid:recalculate full recompute. Accepts a Carbon or any
     * parseable date; date-grained comparison only.
     */
    public function bumpLastFirstAidAt($date): void
    {
        if (is_null($date)) {
            return;
        }

        $date = $date instanceof \Carbon\Carbon
            ? $date->copy()->startOfDay()
            : \Carbon\Carbon::parse($date)->startOfDay();

        if (is_null($this->last_first_aid_at) || $date->gt($this->last_first_aid_at)) {
            $this->last_first_aid_at = $date->toDateString();
            $this->save();
        }
    }

    /**
     * Authoritatively recompute the denormalized latest first-aid date.
     *
     * last_first_aid_at = MAX(training_date) over this user's trainings WHERE is_deleted = false
     * AND the training's training_type has is_first_aid = true; NULL when none qualify. Unlike
     * bumpLastFirstAidAt(), this is the source of truth: it can move the date DOWN or to NULL,
     * not only forward. Single aggregate query (correlated subquery on the FA flag).
     */
    public function recalculateLastFirstAidAt(): void
    {
        $latest = $this->trainings()
            ->where('is_deleted', false)
            ->whereHas('trainingType', fn ($q) => $q->where('is_first_aid', true))
            ->max('training_date');

        $newValue = $latest ? \Carbon\Carbon::parse($latest)->toDateString() : null;

        if (optional($this->last_first_aid_at)->toDateString() !== $newValue) {
            $this->last_first_aid_at = $newValue;
            $this->save();
        }
    }

    /**
     * Human-readable time since the user's most recent first-aid training, e.g. "2 years, 3 months".
     * Returns null when there is no first-aid record on file (callers decide the fallback text).
     */
    public function timeSinceLastFirstAid(): ?string
    {
        if (is_null($this->last_first_aid_at)) {
            return null;
        }

        $from = $this->last_first_aid_at->copy()->startOfDay();
        $now = now()->startOfDay();

        if ($from->greaterThanOrEqualTo($now)) {
            return 'less than a month';
        }

        $interval = $from->diff($now);   // DateInterval — version-stable y/m
        $years = $interval->y;
        $months = $interval->m;

        $parts = [];
        if ($years > 0) {
            $parts[] = $years.' '.\Illuminate\Support\Str::plural('year', $years);
        }
        if ($months > 0) {
            $parts[] = $months.' '.\Illuminate\Support\Str::plural('month', $months);
        }

        return $parts ? implode(', ', $parts) : 'less than a month';
    }

    /**
     * Re-evaluate this user's lifecycle status after a record is deleted.
     * Uses the same dormancy logic as the UpdateUserLifecycleFromActivity command,
     * but applied to a single user instead of a batch.
     *
     * First recalculates last_activity_at from actual remaining non-deleted records,
     * so that deleting the record that triggered markActive() is properly reflected.
     */
    public function recalculateLifecycle(): void
    {
        if ($this->lifecycle_status !== 'active') {
            return;
        }

        // Derive the true last activity date from remaining non-deleted records.
        $lastActivity = $this->activities()
            ->where('is_deleted', false)
            ->max('date');

        $lastTraining = $this->trainings()
            ->where('is_deleted', false)
            ->max('training_date');

        $lastPayment = $this->membershipPayments()
            ->where('is_deleted', false)
            ->max('payment_date');

        $lastDonation = $this->donations()
            ->whereNull('removed_date')
            ->max('date_donation');

        $trueLastActivity = collect([$lastActivity, $lastTraining, $lastPayment, $lastDonation])
            ->filter()
            ->map(fn ($d) => Carbon::parse($d))
            ->max();

        $this->forceFill(['last_activity_at' => $trueLastActivity])->save();

        // Demotion decision delegated to the single source of truth
        // (members keyed on membership validity; volunteers/neither on inactivity).
        if ($this->isDormantByPolicy()) {
            $this->update(['lifecycle_status' => 'dormant']);
        }
    }

    /**
     * Instance-level mirror of scopeUnassignedGhost(), for single-record checks
     * (e.g. badges) where building a scoped query isn't otherwise needed.
     */
    public function isUnassignedGhost(): bool
    {
        if (is_null($this->assigned_rcu_date) || ! is_null($this->red_cross_unit_id)) {
            return false;
        }

        return ! $this->currentMembershipPayment()
            ->personal()
            ->whereHas('membershipFee', fn ($q) => $q->where('is_volunteer_fee', false))
            ->exists();
    }

    /**
     * Classify a user for the dormancy policy: 'volunteer' (in a Red Cross unit),
     * 'unassigned' (left their unit, no genuine membership payment to fall back on),
     * 'member' (no unit but has current membership), or 'neither'.
     * Volunteer status takes precedence, then unassigned-ghost, then membership.
     */
    public function lifecyclePolicyType(): string
    {
        if ($this->red_cross_unit_id !== null) {
            return 'volunteer';
        }
        if ($this->isUnassignedGhost()) {
            return 'unassigned';
        }
        if ($this->currentMembershipPayment()->personal()->exists()) {
            return 'member';
        }

        return 'neither';
    }

    /**
     * Whether this user should be dormant under the dormancy policy.
     * Volunteers, "unassigned" ghosts, and "neither" use the inactivity threshold
     * (membership.dormant_after_months); members are dormant once they hold no
     * current valid membership.
     * Callers apply this only to active/dormant users.
     */
    public function isDormantByPolicy(): bool
    {
        if ($this->lifecyclePolicyType() === 'member') {
            return ! $this->currentMembershipPayment()->personal()->exists();
        }

        $months = Setting::getInt('membership.dormant_after_months', 12);

        return is_null($this->last_activity_at)
            || $this->last_activity_at->lt(now()->subMonths($months));
    }

    /**
     * Update only this user's last_activity_at (no lifecycle change).
     */
    public function touchLastActivity(): void
    {
        $this->forceFill([
            'last_activity_at' => now(),
        ])->save();
    }

    public function touchLastAdminActivity(): void
    {
        $this->forceFill([
            'last_admin_activity_at' => now(),
        ])->save();
    }

    public function formRegistrar()
    {
        return $this->belongsTo(User::class, 'form_reg_id');
    }

    public function campaignRecipients()
    {
        return $this->morphMany(\App\Models\MessagingRecipient::class, 'recipient');
    }

    public function latestFirstAidTraining(): ?Training
    {
        return Training::where('user_id', $this->id)
            ->where('is_deleted', false)
            ->whereHas('trainingType', fn ($q) => $q->where('is_first_aid', true))
            ->with('trainingType:id,name')
            ->orderByDesc('training_date')
            ->first();
    }
}
