<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportDivisionCoordinatesFromCsv extends Command
{
    protected $signature = 'divisions:import-geo
                            {file : Path to the CSV file (e.g. storage/app/lgas.csv)}
                            {--delimiter=, : CSV delimiter}
                            {--dry-run : Run without updating the database}';

    protected $description = 'Import latitude/longitude into divisions table from an LGA CSV file';

    public function handle(): int
    {
        $file      = $this->argument('file');
        $delimiter = $this->option('delimiter');
        $dryRun    = $this->option('dry-run');

        // Allow relative paths from base_path
        if (!file_exists($file)) {
            $alt = base_path($file);
            if (file_exists($alt)) {
                $file = $alt;
            }
        }

        if (!file_exists($file)) {
            $this->error("❌ File not found: {$this->argument('file')}");
            return Command::FAILURE;
        }

        $this->info("📂 Reading CSV: {$file}");
        if ($dryRun) {
            $this->warn('DRY RUN – no database changes will be made.');
        }

        if (($handle = fopen($file, 'r')) === false) {
            $this->error('❌ Could not open the CSV file.');
            return Command::FAILURE;
        }

        $header = null;
        $rows   = [];

        // ---- Read CSV into memory ----
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($header === null) {
                // First row = header
                $header = array_map(function ($col) {
                    // Trim + remove possible UTF-8 BOM
                    return ltrim(trim($col), "\xEF\xBB\xBF");
                }, $data);
                continue;
            }

            if (count($data) === 1 && ($data[0] === null || $data[0] === '')) {
                continue; // skip empty line
            }

            $row = [];
            foreach ($header as $index => $columnName) {
                $row[$columnName] = $data[$index] ?? null;
            }

            $rows[] = $row;
        }

        fclose($handle);

        $total = count($rows);
        if ($total === 0) {
            $this->warn('⚠️ No data rows found in CSV.');
            return Command::SUCCESS;
        }

        $this->info("📊 Found {$total} rows in CSV.");

        $matched         = 0;
        $updated         = 0;
        $missingBranch   = 0;
        $missingDivision = 0;
        $skippedNoCoord  = 0;

        // Diagnostics
        $missingBranchStates = [];  // unique state_names with no matching branch
        $missingDivisionRows = [];  // sample rows where division not found

        // Cache of divisions per branch: [branch_id => [normalizedKey => divisionRow]]
        $divisionsCache = [];

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        foreach ($rows as $row) {
            $nameRaw      = trim($row['name']       ?? '');
            $stateNameRaw = trim($row['state_name'] ?? '');
            $lat          = $this->toDecimal($row['latitude']  ?? null);
            $lng          = $this->toDecimal($row['longitude'] ?? null);

            if ($nameRaw === '' || $stateNameRaw === '') {
                $progressBar->advance();
                continue;
            }

            if ($lat === null || $lng === null) {
                $skippedNoCoord++;
                $progressBar->advance();
                continue;
            }

            // Apply spelling aliases on CSV side (division + state)
            $stateNameForMatch = $this->applyBranchAlias($stateNameRaw);
            $csvNameForMatch   = $this->applyDivisionAlias($nameRaw, $stateNameRaw);

            // 1. Find branch by state_name (case-insensitive, with alias)
            $branchId = DB::table('branches')
                ->whereRaw('LOWER(name) = ?', [strtolower($stateNameForMatch)])
                ->value('id');

            if (!$branchId) {
                $missingBranch++;
                $missingBranchStates[$stateNameRaw] = true;
                $progressBar->advance();
                continue;
            }

            // 2. Build cache of divisions for this branch on first use
            if (!isset($divisionsCache[$branchId])) {
                $divisionsForBranch = DB::table('divisions')
                    ->where('branch_id', $branchId)
                    ->get();

                $map = [];
                foreach ($divisionsForBranch as $div) {
                    $key = $this->normalizeKey($div->name);
                    if ($key !== '') {
                        $map[$key] = $div;
                    }
                }

                $divisionsCache[$branchId] = $map;
            }

            $branchMap = $divisionsCache[$branchId];
            $searchKey = $this->normalizeKey($csvNameForMatch);

            $division = $branchMap[$searchKey] ?? null;

            if (!$division) {
                $missingDivision++;
                if (count($missingDivisionRows) < 80) {
                    $missingDivisionRows[] = [
                        'state_name'      => $stateNameRaw,
                        'csv_name'        => $nameRaw,
                        'csv_match_name'  => $csvNameForMatch,
                        'search_key'      => $searchKey,
                        'lat'             => $lat,
                        'lng'             => $lng,
                    ];
                }
                $progressBar->advance();
                continue;
            }

            $matched++;

            if (!$dryRun) {
                DB::table('divisions')
                    ->where('id', $division->id)
                    ->update([
                        'latitude'   => $lat,
                        'longitude'  => $lng,
                        'updated_at' => now(),
                    ]);
            }



            $updated++;
            $progressBar->advance();
        }

        // ------------------------------------------------------------------
// SPECIAL FIX: Set coordinates for Okeigbo (division id = 3654)
// by displacing them slightly from Ile-Oluji
// ------------------------------------------------------------------
        if (!$dryRun) {

            // 1. Get Ile-Oluji coordinates (same branch as Okeigbo)
            $ileOluji = DB::table('divisions')
                ->where('name', 'Ile-Oluji')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->first();

            if ($ileOluji) {

                // 2. Displace slightly
                $okeigboLat = $ileOluji->latitude  + 0.015;
                $okeigboLng = $ileOluji->longitude + 0.020;

                // 3. Update ONLY Okeigbo (id = 3654)
                DB::table('divisions')
                    ->where('id', 3654)
                    ->update([
                        'latitude'   => $okeigboLat,
                        'longitude'  => $okeigboLng,
                        'updated_at' => now(),
                    ]);

                $this->info("📍 Okeigbo (ID 3654) updated using displaced Ile-Oluji coordinates.");
                $this->line("    Lat: {$okeigboLat}, Lng: {$okeigboLng}");

            } else {
                $this->warn('⚠️ Ile-Oluji not found or has no coordinates — Okeigbo not updated.');
            }
        }


        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('✅ Import completed. Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total CSV rows', $total],
                ['Matched divisions (by name + branch/state)', $matched],
                ['Updated divisions', $updated],
                ['Missing branch (state_name not found in branches)', $missingBranch],
                ['Missing division (name+branch_id not found)', $missingDivision],
                ['Skipped (no coordinates)', $skippedNoCoord],
            ]
        );

        // Extra diagnostics
        if (!empty($missingBranchStates)) {
            $this->info('🧭 States with no matching branches.name:');
            foreach (array_keys($missingBranchStates) as $state) {
                $this->line('  - ' . $state);
            }
        }

        if (!empty($missingDivisionRows)) {
            $this->info('🏷  Sample of divisions that did not match by (name + branch):');
            $this->table(
                ['state_name', 'csv_name', 'csv_match_name', 'search_key', 'lat', 'lng'],
                $missingDivisionRows
            );
        }

        if ($dryRun) {
            $this->warn('This was a DRY RUN – no data was actually written to the database.');
        }

        return Command::SUCCESS;
    }

    /**
     * Convert a CSV value to decimal (string) or null.
     */
    private function toDecimal($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // Replace comma with dot if necessary
        $value = str_replace(',', '.', $value);

        if (!is_numeric($value)) {
            return null;
        }

        // Return as string; MySQL will cast into decimal(10,8 / 11,8)
        return $value;
    }

    /**
     * Normalize a name for comparison:
     * - lowercase
     * - remove all non a–z / 0–9 characters (spaces, hyphens, punctuation)
     */
    private function normalizeKey(string $name): string
    {
        $name = mb_strtolower($name);
        // remove everything except letters and digits
        $name = preg_replace('/[^a-z0-9]+/u', '', $name);

        return $name ?? '';
    }

    /**
     * Apply manual alias fixes for truly different spellings (CSV -> DB).
     */
    private function applyDivisionAlias(string $name, string $stateName): string
    {
        $key = strtolower(trim($name));
        $stateKey  = strtolower(trim($stateName));
        // Special case: FCT Abuja -> Abuja Municipal
        if ($stateKey === 'federal capital territory' && $key === 'abuja') {
            return 'Abuja Municipal';
        }


        $aliases = [
            // Bayelsa
            'southern ijaw'           => 'Southern Jaw',
            'yenagoa'                 => 'Yenegoa',

            // Benue
            'otukpo'                  => 'Oturkpo',

            // Cross River
            'bekwarra'                => 'Bekwara',

            // Ebonyi
            'izzi'                    => 'Ngbo',          // special case you noted
            'ikwo'                    => 'lkwo',

            // Edo
            'orhionmwon'              => 'Orhionwon',

            // Ekiti
            'ado-ekiti'               => 'Ado',
            'emure'                   => 'Emure/Ise/Orun',
            'irepodun/ifelodun'       => 'Irepodun',

            // Enugu
            'igbo etiti'              => 'Igbo-Ekiti',

            // Gombe
            'shongom'                 => 'Shomgom',

            // Imo
            'ezinihitte mbaise'       => 'Ezinihitte',

            // Jigawa
            'birnin kudu'             => 'Birni Kudu',
            'kiri kasama'             => 'Kiri Kasamma',

            // Kaduna
            'birnin gwari'            => 'Birni-Gwari',
            'zangon kataf'            => 'Zango-Kataf',

            // Kano
            'garun malam'             => 'Garum Malam',
            'nasarawa'                => 'Nassarawa',

            // Katsina
            'matazu'                  => 'Matazuu',

            // Kebbi
            'aliero'                  => 'Aleiro',
            'danko-wasagu'            => 'Wasagu/Danko',

            // Kogi
            'ogori/magongo'           => 'Ogori/Mangongo',

            // Lagos
            'ifako-ijaiye'            => 'Ifako-Ijaye',
            'somolu'                  => 'Shomolu',

            // Nasarawa
            'nasarawa egon'           => 'Nasarawa-Eggon',

            // Ondo
            'ile oluji/okeigbo'       => 'Ile-Oluji', // choose Ile-Oluji as target

            // Osun
            'ayedaade'                => 'Aiyedade',
            'atakunmosa east'         => 'Atakumosa East',
            'atakunmosa west'         => 'Atakumosa West',
            'ilesa east'              => 'Ilesha East',
            'ilesa west'              => 'Ilesha West',

            // Oyo
            'ogbomosho south'         => 'Ogbmosho South',
            'oorelope'                => 'Orelope',
            'ogbomosho north'         => 'Ogbomoso North',

            // Plateau
            'barkin ladi'             => 'Barikin Ladi',

            'isuikwuato' => 'Isuikwato',
            'warri south west' => 'Warri-South West',

            // Rivers
            'obio/akpor'              => 'Obia/Akpor',
            'omuma'                   => 'Omumma',

            // Zamfara
            'birnin magaji/kiyaw'     => 'Birnin Magaji',
        ];

        return $aliases[$key] ?? $name;
    }

    /**
     * Apply alias for state/branch names (CSV -> branches.name).
     * Mainly for FCT / Federal Capital Territory.
     */
    private function applyBranchAlias(string $stateName): string
    {
        $key = strtolower(trim($stateName));

        $aliases = [
            'federal capital territory' => 'FCT',
            'f.c.t.'                    => 'FCT',
            'abuja fct'                 => 'FCT',
        ];

        return $aliases[$key] ?? $stateName;
    }
}
