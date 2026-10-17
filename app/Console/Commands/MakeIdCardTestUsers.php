<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\IdCardPrint;
use App\Models\IdCardPrintRequest;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/**
 * Dev-only: create (or reset) four fully printable test members for trying
 * the bulk ID card page by hand — Mark as ready to print, printing, and
 * rejecting photos/signatures. Refuses to run unless APP_ENV is local or
 * testing. Safe to run again:
 * each run puts the four users back in the same state (no prints, no
 * ready marks, the two rejections set). --remove deletes them.
 *
 * Users are found by their @printtest.local email; search "Printtest" on
 * the bulk print page. Images are generated placeholders, not real photos.
 */
class MakeIdCardTestUsers extends Command
{
    protected $signature = 'idcards:make-test-users
                            {--branch=FCT : Branch name}
                            {--division=Abuja Municipal : Division for the "division" users}
                            {--other-division=Bwari : Another division in the same branch, for the "branch" users}
                            {--unit= : Red Cross unit ID; test users in that unit\'s division join it (for /my-unit/report)}
                            {--remove : Delete the test users instead}';

    protected $description = 'Local/testing only: create or reset 4 printable test members for the bulk ID card page';

    private const EMAIL_DOMAIN = 'printtest.local';

    /** last_name => [email key, which division option, rejection] */
    private const USERS = [
        'Branchcard' => ['branch', 'other', null],
        'Divisioncard' => ['division', 'division', null],
        'Rejectedphoto' => ['rejected-photo', 'division', 'picture'],
        'Rejectedsignature' => ['rejected-signature', 'other', 'signature'],
    ];

    /** Fixed, obviously fake 11-digit NINs (999... prefix). */
    private const NINS = [
        'branch' => '99900000001',
        'division' => '99900000002',
        'rejected-photo' => '99900000003',
        'rejected-signature' => '99900000004',
    ];

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Refusing to run: APP_ENV is "'.app()->environment().'".');
            $this->line('This command creates and deletes test users, so it only runs when APP_ENV is "local" or "testing". Nothing was changed.');

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            return $this->remove();
        }

        $division = Division::with('branch')->where('name', $this->option('division'))
            ->whereHas('branch', fn ($q) => $q->where('name', $this->option('branch')))->first();
        $otherDivision = Division::where('name', $this->option('other-division'))
            ->where('branch_id', $division?->branch_id)->first();

        if (! $division || ! $otherDivision) {
            $this->error('Branch or division not found. Check --branch, --division and --other-division.');

            return self::FAILURE;
        }

        $fee = MembershipFee::where('is_volunteer_fee', false)->orderBy('amount')->first();
        $rejectedBy = User::role('national_db_administrator')->orderBy('id')->first();
        if (! $fee || ! $rejectedBy) {
            $this->error('Need a non-volunteer membership fee and a national_db_administrator.');

            return self::FAILURE;
        }

        $unit = null;
        if ($this->option('unit')) {
            $unit = RedCrossUnit::find($this->option('unit'));
            if (! $unit || ! in_array((int) $unit->division_id, [$division->id, $otherDivision->id], true)) {
                $this->error("Red Cross unit {$this->option('unit')} not found in {$division->name} or {$otherDivision->name}.");

                return self::FAILURE;
            }
        }

        $rows = [];
        foreach (self::USERS as $lastName => [$key, $divisionChoice, $rejection]) {
            $target = $divisionChoice === 'division' ? $division : $otherDivision;
            // Only users in the unit's own division join it, so unit and division agree.
            $userUnit = $unit && (int) $unit->division_id === $target->id ? $unit : null;
            $user = $this->resetUser($key, $lastName, $target, $fee, $rejectedBy, $userUnit);

            if ($rejection) {
                // Second save: User::booted() clears a rejection whenever the
                // image column is dirty, as it was in resetUser().
                $user->forceFill([
                    $rejection.'_rejected_at' => now(),
                    $rejection.'_rejected_by_id' => $rejectedBy->id,
                    $rejection.'_rejection_reason' => $rejection === 'picture' ? 'dark_or_blurry' : 'too_faint',
                    $rejection.'_rejection_note' => 'Test rejection from idcards:make-test-users',
                ])->save();
            }

            $rows[] = [$user->id, $user->full_name, $user->email, $target->name, $userUnit?->name ?? '—', $rejection ? "{$rejection} rejected" : 'printable'];
        }

        $this->table(['ID', 'Name', 'Email', 'Division', 'Unit', 'State'], $rows);
        if ($unit) {
            $this->line("Unit {$unit->name} (#{$unit->id}): view /my-unit/report as one of its members, or /red-cross-units/{$unit->id}.");
        }
        $this->line("Branch: {$division->branch->name}. Search \"Printtest\" on /id-cards/prepare-bulk-print.");
        $this->line('Log in as a test member: php artisan users:reset-password <ID>');
        $this->line('Admins to test with (set a password the same way):');
        $this->table(['ID', 'Name', 'Role', 'Sees'], [
            [$rejectedBy->id, $rejectedBy->full_name, 'national_db_administrator', 'all 4 (can print)'],
            ...$this->adminsFor($division),
        ]);

