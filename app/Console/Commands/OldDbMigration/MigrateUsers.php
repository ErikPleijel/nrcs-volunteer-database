<?php



namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MigrateUsers extends Command
{
    protected $signature = 'migrate:users
                            {--limit=0 : Limit number of records (0 for no limit)}
                            {--clear : Clear existing data before migration}';

    protected $description = 'Migrate users from old database to new structure';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $clear = (bool) $this->option('clear');

        $this->info('Starting user migration...');

        // Clear existing data if requested
        if ($clear && $this->confirm('Clear existing users table?', true)) {
            $this->info('Clearing existing users data...');
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('users')->truncate();
            $this->info('Cleared existing users table');
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        // Get old database name from config
        $oldDbName = config('database.connections.old_db.database');

        // Construct the LIMIT clause conditionally
        $limitClause = ($limit > 0) ? "LIMIT {$limit}" : "";

        // Get records with updated filters and role information
        // âœ… Added title join: persons.TitleID -> titles.Title
        $records = DB::select("
            SELECT
                p.PersonID,
                p.FirstName,
                p.LastName,
                p.Other_Names,
                p.Email,
                p.Password,
                p.Gender,
                p.Year_of_birth,
                p.MaritalStatus,
                p.Organisation,
                p.Occupation,
                p.Residential_address,
                p.Workplace_address,
                p.Telephone1,
                p.Telephone2,
                p.Disciplin,
                p.BranchID,
                p.DivisionID,
                p.RedCrossUnitID,
                p.Inactive,
                p.AccountActivated,
                p.Public_contact,
                p.Public_contacts_position,
                p.Personal_info,
                p.Contribute_volunteering,
                p.Contribute_donor,
                p.Contribute_member,
                p.Picture,
                p.PicConfirm,
                p.ImageUploadDate,
                p.ImageUploadID,
                p.Signature,
                p.SignConfirm,
                p.IDcard_timestamp,
                p.IDCardValidYears,
                p.Lastlogin,
                p.LastActivity,
                p.FormRegID,
                p.FormRegistration,
                p.DeactivatedDate,
                p.DeactivateID,
                p.AssignRcuDate,
                p.AssignRcuID,
                p.PositionID,
                p.PositionGeoLevel,
                p.Timestamp,
                p.IDNo,
                a.AuthorizationName,
                t.Title AS TitleText
            FROM {$oldDbName}.persons p
            LEFT JOIN {$oldDbName}.authorizationnames a ON p.Auth_Manager = a.Auth_number
            LEFT JOIN {$oldDbName}.titles t ON p.TitleID = t.TitleID
            WHERE p.IsOrganisation = 0
              AND p.AccountActivated = 1
              AND p.Inactive = 0
              AND (
                    (p.FirstName IS NOT NULL AND TRIM(p.FirstName) != '')
                 OR (p.LastName  IS NOT NULL AND TRIM(p.LastName)  != '')
              )
            ORDER BY p.PersonID
            {$limitClause}
        ");

        if (empty($records)) {
            $this->warn('No records found to migrate');
            return Command::SUCCESS;
        }

        $this->info('Found ' . count($records) . ' records to migrate');

        $totalRecords = count($records); // For progress display
        $now = Carbon::now();
        $hashedPassword = ''; // Intentionally blank; legacy hashes stored separately
        $inserted = 0;
        $errors = 0;

        // Email statistics tracking
        $emailStats = [
            'valid_emails' => 0,
            'invalid_emails_moved_to_user_code' => 0,
            'empty_emails' => 0,
        ];
        $invalidEmailExamples = [];

        // Role statistics tracking
        $roleStats = [];

        // Disable foreign key checks to allow custom IDs for inserts
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($records as $record) {
            try {
                // Process names
                $firstName  = !empty($record->FirstName) ? trim($record->FirstName) : 'Unknown';
                $lastName   = !empty($record->LastName) ? trim($record->LastName) : 'Unknown';
                $middleName = !empty($record->Other_Names) ? trim($record->Other_Names) : null;

                // âœ… Process title from joined titles table
                $title = null;
                if (!empty($record->TitleText)) {
                    $t = trim((string) $record->TitleText);
                    $title = $t !== '' ? $t : null;
                }

                // Process legacy password
                $legacyPasswordHash = null;
                if (!empty($record->Password) && trim($record->Password) !== '') {
                    $legacyPasswordHash = trim($record->Password);
                }

                // Process email and user_code:
                // If email exists and is valid: email set, user_code = null
                // Otherwise: email = null, user_code = PersonID
                $email = null;
                $userCode = null;

                $rawEmail = $record->Email ? trim($record->Email) : null;

                if (!empty($rawEmail) && filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
                    $email = strtolower($rawEmail);
                    $userCode = null;
                    $emailStats['valid_emails']++;
                } else {
                    $email = null;
                    $userCode = $record->PersonID;

                    if (!empty($rawEmail)) {
                        $emailStats['invalid_emails_moved_to_user_code']++;

                        if (count($invalidEmailExamples) < 10) {
                            $invalidEmailExamples[] = $rawEmail;
                        }
                    } else {
                        $emailStats['empty_emails']++;
                    }
                }

                // Process legacy role
                $legacyRole = null;
                if (!empty($record->AuthorizationName)) {
                    $legacyRole = trim($record->AuthorizationName);
                    if (!isset($roleStats[$legacyRole])) {
                        $roleStats[$legacyRole] = 0;
                    }
                    $roleStats[$legacyRole]++;
                }

                // Process gender
                $gender = null;
                if (!empty($record->Gender)) {
                    $genderLower = strtolower(trim($record->Gender));
                    if (in_array($genderLower, ['m', 'male', '1'], true)) {
                        $gender = 'male';
                    } elseif (in_array($genderLower, ['f', 'female', '2'], true)) {
                        $gender = 'female';
                    } else {
                        $gender = 'other';
                    }
                }

                // Process birth year
                $birthYear = null;
                if (!empty($record->Year_of_birth) && is_numeric($record->Year_of_birth)) {
                    $year = (int) $record->Year_of_birth;
                    if ($year >= 1900 && $year <= (int) date('Y')) {
                        $birthYear = $year;
                    }
                }

                // Process marital status
                $maritalStatus = null;
                if (!empty($record->MaritalStatus)) {
                    $maritalLower = strtolower(trim($record->MaritalStatus));
                    if (in_array($maritalLower, ['single', 'married'], true)) {
                        $maritalStatus = $maritalLower;
                    } else {
                        $maritalStatus = 'other';
                    }
                }

                // Process dates
                $lastLogin = null;
                if (!empty($record->Lastlogin) && $record->Lastlogin !== '0000-00-00 00:00:00') {
                    try {
                        $lastLogin = Carbon::parse($record->Lastlogin);
                    } catch (\Exception $e) {
                        $lastLogin = null;
                    }
                }

                $lastActivity = null;
                if (!empty($record->LastActivity) && $record->LastActivity !== '0000-00-00') {
                    try {
                        $lastActivity = Carbon::parse($record->LastActivity)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $lastActivity = null;
                    }
                }

                $imageUploadDate = null;
                if (!empty($record->ImageUploadDate) && $record->ImageUploadDate !== '0000-00-00') {
                    try {
                        $imageUploadDate = Carbon::parse($record->ImageUploadDate)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $imageUploadDate = null;
                    }
                }

                $idCardTimestamp = null;
                if (!empty($record->IDcard_timestamp) && $record->IDcard_timestamp !== '0000-00-00') {
                    try {
                        $idCardTimestamp = Carbon::parse($record->IDcard_timestamp)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $idCardTimestamp = null;
                    }
                }

                $deactivatedDate = null;
                if (!empty($record->DeactivatedDate) && $record->DeactivatedDate !== '0000-00-00') {
                    try {
                        $deactivatedDate = Carbon::parse($record->DeactivatedDate)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $deactivatedDate = null;
                    }
                }

                $assignRcuDate = null;
                if (!empty($record->AssignRcuDate) && $record->AssignRcuDate !== '0000-00-00') {
                    try {
                        $assignRcuDate = Carbon::parse($record->AssignRcuDate)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $assignRcuDate = null;
                    }
                }

                // Insert user with ALL the fields
                DB::table('users')->insert([
                    'id' => $record->PersonID,
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'title' => $title, // âœ… NEW

                    'email' => $email,
                    'user_code' => $userCode,
                    'national_id_number' => null,
                    'red_cross_id_number' => !empty($record->IDNo) ? trim($record->IDNo) : null,
                    'gender' => $gender,
                    'birth_year' => $birthYear,
                    'marital_status' => $maritalStatus,
                    'organisation' => !empty($record->Organisation) ? trim($record->Organisation) : null,
                    'occupation' => !empty($record->Occupation) ? trim($record->Occupation) : null,
                    'residential_address' => !empty($record->Residential_address) ? trim($record->Residential_address) : null,
                    'workplace_address' => !empty($record->Workplace_address) ? trim($record->Workplace_address) : null,
                    'telephone1' => !empty($record->Telephone1) ? trim($record->Telephone1) : null,
                    'telephone2' => !empty($record->Telephone2) ? trim($record->Telephone2) : null,
                    'disciplin' => !empty($record->Disciplin) ? trim($record->Disciplin) : null,
                    'branch_id' => $this->normalizeId($record->BranchID),
                    'division_id' => $this->normalizeId($record->DivisionID),
                    'red_cross_unit_id' => $this->normalizeId($record->RedCrossUnitID),

                    // New fields from old database
                    'is_inactive' => (bool) ($record->Inactive ?? false),
                    'is_account_activated' => (bool) ($record->AccountActivated ?? false),
                    'is_public_contact' => $this->normalizeBool($record->Public_contact),
                    'public_contact_position' => !empty($record->Public_contacts_position) ? trim($record->Public_contacts_position) : null,
                    'personal_info' => !empty($record->Personal_info) ? Crypt::encryptString(trim($record->Personal_info)) : null,
                    'can_contribute_volunteering' => $this->normalizeBool($record->Contribute_volunteering),

                    'can_contribute_member' => $this->normalizeBool($record->Contribute_member),
                    'picture' => !empty($record->Picture) ? trim($record->Picture) : null,
                    'is_picture_confirmed' => $this->normalizeBool($record->PicConfirm),
                    'image_upload_date' => $imageUploadDate,
                    'image_upload_id' => $this->normalizeId($record->ImageUploadID),
                    'signature' => !empty($record->Signature) ? trim($record->Signature) : null,
                    'is_signature_confirmed' => $this->normalizeBool($record->SignConfirm),
                    'id_card_timestamp' => $idCardTimestamp,
                    'id_card_valid_years' => $this->normalizeSmallInt($record->IDCardValidYears),
                    'last_login_at' => $lastLogin,
                    'last_activity_at' => $lastActivity,
                    'form_reg_id' => $this->normalizeId($record->FormRegID),
                    'is_form_registration' => $this->normalizeBool($record->FormRegistration),
                    'deactivated_date' => $deactivatedDate,
                    'deactivated_by_id' => $this->normalizeId($record->DeactivateID),
                    'assigned_rcu_date' => $assignRcuDate,
                    'assigned_rcu_by_id' => $this->normalizeId($record->AssignRcuID),
                  //  'position_id' => $this->normalizeSmallInt($record->PositionID),
                    //'position_geo_level' => $this->normalizeSmallInt($record->PositionGeoLevel),
                    'custom_timestamp' => $record->Timestamp ?? $now,
                    'legacy_role' => $legacyRole,

                    'password' => $hashedPassword,
                    'legacy_password_hash' => $legacyPasswordHash,
                    'created_at' => $record->Timestamp ?? $now,
                    'updated_at' => $now,
                    'email_verified_at' => ($record->AccountActivated ?? false) ? ($record->Timestamp ?? $now) : null,
                ]);

                $inserted++;

                // Progress output every 500 records
                if ($inserted % 500 === 0) {
                    $percent = round(($inserted / $totalRecords) * 100, 1);
                    $this->info("âž¡ï¸  {$inserted} / {$totalRecords} accounts migrated ({$percent}%)...");
                }
            } catch (\Exception $e) {
                $errors++;
                $this->warn("Failed to insert PersonID {$record->PersonID}: " . $e->getMessage());
            }
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Reconcile division/branch consistency: legacy data may contain users whose
        // division_id belongs to a different branch than their branch_id. Log and null
        // those division_ids so the imported data is left consistent.
        $mismatches = DB::table('users')
            ->join('divisions', 'divisions.id', '=', 'users.division_id')
            ->whereColumn('divisions.branch_id', '!=', 'users.branch_id')
            ->select('users.id', 'users.branch_id', 'users.division_id',
                     'divisions.branch_id as division_branch_id')
            ->get();

        if ($mismatches->isNotEmpty()) {
            $this->warn("Found {$mismatches->count()} users with division/branch mismatch (legacy data); nulling division_id:");
            foreach ($mismatches as $m) {
                $this->line("  user {$m->id}: division {$m->division_id} (branch {$m->division_branch_id}) != assigned branch {$m->branch_id}");
                Log::warning("MigrateUsers reconciliation: user {$m->id} division_id {$m->division_id} belongs to branch {$m->division_branch_id}, not {$m->branch_id} â€” nulled.");
            }
            DB::table('users')
                ->join('divisions', 'divisions.id', '=', 'users.division_id')
                ->whereColumn('divisions.branch_id', '!=', 'users.branch_id')
                ->update(['users.division_id' => null]);
        } else {
            $this->info('Reconciliation: no division/branch mismatches found.');
        }

        // Restore pass — recover division_ids that were nulled but are
        // now resolvable (e.g. after a re-run with corrected divisions).
        $nullUsers = DB::table('users')
            ->whereNull('division_id')
            ->whereNotNull('branch_id')
            ->get(['id', 'branch_id', 'email', 'user_code']);

        $restored = 0;
        foreach ($nullUsers as $u) {
            $p = $u->email
                ? DB::connection('old_db')->table('persons')
                    ->whereRaw('LOWER(TRIM(Email)) = ?', [strtolower($u->email)])
                    ->first()
                : DB::connection('old_db')->table('persons')
                    ->where('PersonID', $u->user_code)
                    ->first();

            if (!($p->DivisionID ?? null)) continue;

            $div = DB::table('divisions')
                ->where('id', $p->DivisionID)
                ->where('branch_id', $u->branch_id)
                ->first();

            if (!$div) continue;

            DB::table('users')
                ->where('id', $u->id)
                ->update(['division_id' => $p->DivisionID]);

            $restored++;
        }

        if ($restored > 0) {
            $this->info("Restore pass: recovered division_id for {$restored} users.");
        }

        $this->info("Migration completed!");
        $this->info("Inserted: {$inserted}");
        $this->info("Errors: {$errors}");

        // Display email statistics
        $this->info("\n--- Email Processing Statistics ---");
        $this->info("Valid emails preserved: {$emailStats['valid_emails']}");
        $this->info("Invalid emails (user_code = PersonID): {$emailStats['invalid_emails_moved_to_user_code']}");
        $this->info("Empty/null emails (user_code = PersonID): {$emailStats['empty_emails']}");

        // Calculate percentages
        $totalRecords = count($records);
        if ($totalRecords > 0) {
            $validEmailPercent = round(($emailStats['valid_emails'] / $totalRecords) * 100, 1);
            $invalidEmailPercent = round(($emailStats['invalid_emails_moved_to_user_code'] / $totalRecords) * 100, 1);
            $emptyEmailPercent = round(($emailStats['empty_emails'] / $totalRecords) * 100, 1);

            $this->info("\n--- Percentages ---");
            $this->info("Valid emails: {$validEmailPercent}%");
            $this->info("Invalid emails: {$invalidEmailPercent}%");
            $this->info("Empty emails: {$emptyEmailPercent}%");
        }

        // Show role statistics
        if (!empty($roleStats)) {
            $this->info("\n--- Role Distribution ---");
            arsort($roleStats);
            foreach ($roleStats as $role => $count) {
                $this->line("  - {$role}: {$count} users");
            }

            $usersWithoutRoles = $inserted - array_sum($roleStats);
            if ($usersWithoutRoles > 0) {
                $this->line("  - [No Role]: {$usersWithoutRoles} users");
            }
        }

        // Show examples of invalid emails
        if (!empty($invalidEmailExamples)) {
            $this->info("\n--- Examples of Invalid Emails (email ignored, user_code = PersonID) ---");
            foreach ($invalidEmailExamples as $example) {
                $this->line("  - \"{$example}\"");
            }
        }

        // Show sample of migrated users
        if ($inserted > 0) {
            $samples = DB::table('users')->limit(3)->get();
            $this->info("\n--- Sample Migrated Users ---");
            foreach ($samples as $user) {
                $userCodeInfo = $user->user_code ? " | User Code: {$user->user_code}" : "";
                $emailInfo = $user->email ? " | Email: {$user->email}" : " | Email: [empty]";
                $roleInfo = $user->legacy_role ? " | Role: {$user->legacy_role}" : " | Role: [none]";
                $legacyPassInfo = $user->legacy_password_hash ? " | Has Legacy Password" : " | No Legacy Password";
                $this->line(
                    "  - ID: {$user->id} | {$user->first_name} {$user->last_name}"
                    . "{$emailInfo}{$userCodeInfo}{$roleInfo}{$legacyPassInfo} | Active: "
                    . ($user->is_inactive ? 'No' : 'Yes')
                    . " | Activated: " . ($user->is_account_activated ? 'Yes' : 'No')
                );
            }

            $totalCount = DB::table('users')->count();
            $activeCount = DB::table('users')->where('is_inactive', false)->count();
            $activatedCount = DB::table('users')->where('is_account_activated', true)->count();
            $userCodeCount = DB::table('users')->whereNotNull('user_code')->count();
            $rolesCount = DB::table('users')->whereNotNull('legacy_role')->count();
            $legacyPasswordCount = DB::table('users')->whereNotNull('legacy_password_hash')->count();

            $this->info("\n--- Database Summary ---");
            $this->info("Total users in database: {$totalCount}");
            $this->info("Active users: {$activeCount}");
            $this->info("Activated accounts: {$activatedCount}");
            $this->info("Users with user_code: {$userCodeCount}");
            $this->info("Users with legacy roles: {$rolesCount}");
            $this->info("Users with legacy passwords: {$legacyPasswordCount}");
        }

        return Command::SUCCESS;
    }

    /**
     * Convert 0 or null values to proper null for foreign keys
     */
    private function normalizeId($id)
    {
        return (!empty($id) && $id > 0) ? $id : null;
    }

    /**
     * Convert small integers, handling 0 as null
     */
    private function normalizeSmallInt($value)
    {
        return (!empty($value) && $value > 0) ? (int) $value : null;
    }

    /**
     * Convert tinyint(1) to boolean, handling null values
     */
    private function normalizeBool($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (bool) $value;
    }
}
