<?php

namespace FNP\ElStart\Helpers;

use Fnp\ElHelper\Obj;
use FNP\ElStart\Contracts\Registerable;
use FNP\ElStart\Enums\UserDetailType;
use FNP\ElStart\Models\AppUserDetailModel;
use FNP\ElStart\Models\AppUserModel;

class UserDetails
{
    public static function set(AppUserModel $user, Registerable $object)
    {
        $properties = Obj::properties($object, Obj::PROPERTIES_PUBLIC);

        foreach ($properties as $prop) {

        }


        AppUserDetailModel::updateOrCreate(
            ['user_id' => $user->id, 'type' => $type, 'tab' => $tab],
            ['user_id' => $user->id, 'type' => $type, 'tab' => $tab, 'value' => $value],
        );
    }
}