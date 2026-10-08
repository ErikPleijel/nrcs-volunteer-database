<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Log as AuditLog;
use App\Models\Setting;
use App\Services\SettingHtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    public function index()
    {
        return view('settings.index');
    }

    public function edit()
    {
        $settings = Setting::all()->groupBy('group');
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
            if ($setting) {
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

