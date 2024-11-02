<?php

namespace FNP\ElStart\Helpers;

use FNP\ElStart\Enums\UserDetailType;
use FNP\ElStart\Models\AppUserDetailModel;
use FNP\ElStart\Models\AppUserModel;

class AppDetails
{
    public static function set(AppUserModel $user, UserDetailType $type, $value, $tab = 0)
    {
        AppUserDetailModel::updateOrCreate(
            ['user_id' => $user->id, 'type' => $type, 'tab' => $tab],
            ['user_id' => $user->id, 'type' => $type, 'tab' => $tab, 'value' => $value],
        );
    }
}