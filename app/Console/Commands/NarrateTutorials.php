<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class NarrateTutorials extends Command
{
    protected $signature = 'tutorials:narrate
                            {--only= : Process only this filename stem (e.g. level1-welcome-s1)}
                            {--force : Regenerate even if script is unchanged and MP3 exists}
                            {--dry-run : List what would be generated without calling the API}';

    protected $description = 'Generate MP3 narration audio for tutorial slides via ElevenLabs TTS';

    private const NARRATION_DIR  = 'resources/tutorials/narration';
    private const AUDIO_DIR      = 'public/tutorials/audio';
    private const MANIFEST_PATH  = 'tutorial-narration-manifest.json';

    public function handle(): int
    {
        $apiKey  = config('services.elevenlabs.key');
        $voiceId = config('services.elevenlabs.voice_id');
        $modelId = config('services.elevenlabs.model_id');

        if (! $apiKey || ! $voiceId) {
            $this->error('ELEVENLABS_API_KEY and ELEVENLABS_VOICE_ID must be set in .env');
            return self::FAILURE;
        }

        $isDryRun = $this->option('dry-run');
        $isForce  = $this->option('force');
        $only     = $this->option('only');

        // Load existing manifest
        $manifest = [];
        if (Storage::exists(self::MANIFEST_PATH)) {
            $manifest = json_decode(Storage::get(self::MANIFEST_PATH), true) ?? [];
        }

        // Collect script files
        $narrationDir = base_path(self::NARRATION_DIR);
        $scriptFiles  = glob($narrationDir . '/*.txt');

        if (empty($scriptFiles)) {
            $this->warn('No .txt files found in ' . self::NARRATION_DIR);
            return self::SUCCESS;
        }

        if ($only) {
            $scriptFiles = array_filter(
                $scriptFiles,
                fn($f) => pathinfo($f, PATHINFO_FILENAME) === $only
            );
            if (empty($scriptFiles)) {
                $this->error("No script file found for --only={$only}");
                return self::FAILURE;
            }
        }

        $audioDir = base_path(self::AUDIO_DIR);
        if (! is_dir($audioDir)) {
            mkdir($audioDir, 0755, true);
        }

        $rows     = [];
        $anyFailed = false;

        foreach ($scriptFiles as $scriptPath) {
            $stem    = pathinfo($scriptPath, PATHINFO_FILENAME);
            $mp3Name = $stem . '.mp3';
            $mp3Path = $audioDir . '/' . $mp3Name;
            $text    = file_get_contents($scriptPath);
            $hash    = md5($text);
            $chars   = mb_strlen($text);

            $mp3Exists   = file_exists($mp3Path);
            $hashMatches = isset($manifest[$stem]) && $manifest[$stem] === $hash;

            if (! $isForce && $hashMatches && $mp3Exists) {
                $rows[] = [$stem . '.txt', $chars, 'skipped (unchanged)'];
                continue;
            }

            if ($isDryRun) {
                $reason = (! $mp3Exists) ? 'new file' : ($isForce ? 'forced' : 'script changed');
                $rows[] = [$stem . '.txt', $chars, "would generate ({$reason})"];
                continue;
            }

            $this->line("Generating: {$stem}.mp3 ({$chars} chars)");

            $url = "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}?output_format=mp3_44100_64";

            $response = Http::withHeaders(['xi-api-key' => $apiKey])
                ->timeout(120)
                ->post($url, [
                    'text'          => $text,
                    'model_id'      => $modelId,
                    'voice_settings' => [
                        'stability'       => 0.65,
                        'similarity_boost' => 0.75,
                    ],
                ]);

            if ($response->status() !== 200) {
                $this->error("  Failed [{$response->status()}]: " . $response->body());
                $rows[]    = [$stem . '.txt', $chars, 'FAILED'];
                $anyFailed = true;
                sleep(1);
                continue;
            }

            file_put_contents($mp3Path, $response->body());
            $manifest[$stem] = $hash;
            Storage::put(self::MANIFEST_PATH, json_encode($manifest, JSON_PRETTY_PRINT));

            $rows[] = [$stem . '.txt', $chars, 'generated'];

            sleep(1);
        }

        $this->newLine();
        $this->table(['File', 'Characters', 'Status'], $rows);

        return $anyFailed ? self::FAILURE : self::SUCCESS;
    }
}
