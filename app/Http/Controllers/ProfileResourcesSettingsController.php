<?php

namespace App\Http\Controllers;

use App\Models\Log as AuditLog;
use App\Models\Setting;
use App\Services\ProfileResources;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Throwable;

/**
 * Settings page for the "Useful links" and "Downloads" lists on My Profile
 * (see ProfileResources). Route-gated on change_settings + password.confirm
 * like the rest of the Settings area. Kept off the generic settings form,
 * which would show the lists as raw JSON.
 *
 * A downloads row is either an external url or an uploaded file. Paths and
 * ids never come from the form: a posted id only keeps the stored file of
 * the row that already has that id; anything else gets a new id.
 */
class ProfileResourcesSettingsController extends Controller
{
    private const MAX_ROWS = 50;

    public function index()
    {
        $storedDownloads = collect(ProfileResources::downloads())->keyBy('id');

        // After a failed save, show what was submitted (uploads are lost:
        // the browser cannot refill a file input).
        $links = old('links', ProfileResources::links());
        $downloads = collect(old('downloads', ProfileResources::downloads()))
            ->map(function ($row) use ($storedDownloads) {
                $stored = $storedDownloads->get($row['id'] ?? null);

                return [
                    'id' => $stored['id'] ?? '',
                    'title' => (string) ($row['title'] ?? ''),
                    'source' => $row['source'] ?? (($row['path'] ?? null) ? 'file' : 'url'),
                    'url' => (string) ($row['url'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'file_name' => $stored['file_name'] ?? null,
                ];
            })
            ->values()
            ->all();

        return view('settings.profile-resources', [
            'links' => array_values(array_map(fn ($row) => [
                'title' => (string) ($row['title'] ?? ''),
                'url' => (string) ($row['url'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
            ], $links)),
            'downloads' => $downloads,
        ]);
    }

    public function update(Request $request)
    {
        $storedDownloads = collect(ProfileResources::downloads())->keyBy('id');

        $validated = validator($request->all(), [
            'links' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'links.*.title' => ['required', 'string', 'max:80'],
            'links.*.url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'links.*.description' => ['nullable', 'string', 'max:200'],

            'downloads' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'downloads.*.id' => ['nullable', 'string', 'max:64'],
            'downloads.*.title' => ['required', 'string', 'max:80'],
            'downloads.*.source' => ['required', 'in:url,file'],
            'downloads.*.url' => ['exclude_unless:downloads.*.source,url', 'required', 'string', 'max:2048', 'url:http,https'],
            'downloads.*.file' => [
                'exclude_unless:downloads.*.source,file',
                'nullable',
                'file',
                'extensions:'.implode(',', array_keys(ProfileResources::FILE_TYPES)),
                'mimes:'.implode(',', array_keys(ProfileResources::FILE_TYPES)),
                'max:'.ProfileResources::MAX_UPLOAD_KB,
            ],
            'downloads.*.description' => ['nullable', 'string', 'max:200'],
        ], [
            'links.*.url.url' => 'The :attribute must start with http:// or https://.',
            'downloads.*.url.url' => 'The :attribute must start with http:// or https://.',
            'downloads.*.file.extensions' => 'The :attribute must be a PDF, Word (.docx) or Excel (.xlsx) file.',
            'downloads.*.file.mimes' => 'The :attribute must be a PDF, Word (.docx) or Excel (.xlsx) file.',
        ], [
            'links.*.title' => 'title',
            'links.*.url' => 'web address',
            'links.*.description' => 'description',
            'downloads.*.title' => 'title',
            'downloads.*.source' => 'type',
            'downloads.*.url' => 'web address',
            'downloads.*.file' => 'file',
            'downloads.*.description' => 'description',
        ])
            ->after(function (Validator $validator) use ($request, $storedDownloads) {
                foreach ((array) $request->input('downloads', []) as $i => $row) {
                    if (($row['source'] ?? null) !== 'file' || $request->hasFile("downloads.{$i}.file")) {
                        continue;
                    }

                    $stored = $storedDownloads->get($row['id'] ?? null);
                    if (! $stored || $stored['path'] === null) {
                        $validator->errors()->add("downloads.{$i}.file", 'Choose a file to upload.');
                    }
                }
            })
            ->validate();

        $links = array_map(fn (array $row) => [
            'title' => trim($row['title']),
            'url' => trim($row['url']),
            'description' => trim((string) ($row['description'] ?? '')),
        ], array_values($validated['links'] ?? []));

        // Store new uploads first; if saving the settings then fails, they
        // are removed again so nothing is left on disk unreferenced.
        $newPaths = [];
        $downloads = [];
        foreach (array_values($validated['downloads'] ?? []) as $i => $row) {
            $stored = $storedDownloads->get($row['id'] ?? null);
            $download = [
                'id' => $stored['id'] ?? (string) Str::uuid(),
                'title' => trim($row['title']),
                'url' => null,
                'path' => null,
                'file_name' => null,
                'description' => trim((string) ($row['description'] ?? '')),
            ];

            $file = $row['file'] ?? null;
            if ($row['source'] === 'url') {
                $download['url'] = trim($row['url']);
            } elseif ($file instanceof UploadedFile) {
                $download['path'] = $file->store(ProfileResources::DIRECTORY, ProfileResources::DISK);
                $download['file_name'] = Str::limit(basename($file->getClientOriginalName()), 150, '');
                $newPaths[] = $download['path'];
            } else {
                $download['path'] = $stored['path'];
                $download['file_name'] = $stored['file_name'];
            }

            $downloads[] = $download;
        }

        $oldPaths = ProfileResources::storedPaths();

        try {
            DB::transaction(function () use ($links, $downloads) {
                $this->saveSetting(ProfileResources::LINKS_KEY, $links);
                $this->saveSetting(ProfileResources::DOWNLOADS_KEY, $downloads);
            });
        } catch (Throwable $e) {
            Storage::disk(ProfileResources::DISK)->delete($newPaths);

            throw $e;
        }

        // Files of deleted rows, and files replaced by a new upload.
        $unused = array_diff($oldPaths, array_filter(array_column($downloads, 'path')));
        if ($unused !== []) {
            Storage::disk(ProfileResources::DISK)->delete(array_values($unused));
        }

        return redirect()
            ->route('admin.settings.profile-resources.index')
            ->with('success', 'Useful links and downloads updated successfully.');
    }

    /**
     * Store one json list, clear its cache and audit the change; nothing
     * happens when the list is unchanged.
     */
    private function saveSetting(string $key, array $value): void
    {
        $setting = Setting::firstOrNew(['key' => $key], [
            'type' => 'json',
            'group' => ProfileResources::GROUP,
        ]);
        $oldValue = $setting->value;

        // Same encoding as Setting::setValueAttribute().
        if ($oldValue === json_encode($value)) {
            return;
        }

        $setting->value = $value;
        $setting->save();

        Cache::forget("setting.{$key}");

        // Same entry shape as SettingController::update(); the lists are in
        // old_values / new_values, too long for the description.
        AuditLog::write(
            'setting_changed',
            null,
            null,
            [$key => $oldValue === null ? null : json_decode($oldValue, true)],
            [$key => $value],
            sprintf('Setting "%s" updated.', $key)
        );
    }
}
