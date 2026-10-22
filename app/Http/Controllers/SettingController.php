<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Log as AuditLog;
use App\Models\Setting;
use App\Services\FeeDistributionSettings;
use App\Services\ProfileResources;
use App\Services\SettingHtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    /**
     * Groups edited on their own Settings page and never on this form: fee
     * distribution (each rule must add up to 100) and the My Profile links
     * and downloads (json lists, which this form would show as raw JSON).
     */
    private const OWN_PAGE_GROUPS = [
        FeeDistributionSettings::GROUP,
        ProfileResources::GROUP,
    ];

    public function index()
    {
        return view('settings.index');
    }

    public function edit()
    {
        $settings = Setting::whereNotIn('group', self::OWN_PAGE_GROUPS)
            ->orWhereNull('group')
            ->get()
            ->groupBy('group');
        return view('settings.edit', compact('settings'));
    }

    public function update(Request $request, SettingHtmlSanitizer $sanitizer)
    {
        // Setting keys contain dots, so they are escaped in the rule keys.
        $request->validate(
            ['settings.dpo\.email' => ['nullable', 'email']],
            [],
            ['settings.dpo\.email' => 'DPO email']
        );

        $settings = $request->input('settings', []);

        foreach ($settings as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            if ($setting && ! in_array($setting->group, self::OWN_PAGE_GROUPS, true)) {
                // An empty field arrives as null (ConvertEmptyStringsToNull)
                // but is stored as ''; normalise it so an unchanged empty
                // setting is not audited as changed on every save.
                $value = $value ?? '';

                // HTML settings render unescaped on every page: only the
                // allowlisted markup is ever stored (the footer re-sanitizes).
                if ($setting->type === 'html') {
                    $value = $sanitizer->sanitize($value);
                }


                $oldValue = $setting->value;
                $setting->value = $value;
                $setting->save();

                Cache::forget("setting.{$key}");

                if ($oldValue !== $value) {
                    // logs.description is varchar(255); the full values are
                    // in old_values / new_values, so HTML isn't repeated here.
                    $description = $setting->type === 'html'
                        ? sprintf('Setting "%s" updated.', $key)
                        : sprintf('Setting "%s" updated from "%s" to "%s".', $key, $oldValue, $value);

                    AuditLog::write(
                        'setting_changed',
                        null,
                        null,
                        [$key => $oldValue],
                        [$key => $value],
                        Str::limit($description, 252) // + '...' = 255
                    );
                }
            }
        }

        return redirect()->route('admin.settings.index')->with('success', 'Settings updated successfully.');
    }
}

