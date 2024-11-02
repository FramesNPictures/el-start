<?php

namespace FNP\ElStart\Helpers;

use FNP\ElStart\Models\AppSettingModel;
use Illuminate\Support\Facades\Cache;

class AppSetting
{
    protected const CACHE_TTL = 60 * 60 * 24; // A DAY

    public static function getRaw(string $key, $default = null): mixed
    {
        $rec = AppSettingModel::where('key', $key)->first();

        if (!$rec) {
            return $default;
        }

        return $rec->value;
    }

    public static function get(string $key, $default = null): mixed
    {
        return Cache::remember(
            self::cacheKey($key), self::CACHE_TTL,
            function () use ($default, $key) {
                return self::getRaw($key, $default);
            }
        );
    }

    public static function set(string $key, $value)
    {
        $rec = AppSettingModel::updateOrCreate(
            ['key' => $key],
            ['key' => $key, 'value' => $value]
        );
        Cache::set(self::cacheKey($key), $value, self::CACHE_TTL);
    }

    public static function forget(string $key)
    {
        AppSettingModel::where('key', $key)->delete();
        Cache::forget(self::cacheKey($key));
    }

    public static function cacheKey(string $key): string
    {
        return implode('//', [__CLASS__, $key]);
    }
}