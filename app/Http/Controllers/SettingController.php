<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Log as AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

    public function update(Request $request)
    {
        $settings = $request->input('settings', []);

        foreach ($settings as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            if ($setting) {
                $oldValue = $setting->value;
                $setting->value = $value;
                $setting->save();

                Cache::forget("setting.{$key}");

                if ($oldValue !== $value) {
                    AuditLog::write(
                        'setting_changed',
                        null,
                        null,
                        [$key => $oldValue],
                        [$key => $value],
                        sprintf('Setting "%s" updated from "%s" to "%s".', $key, $oldValue, $value)
                    );
                }
            }
        }

        return redirect()->route('admin.settings.index')->with('success', 'Settings updated successfully.');
    }
}

