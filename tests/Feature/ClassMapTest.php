<?php

use Fnp\ElModule\Helpers\HClassMap;
use Fnp\ElStart\Events\UserLoggedIn;
use Fnp\ElStart\Events\UserRegistered;
use Fnp\ElStart\Models\AppUser;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;

it('aliases the user model', function (): void {
    expect(HClassMap::getAlias(AppUser::class))->toBe('user')
        ->and(HClassMap::getClass('user'))->toBe(AppUser::class);
});

it('aliases every event of this module', function (): void {
    $events = collect(File::files(__DIR__ . '/../../src/Events'))
        ->map(fn ($file): string => 'Fnp\\ElStart\\Events\\' . $file->getFilenameWithoutExtension())
        ->reject(fn (string $class): bool => (new ReflectionClass($class))->isAbstract());

    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        expect(HClassMap::getAlias($event))
            ->not->toBeNull("{$event} has no alias in ElStartModule::defineClassMap()");
    }
});

it('groups the aliases by what they belong to', function (): void {
    expect(HClassMap::getAlias(UserRegistered::class))->toBe('user.registered')
        ->and(HClassMap::getAlias(UserLoggedIn::class))->toBe('user.logged-in')
        ->and(array_keys(Relation::morphMap()))
        ->each->toMatch('/^[a-z]+(\.[a-z-]+)?$/');
});
