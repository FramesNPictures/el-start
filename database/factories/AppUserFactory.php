<?php

namespace Fnp\ElStart\Database\Factories;

use Fnp\ElStart\Enums\ESystemVaultDetail;
use Fnp\ElStart\Models\AppUser;
use Fnp\ElStart\Services\VaultService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Makes a user the way registering one does: the row carries the hash of an
 * address, and the address itself, with the name, goes into the vault.
 *
 * Creating one leaves the vault unlocked as that user, because that is the
 * only moment their password is around to open it with. Lock it afterwards
 * where a test needs to start from a closed vault.
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
     * The name and address behind a hash, held until the user they belong to
     * exists and the vault can take them.
     *
     * @var array<string, array{email: string, name: string}>
     */
    protected static array $pending = [];

    /**
     * Put the name and the address of the user where they belong.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (AppUser $user): void {
            $details = static::$pending[$user->email_hash] ?? null;

            if ($details === null) {
                return;
            }

            unset(static::$pending[$user->email_hash]);

            app(VaultService::class)->unlock($user, static::$password);

            $user->putVault(ESystemVaultDetail::Email, $details['email']);
            $user->putVault(ESystemVaultDetail::Name, $details['name']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->remember(
            $this->faker->unique()->safeEmail(),
            $this->faker->name(),
        );
    }

    /**
     * The factory is trusted, so it writes the columns the model guards
     * against a request — the hash above all, which is what a user is found by.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): Model
    {
        $model = $this->modelName();

        return (new $model())->forceFill($attributes);
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
     * @param  string  $email  Address to hash into the row and put in the vault
     */
    public function withEmail(string $email): static
    {
        return $this->state(fn (array $attributes): array => $this->remember($email, $this->forget($attributes)['name'] ?? $this->faker->name()));
    }

    /**
     * Give the user a name of your choosing rather than a made up one.
     *
     * @param  string  $name  Name to put in the vault
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes): array => $this->remember(
            $this->forget($attributes)['email'] ?? $this->faker->unique()->safeEmail(),
            $name,
        ));
    }

    /**
     * Take the details of an address that is being replaced back out again.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{email?: string, name?: string}
     */
    protected function forget(array $attributes): array
    {
        $hash = $attributes['email_hash'] ?? null;
        $details = static::$pending[$hash] ?? [];

        unset(static::$pending[$hash]);

        return $details;
    }

    /**
     * Hold on to a name and an address until the user exists.
     *
     * @return array<string, mixed>
     */
    protected function remember(string $email, string $name): array
    {
        $hash = AppUser::hashEmail($email);

        static::$pending[$hash] = ['email' => $email, 'name' => $name];

        return [
            'email_hash' => $hash,
            'password' => static::$password,
        ];
    }
}
