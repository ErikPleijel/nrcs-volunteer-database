<?php

namespace App\Http\Controllers;

use App\Models\Log as AuditLog;
use App\Models\Setting;
use App\Services\FeeDistributionSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

/**
 * Settings page for the online income split (see FeeDistributionSettings).
 * Route-gated on change_settings + password.confirm like the rest of the
 * Settings area. Kept off the generic settings form because each rule must
 * add up to exactly 100, which that form cannot check.
 */
class FeeDistributionSettingsController extends Controller
{
    private const RULE_LABELS = [
        'standard' => 'Payment with a division',
        'branch_level' => 'Payment with a branch but no division (e.g. organisation payments)',
    ];

    private const PARTY_LABELS = [
        'hq' => 'HQ',
        'branch' => 'Branch',
        'division' => 'Division',
    ];

    public function index()
    {
        return view('settings.fee-distribution', [
            'percentages' => FeeDistributionSettings::percentages(),
            'ruleLabels' => self::RULE_LABELS,
            'partyLabels' => self::PARTY_LABELS,
        ]);
    }

    public function update(Request $request)
    {
        $rules = [];
        $attributes = [];
        foreach (FeeDistributionSettings::DEFAULTS as $rule => $parties) {
            foreach (array_keys($parties) as $party) {
                $rules["percentages.{$rule}.{$party}"] = ['required', 'integer', 'between:0,100'];
                $attributes["percentages.{$rule}.{$party}"] = self::PARTY_LABELS[$party].' ('.strtolower(self::RULE_LABELS[$rule]).')';
            }
        }

        $validated = validator($request->all(), $rules, [], $attributes)
            ->after(function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach (FeeDistributionSettings::DEFAULTS as $rule => $parties) {
                    $sum = 0;
                    foreach (array_keys($parties) as $party) {
                        $sum += (int) $validator->getData()['percentages'][$rule][$party];
                    }

                    if ($sum !== 100) {
                        $validator->errors()->add(
                            "percentages.{$rule}",
                            sprintf('%s: the percentages add up to %d. They must add up to exactly 100.', self::RULE_LABELS[$rule], $sum)
                        );
                    }
                }
            })
            ->validate();

        DB::transaction(function () use ($validated) {
            foreach (FeeDistributionSettings::DEFAULTS as $rule => $parties) {
                foreach (array_keys($parties) as $party) {
                    $key = FeeDistributionSettings::key($rule, $party);
                    $value = (string) (int) $validated['percentages'][$rule][$party];

                    $setting = Setting::firstOrNew(['key' => $key], [
                        'type' => 'int',
                        'group' => FeeDistributionSettings::GROUP,
                    ]);
                    $oldValue = $setting->value;

                    if ($oldValue === $value) {
                        continue;
                    }

                    $setting->value = $value;
                    $setting->save();

                    Cache::forget("setting.{$key}");

                    // Same entry shape as SettingController::update().
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
        });

        return redirect()
            ->route('admin.settings.fee-distribution.index')
            ->with('success', 'Fee distribution updated successfully.');
    }
}