        return self::SUCCESS;
    }

    private function resetUser(string $key, string $lastName, Division $division, MembershipFee $fee, User $submittedBy, ?RedCrossUnit $unit = null): User
    {
        $email = "{$key}@".self::EMAIL_DOMAIN;
        $user = User::where('email', $email)->first() ?? new User(['email' => $email]);

        $user->forceFill([
            'first_name' => 'Printtest',
            'last_name' => $lastName,
            'gender' => 'female',
            'branch_id' => $division->branch_id,
            'division_id' => $division->id,
            'red_cross_unit_id' => $unit?->id,
            'national_id_number' => self::NINS[$key],
            'picture' => $this->writeImage('profile', "printtest-{$key}-photo.jpg", 300, 360, [200, 225, 245], 'TEST PHOTO'),
            'signature' => $this->writeImage('signatures', "printtest-{$key}-sig.png", 400, 150, [255, 255, 255], 'Test Signature'),
            'image_upload_date' => now(),
            'lifecycle_status' => 'active',
            'email_verified_at' => now(),
            'password' => $user->exists ? $user->password : bin2hex(random_bytes(16)),
        ])->save();

        // Back to a clean slate: no prints, no marks, one current ID card payment.
        $user->clearImageRejection('picture');
        $user->clearImageRejection('signature');
        $user->save();
        IdCardPrintRequest::where('user_id', $user->id)->delete();
        IdCardPrint::withTrashed()->where('user_id', $user->id)->forceDelete();
        MembershipPayment::withoutGlobalScopes()->where('user_id', $user->id)->delete();

        (new MembershipPayment)->forceFill([
            'user_id' => $user->id,
            'membership_fee_id' => $fee->id,
            'branch_id' => $division->branch_id,
            'division_id' => $division->id,
            'payment_date' => now()->subWeek()->toDateString(),
            'expiry_date' => now()->subWeek()->addYears($fee->validity_years ?: 1)->toDateString(),
            'id_card_included' => true,
            'is_deleted' => false,
            'submitted_by_user_id' => $submittedBy->id,
            'submitted_at' => now(),
            'approval_status' => MembershipPayment::APPROVED,
            'decided_by_user_id' => $submittedBy->id,
            'decided_at' => now(),
        ])->save();

        return $user->fresh();
    }

    /**
     * A plain generated image in the same place the upload code stores one
     * (original + web), so PhotoController serves it. Returns the bare
     * filename stored in users.picture / users.signature.
     */
    private function writeImage(string $category, string $filename, int $width, int $height, array $rgb, string $label): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        $ink = imagecolorallocate($image, 40, 40, 80);
        imagerectangle($image, 2, 2, $width - 3, $height - 3, $ink);
        imagestring($image, 5, (int) (($width - strlen($label) * 9) / 2), (int) ($height / 2 - 8), $label, $ink);

        ob_start();
        str_ends_with($filename, '.png') ? imagepng($image) : imagejpeg($image, null, 85);
        $bytes = ob_get_clean();
        imagedestroy($image);

        foreach (['original', 'web'] as $variant) {
            Storage::disk('local')->put("photos/{$category}/{$variant}/{$filename}", $bytes);
        }

        return $filename;
    }

    /** @return array<int, array<int, string>> */
    private function adminsFor(Division $division): array
    {
        $branchRoles = ['branch_secretary', 'branch_db_administrator', 'branch_db_assistant'];
        $divisionRoles = ['division_db_assistant_operations', 'division_db_assistant_finance'];

        // Only roles that exist here: User::role() throws on an unknown role.
        $pick = function (array $roles, string $column, int $id, string $sees) {
            $roles = Role::whereIn('name', $roles)->where('guard_name', 'web')->pluck('name')->all();
            if ($roles === []) {
                return collect();
            }

            return User::role($roles)->where($column, $id)->with('roles')->orderBy('id')->limit(2)->get()
                ->map(fn (User $u) => [$u->id, $u->full_name, $u->roles->pluck('name')->intersect($roles)->implode(', '), $sees]);
        };

        return [
            ...$pick($branchRoles, 'branch_id', $division->branch_id, 'all 4 (marks ready)'),
            ...$pick($divisionRoles, 'division_id', $division->id, "{$division->name} only (2)"),
        ];
    }

    /** @return array<int, string> */
    private function testEmails(): array
    {
        return array_map(fn (string $key) => "{$key}@".self::EMAIL_DOMAIN, array_keys(self::NINS));
    }

    private function remove(): int
    {
        // Exactly the four emails this command creates — never a pattern match.
        $users = User::whereIn('email', $this->testEmails())->get();

        foreach ($users as $user) {
            IdCardPrintRequest::where('user_id', $user->id)->delete();
            IdCardPrint::withTrashed()->where('user_id', $user->id)->forceDelete();
            MembershipPayment::withoutGlobalScopes()->where('user_id', $user->id)->delete();
            $user->delete();
        }

        // Known filenames only — the photo folders hold tens of thousands of files.
        foreach (array_keys(self::NINS) as $key) {
            foreach (['original', 'web'] as $variant) {
                Storage::disk('local')->delete([
                    "photos/profile/{$variant}/printtest-{$key}-photo.jpg",
                    "photos/signatures/{$variant}/printtest-{$key}-sig.png",
                ]);
            }
        }

        $this->info("Removed {$users->count()} test users.");

        return self::SUCCESS;
    }
}
