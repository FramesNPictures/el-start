<?php

namespace Fnp\ElStart\Database\Factories;

use Fnp\ElStart\Models\AppUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Makes a user the way registering one does: a name, an address nobody else
 * has, and a password to log them in with.
 *
 * @extends Factory<AppUser>
 */
class AppUserFactory extends Factory
{
    /**
     * The password every user is made with, and the one to log them in with.
     * Set it once, before making anybody, where a suite wants another.
     */
    public static string $password = 'password';

    protected $model = AppUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => static::$password,
        ];
    }

    /**
     * A user who never confirmed their address.
     */
    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    /**
     * A user who confirmed their address.
     */
    public function verified(): static
    {
        return $this->state(['email_verified_at' => Carbon::now()]);
    }

    /**
     * Give the user an address of your choosing rather than a made up one.
     *
     * @param  string  $email  Address to put on the row
     */
    public function withEmail(string $email): static
    {
        return $this->state(['email' => $email]);
    }

    /**
     * Give the user a name of your choosing rather than a made up one.
     *
     * @param  string  $name  Name to put on the row
     */
    public function withName(string $name): static
    {
        return $this->state(['name' => $name]);
    }
}
