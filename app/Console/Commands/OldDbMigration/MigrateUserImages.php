<?php

namespace App\Console\Commands\OldDbMigration;

use App\Models\User;
use App\Traits\HandlesImageUploads;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MigrateUserImages extends Command
{
    use HandlesImageUploads;

    protected $signature = 'images:migrate
        {--source= : Filesystem path to the old image directory, OR an http(s) URL prefix}
        {--only= : photos|signatures (default: both)}
        {--limit= : Process at most N users (for testing)}
        {--dry-run : Report what would be done without writing anything}
        {--delay=0 : Milliseconds to sleep between HTTP downloads (ignored for filesystem source)}';

    protected $description = 'Migrate user profile photos and signatures from a legacy source to local web-optimized storage.';

    private int $usersScanned   = 0;
    private int $photosMigrated = 0;
    private int $photosSkipped  = 0;
    private int $photosFailed   = 0;
    private int $sigsMigrated   = 0;
    private int $sigsSkipped    = 0;
    private int $sigsFailed     = 0;

    public function handle(): int
    {
        $source = $this->option('source') ?? config('services.image_migration.source');

        if (empty($source)) {
            $this->error('No --source given and IMAGE_MIGRATION_SOURCE is not set. Aborting.');
            return self::FAILURE;
        }

        $only = $this->option('only');
        if ($only && !in_array($only, ['photos', 'signatures'], true)) {
            $this->error('--only must be "photos" or "signatures".');
            return self::FAILURE;
        }

        $limit   = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $dryRun  = (bool) $this->option('dry-run');
        $delayMs = (int) $this->option('delay');
        $isHttp  = str_starts_with($source, 'http://') || str_starts_with($source, 'https://');

        if (!$isHttp && !is_dir($source)) {
            $this->error("Source directory does not exist: {$source}");
            return self::FAILURE;
        }

        if (!$dryRun) {
            Storage::disk('local')->makeDirectory('photos/profile/original');
            Storage::disk('local')->makeDirectory('photos/profile/web');
            Storage::disk('local')->makeDirectory('photos/signatures/original');
            Storage::disk('local')->makeDirectory('photos/signatures/web');
        }

        $doPhotos     = $only !== 'signatures';
        $doSignatures = $only !== 'photos';

        $query = User::query();
        if ($only === 'photos') {
            $query->whereNotNull('picture');
        } elseif ($only === 'signatures') {
            $query->whereNotNull('signature');
        } else {
            $query->where(function ($q) {
                $q->whereNotNull('picture')->orWhereNotNull('signature');
            });
        }

        $totalCount = (clone $query)->count();
        if ($limit !== null) {
            $totalCount = min($totalCount, $limit);
        }

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $startedAt = microtime(true);
        $processed = 0;

        $query->chunkById(500, function ($users) use (
            $source, $isHttp, $dryRun, $delayMs,
            $doPhotos, $doSignatures, $limit, &$processed, $bar
        ) {
            foreach ($users as $user) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                $this->usersScanned++;
                $processed++;

                try {
                    if ($doPhotos && $user->picture) {
                        $this->migrateImage(
                            $user->id, $user->picture,
                            'profile', 'photo',
                            $source, $isHttp, $dryRun, $delayMs
                        );
                    }

                    if ($doSignatures && $user->signature) {
                        $this->migrateImage(
                            $user->id, $user->signature,
                            'signatures', 'signature',
                            $source, $isHttp, $dryRun, $delayMs
                        );
                    }
                } catch (\Throwable $e) {
                    $this->logFailure($user->id, 'unknown', '', 'Unexpected error: ' . $e->getMessage());
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $elapsed = round(microtime(true) - $startedAt, 2);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Users scanned',       $this->usersScanned],
                ['Photos migrated',     $this->photosMigrated],
                ['Photos skipped',      $this->photosSkipped],
                ['Photos failed',       $this->photosFailed],
                ['Signatures migrated', $this->sigsMigrated],
                ['Signatures skipped',  $this->sigsSkipped],
                ['Signatures failed',   $this->sigsFailed],
                ['Elapsed',             "{$elapsed}s"],
            ]
        );

        if ($dryRun) {
            $this->info('Dry-run mode: no files were written.');
        }

        return self::SUCCESS;
    }

    private function migrateImage(
        int $userId, string $filename,
        string $category, string $type,
        string $source, bool $isHttp, bool $dryRun, int $delayMs
    ): void {
        if (str_contains($filename, '..') || str_starts_with($filename, '/')) {
            $this->logFailure($userId, $type, $filename, 'Rejected: filename contains path-traversal characters');
            $this->tally($type, 'failed');
            return;
        }

        $basename     = basename($filename);
        $originalPath = Storage::disk('local')->path("photos/{$category}/original/{$basename}");
        $webPath      = Storage::disk('local')->path("photos/{$category}/web/{$basename}");

        if (File::exists($originalPath) && File::exists($webPath)) {
            $this->tally($type, 'skipped');
            return;
        }

        if ($dryRun) {
            $this->tally($type, 'migrated');
            return;
        }

        if (!File::exists($originalPath)) {
            $content = $this->fetchContent($source, $filename, $isHttp, $delayMs, $userId, $type);
            if ($content === null) {
                $this->tally($type, 'failed');
                return;
            }
            if (file_put_contents($originalPath, $content) === false) {
                $this->logFailure($userId, $type, $filename, 'Failed to write original file');
                $this->tally($type, 'failed');
                return;
            }
        }

        if (!File::exists($webPath)) {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            [$maxW, $maxH] = $extension === 'png' ? [200, 200] : [400, 400];

            if (!$this->createOptimizedImage($originalPath, $webPath, $maxW, $maxH, 95)) {
                $this->logFailure($userId, $type, $filename, 'createOptimizedImage failed');
                $this->tally($type, 'failed');
                return;
            }
        }

        $this->tally($type, 'migrated');
    }

    private function fetchContent(
        string $source, string $filename,
        bool $isHttp, int $delayMs,
        int $userId, string $type
    ): ?string {
        if ($isHttp) {
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
            try {
                $response = Http::timeout(20)->get(rtrim($source, '/') . '/' . $filename);
                if (!$response->successful()) {
                    $this->logFailure($userId, $type, $filename, "HTTP {$response->status()}");
                    return null;
                }
                return $response->body();
            } catch (\Throwable $e) {
                $this->logFailure($userId, $type, $filename, 'HTTP error: ' . $e->getMessage());
                return null;
            }
        }

        $path = rtrim($source, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        if (!File::exists($path)) {
            $this->logFailure($userId, $type, $filename, "Not found: {$path}");
            return null;
        }
        try {
            return File::get($path);
        } catch (\Throwable $e) {
            $this->logFailure($userId, $type, $filename, 'Read error: ' . $e->getMessage());
            return null;
        }
    }

    private function tally(string $type, string $bucket): void
    {
        match ("{$type}.{$bucket}") {
            'photo.migrated'     => $this->photosMigrated++,
            'photo.skipped'      => $this->photosSkipped++,
            'photo.failed'       => $this->photosFailed++,
            'signature.migrated' => $this->sigsMigrated++,
            'signature.skipped'  => $this->sigsSkipped++,
            'signature.failed'   => $this->sigsFailed++,
            default              => null,
        };
    }

    private function logFailure(int $userId, string $type, string $filename, string $reason): void
    {
        $entry = implode("\t", [
            now()->toIso8601String(),
            "user={$userId}",
            "type={$type}",
            "file={$filename}",
            "reason={$reason}",
        ]) . PHP_EOL;

        file_put_contents(storage_path('logs/image-migration.log'), $entry, FILE_APPEND | LOCK_EX);
    }
}
