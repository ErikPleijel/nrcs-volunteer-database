<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'autoload',
    ];

    /**
     * Get a setting by key, with optional default.
     */
    public static function get(string $key, $default = null)
    {
        return Cache::rememberForever("setting.{$key}", function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            if (! $setting) {
                return $default;
            }

            return $setting->castValue();
        });
    }

    /**
     * Convenience typed getters
     */
    public static function getInt(string $key, int $default = 0): int
    {
        return (int) static::get($key, $default);
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        return (bool) static::get($key, $default);
    }

    /**
     * Cast the value according to its type.
     */
    public function castValue()
    {
        return match ($this->type) {
            'int', 'integer' => (int) $this->value,
            'bool', 'boolean' => (bool) $this->value,
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Set value with optional auto-casting.
     */
    public function setValueAttribute($value): void
    {
        // If type is json, encode it
        if (in_array($this->type, ['json'])) {
            $this->attributes['value'] = json_encode($value);
        } else {
            $this->attributes['value'] = (string) $value;
        }
    }
}
