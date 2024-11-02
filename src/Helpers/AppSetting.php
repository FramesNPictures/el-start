<?php

namespace FNP\ElStart\Helpers;

use FNP\ElStart\Models\AppSettingModel;
use Illuminate\Support\Facades\Cache;

class AppSetting
{
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
        $cacheTTL = 60 * 60 * 24; // An hour
        $cacheKey = self::getCacheKey($key);
        return Cache::remember($cacheKey, $cacheTTL, function () use ($default, $key) {
            return self::getRaw($key, $default);
        });
    }

    public static function set(string $key, $value)
    {
        $cacheTTL = 60 * 60 * 24; // An hour
        $cacheKey = self::getCacheKey($key);
        $rec = AppSettingModel::updateOrCreate(
            ['key' => $key],
            ['key' => $key, 'value' => $value]
        );
        Cache::set($cacheKey, $value, $cacheTTL);
    }

    public static function forget(string $key)
    {
        $cacheKey = self::getCacheKey($key);
        AppSettingModel::where('uuid', $key)->delete();
        Cache::forget($cacheKey);
    }

    /**
     * @param string $key
     * @return string
     */
    public static function getCacheKey(string $key): string
    {
        $cacheKey = implode('//', [__CLASS__, $key]);
        return $cacheKey;
    }
}