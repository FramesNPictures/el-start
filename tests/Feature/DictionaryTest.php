<?php

use Fnp\ElStart\Enums\ESystemTokenType;
use Fnp\ElStart\Models\AppDictionary;
use Fnp\ElStart\Services\DictionaryService;
use Fnp\ElStart\Tests\Stubs\DictionaryStubModule;
use Fnp\ElStart\Tests\Stubs\EStringBackedStub;
use Fnp\ElStart\Tests\Stubs\ETokenTypeStub;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->dictionary = app(DictionaryService::class);
});

afterEach(function (): void {
    // Relation::$morphMap is static and would otherwise leak between tests.
    Relation::morphMap([], false);

    DictionaryStubModule::$enums = [];
});

it('has the dictionary table', function (): void {
    expect(Schema::hasTable(AppDictionary::TABLE))->toBeTrue()
        ->and(Schema::getColumnListing(AppDictionary::TABLE))
        ->toEqualCanonicalizing(['id', 'entity', 'name', 'value', 'created_at', 'updated_at']);
});

it('resolves out of the container as a single instance', function (): void {
    expect(app(DictionaryService::class))->toBeInstanceOf(DictionaryService::class)
        ->and(app(DictionaryService::class))->toBe(app(DictionaryService::class));
});

it('is written by the migration that ships with it', function (): void {
    // Nothing in this test stored anything: migrating did.
    expect(AppDictionary::query()->ofEnum(ESystemTokenType::class)->count())
        ->toBe(count(ESystemTokenType::cases()));
});

it('gathers the enums of every module that offers them', function (): void {
    $this->dictionary->store();

    expect($this->dictionary->registered())
        ->toContain(ESystemTokenType::class);
});

it('registers an enum once, however often it is asked', function (): void {
    $before = count($this->dictionary->registered());

    $this->dictionary->register(ETokenTypeStub::class);
    $this->dictionary->register(ETokenTypeStub::class);

    expect($this->dictionary->registered())->toHaveCount($before + 1);
});

it('takes integer backed enums only', function (): void {
    expect(fn () => $this->dictionary->register(EStringBackedStub::class))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->dictionary->register(AppDictionary::class))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->dictionary->register('Nothing\\Like\\This'))
        ->toThrow(InvalidArgumentException::class);
});

it('writes every case down against its class and name', function (): void {
    $written = $this->dictionary->store();

    expect($written)->toBeGreaterThanOrEqual(count(ESystemTokenType::cases()))
        ->and(AppDictionary::query()->ofEnum(ESystemTokenType::class)
            ->orderBy('name')->pluck('value', 'name')->all())
        ->toBe([
            'password-reset' => ESystemTokenType::PasswordReset->value,
            'remember' => ESystemTokenType::Remember->value,
        ]);
});

it('writes an enum down by its class map alias where it has one', function (): void {
    $this->dictionary->store();

    // The enums of this module are aliased, so that is what the table says.
    expect(AppDictionary::query()->where('entity', 'token.type')->count())
        ->toBe(count(ESystemTokenType::cases()))
        ->and(AppDictionary::query()->where('entity', ESystemTokenType::class)->count())->toBe(0)
        // And are still found by the class they were registered as.
        ->and(AppDictionary::query()->ofEnum(ESystemTokenType::class)->count())
        ->toBe(count(ESystemTokenType::cases()));
});

it('writes an enum down by its class name where it has no alias', function (): void {
    $this->dictionary->register(ETokenTypeStub::class);
    $this->dictionary->store();

    expect(AppDictionary::query()->where('entity', ETokenTypeStub::class)->count())
        ->toBe(count(ETokenTypeStub::cases()))
        ->and(AppDictionary::query()->ofEnum(ETokenTypeStub::class)->count())
        ->toBe(count(ETokenTypeStub::cases()));
});

it('writes the case names in kebab-case', function (): void {
    $this->dictionary->store();

    expect(AppDictionary::query()->ofEnum(ESystemTokenType::class)->pluck('name')->all())
        ->toEqualCanonicalizing(['password-reset', 'remember']);
});

it('says the same thing however often it is stored', function (): void {
    $this->dictionary->store();
    $rows = AppDictionary::count();

    $this->dictionary->store();

    expect(AppDictionary::count())->toBe($rows)
        ->and(AppDictionary::query()->ofEnum(ESystemTokenType::class)->count())
        ->toBe(count(ESystemTokenType::cases()));
});

it('drops a case that is not one any more', function (): void {
    $this->dictionary->store();

    AppDictionary::query()->create([
        'entity' => ESystemTokenType::class,
        'name' => 'gone',
        'value' => 999,
    ]);

    $this->dictionary->store();

    expect(AppDictionary::query()->ofEnum(ESystemTokenType::class)->pluck('name')->all())
        ->not->toContain('gone');
});

it('leaves the enums nobody registered alone', function (): void {
    AppDictionary::query()->create([
        'entity' => EStringBackedStub::class,
        'name' => 'one',
        'value' => 1,
    ]);

    $this->dictionary->store();

    expect(AppDictionary::query()->ofEnum(EStringBackedStub::class)->count())->toBe(1);
});

it('registers what a module asks it to', function (): void {
    DictionaryStubModule::$enums = [ETokenTypeStub::class];

    app()->register(DictionaryStubModule::class);

    // The module was registered, but nothing asked it for its enums yet.
    expect($this->dictionary->registered())->not->toContain(ETokenTypeStub::class);

    $this->dictionary->store();

    expect($this->dictionary->registered())->toContain(ETokenTypeStub::class);

    expect(AppDictionary::query()->ofEnum(ETokenTypeStub::class)
        ->orderBy('name')->pluck('value', 'name')->all())
        ->toBe([
            'api' => ETokenTypeStub::Api->value,
            'invitation' => ETokenTypeStub::Invitation->value,
            'reset' => ETokenTypeStub::Reset->value,
        ]);
});

it('turns a module down when it registers something else', function (): void {
    DictionaryStubModule::$enums = [EStringBackedStub::class];

    app()->register(DictionaryStubModule::class);

    // Nothing is asked of the module until the moment of writing, so that is
    // where a module offering the wrong kind of enum is refused.
    expect(fn () => $this->dictionary->store())->toThrow(InvalidArgumentException::class);
});
