<?php

namespace FNP\ElStart\Models;

use FNP\ElStart\Enums\UserDetailType;
use FNP\ElStart\Helpers\UserDetails;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AppUserModelFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public function modelName()
    {
        return AppUserModel::class;
    }

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (AppUserModel $user) {
            // ...
        })->afterCreating(function (AppUserModel $user) {
//            UserDetails::set($user, UserDetailType::FIRST_NAME, fake()->name());
//            UserDetails::set($user, UserDetailType::LAST_NAME, fake()->lastName());
//            UserDetails::set($user, UserDetailType::PHONE_MOBILE, fake()->phoneNumber());
//            UserDetails::set($user, UserDetailType::ADDRESS, fake()->address());
        });
    }
}