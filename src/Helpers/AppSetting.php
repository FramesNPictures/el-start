<?php

namespace FNP\ElStart\Helpers;

use FNP\ElStart\Models\AppSettingModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AppSetting
{
    public static function getRaw(string $key, $default = null): mixed
    {
        $uuid = md5(strtolower($key));
        $rec = AppSettingModel::where('uuid', $uuid)->first();

        if (!$rec) {
            return $default;
        }

        return base64_decode(decrypt($rec->value));
    }

    public static function get(string $key, $default = null): mixed
    {
        $uuid = md5(strtolower($key));
        return Cache::remember(__CLASS__ . $uuid, 60 * 60, function () use ($default, $key) {
            return self::getRaw($key, $default);
        });
    }

    public static function set(string $key, $value)
    {
        $uuid = md5(strtolower($key));
        $value = base64_encode(encrypt($value));
        $rec = AppSettingModel::updateOrCreate(
            ['uuid' => $uuid],
            ['uuid' => $uuid, 'value' => $value]
        );

        Cache::set(__CLASS__ . $uuid, $value);
    }

    public static function forget(string $key)
    {
        $uuid = md5(strtolower($key));
        AppSettingModel::where('uuid', $key)->delete();
        Cache::forget(__CLASS__ . $uuid);
    }
}