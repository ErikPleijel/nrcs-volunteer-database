<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    private const OLD_KEY = 'social.share_snippet';

    private const NEW_KEY = 'social.share_description';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $setting = Setting::where('key', self::OLD_KEY)->first();

        if ($setting) {
            $plainText = $setting->value;

            if (preg_match('/content="([^"]*)"/', (string) $setting->value, $matches)) {
                $plainText = $matches[1];
            }

            $setting->update([
                'key'         => self::NEW_KEY,
                'value'       => $plainText,
                'label'       => 'Social Share Description',
                'description' => 'Short text used as the page description when this site is shared on social media.',
            ]);
        }

        Cache::forget('setting.'.self::OLD_KEY);
        Cache::forget('setting.'.self::NEW_KEY);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $setting = Setting::where('key', self::NEW_KEY)->first();

        if ($setting) {
            $html = sprintf('<meta property="og:description" content="%s">', $setting->value);

            $setting->update([
                'key'         => self::OLD_KEY,
                'value'       => $html,
                'label'       => 'Social Share Snippet',
                'description' => 'HTML snippet used for social sharing meta tags.',
            ]);
        }

        Cache::forget('setting.'.self::OLD_KEY);
        Cache::forget('setting.'.self::NEW_KEY);
    }
};
