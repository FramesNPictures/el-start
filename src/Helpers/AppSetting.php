<?php

namespace FNP\ElStart\Helpers;

use FNP\ElStart\Models\AppSettingModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AppSetting
{
    public static function getRaw(string $key, $default = null): mixed
    {
        $key = md5(strtolower($key));
        $rec = AppSettingModel::where('key', $key)->first();

        if (!$rec) {
            return $default;
        }

        return $rec->value;
    }

    public static function get(string $key, $default = null): mixed
    {
        $key = md5(strtolower($key));
        return Cache::remember($key, 60 * 60, function () use ($default, $key) {
            return self::getRaw($key, $default);
        });
    }

    public static function set(string $key, $value)
    {
        $uuid = Str::uuid();
        $key = md5(strtolower($key));
        $rec = AppSettingModel::updateOrCreate(
            ['key' => $key],
            ['key' => $key, 'value' => $value, 'uuid' => $uuid]
        );

        Cache::set($key, $value);
    }

    public static function forget(string $key)
    {
        $key = md5(strtolower($key));
        AppSettingModel::where('key', $key)->delete();
        Cache::forget($key);
    }
}