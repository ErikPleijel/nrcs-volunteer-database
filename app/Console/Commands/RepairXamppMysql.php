<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RepairXamppMysql extends Command
{
    /**
     * The name and signature of the console command.
     *
     * You can optionally pass a different base path:
     * php artisan repair:xampp-mysql --path="D:\xampp\mysql"
     */
    protected $signature = 'repair:xampp-mysql
                            {--path=C:\xampp\mysql : Base path to XAMPP MySQL folder}';

    /**
     * The console command description.
     */
    protected $description = 'Attempt to repair corrupted XAMPP MySQL by resetting data folder using backup';

    public function handle()
    {
        $basePath = rtrim($this->option('path'), "\\/");
        $dataPath = $basePath . DIRECTORY_SEPARATOR . 'data';
        $backupPath = $basePath . DIRECTORY_SEPARATOR . 'backup';

        $this->warn('⚠️  DANGER ZONE: This will modify your local MySQL data files.');
        $this->line("Base path: {$basePath}");
        $this->line("Data path: {$dataPath}");
        $this->line("Backup path: {$backupPath}");
        $this->line('');
        $this->warn('Make sure XAMPP MySQL is STOPPED before running this.');
        $this->warn('Proceed only if you understand that this can cause data loss.');

        if (! $this->confirm('Do you really want to continue?', false)) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        if (! is_dir($dataPath)) {
            $this->error("Data folder not found: {$dataPath}");
            return Command::FAILURE;
        }

        if (! is_dir($backupPath)) {
            $this->error("Backup folder not found: {$backupPath}");
            return Command::FAILURE;
        }

        // 1. Duplicate the folder "data" and call the duplicate "data_old_[datestamp]"
        $timestamp = date('Ymd_His');
        $dataBackupPath = $basePath . DIRECTORY_SEPARATOR . "data_old_{$timestamp}";

        $this->info("1. Backing up data folder to: {$dataBackupPath}");

        if (! $this->copyDirectory($dataPath, $dataBackupPath)) {
            $this->error('Failed to back up data folder.');
            return Command::FAILURE;
        }

        // 2. In the folder "data", delete these folders:
        //    mysql; performance_schema; phpmyadmin; test.
        $foldersToDelete = ['mysql', 'performance_schema', 'phpmyadmin', 'test'];

        $this->info('2. Deleting system folders from data: ' . implode(', ', $foldersToDelete));

        foreach ($foldersToDelete as $folder) {
            $folderPath = $dataPath . DIRECTORY_SEPARATOR . $folder;
            if (is_dir($folderPath)) {
                $this->line(" - Removing folder: {$folderPath}");
                if (! $this->deleteDirectory($folderPath)) {
                    $this->error("   Failed to delete folder: {$folderPath}");
                }
            } else {
                $this->line(" - Folder not found (skipped): {$folderPath}");
            }
        }

        // 3. In "data", delete all files EXCEPT ibdata1.
        $this->info('3. Deleting all files in data except ibdata1');

        $items = scandir($dataPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $dataPath . DIRECTORY_SEPARATOR . $item;

            if (is_file($fullPath)) {
                if (strtolower($item) === 'ibdata1') {
                    $this->line(" - Keeping file: {$item}");
                    continue;
                }

                $this->line(" - Deleting file: {$item}");
                if (! @unlink($fullPath)) {
                    $this->error("   Failed to delete file: {$fullPath}");
                }
            }
        }

        // 4. Copy everything in folder "backup" (except file ibdata1) into "data".
        $this->info('4. Copying backup folder contents into data (excluding ibdata1)');

        if (! $this->copyDirectory($backupPath, $dataPath, ['ibdata1'])) {
            $this->error('Failed to copy backup contents to data folder.');
            return Command::FAILURE;
        }

        $this->info('');
        $this->info('✅ Done.');
        $this->info('Now try starting MySQL in XAMPP and check if it runs.');
        $this->warn('If something went wrong, your original data is backed up in: ' . $dataBackupPath);

        return Command::SUCCESS;
    }

    /**
     * Recursively copy a directory.
     *
     * @param  string  $source
     * @param  string  $destination
     * @param  array   $skipFiles  Filenames (basename) to skip (e.g. ["ibdata1"])
     * @return bool
     */
    protected function copyDirectory(string $source, string $destination, array $skipFiles = []): bool
    {
        if (! is_dir($source)) {
            return false;
        }

        if (! is_dir($destination)) {
            if (! mkdir($destination, 0777, true) && ! is_dir($destination)) {
                return false;
            }
        }

        $dir = opendir($source);
        if (! $dir) {
            return false;
        }

        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcPath = $source . DIRECTORY_SEPARATOR . $file;
            $destPath = $destination . DIRECTORY_SEPARATOR . $file;

            if (is_dir($srcPath)) {
                if (! $this->copyDirectory($srcPath, $destPath, $skipFiles)) {
                    closedir($dir);
                    return false;
                }
            } else {
                if (in_array(strtolower($file), array_map('strtolower', $skipFiles), true)) {
                    $this->line(" - Skipping file (per config): {$srcPath}");
                    continue;
                }

                if (! @copy($srcPath, $destPath)) {
                    closedir($dir);
                    return false;
                }
            }
        }

        closedir($dir);
        return true;
    }

    /**
     * Recursively delete a directory and its contents.
     *
     * @param  string  $dir
     * @return bool
     */
    protected function deleteDirectory(string $dir): bool
    {
        if (! is_dir($dir)) {
            return true;
        }

        $items = scandir($dir);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                if (! $this->deleteDirectory($path)) {
                    return false;
                }
            } else {
                if (! @unlink($path)) {
                    return false;
                }
            }
        }

        return @rmdir($dir);
    }
}
