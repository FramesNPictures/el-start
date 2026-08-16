<?php

use Fnp\ElStart\Models\AppDictionary;
use Fnp\ElStart\Services\DictionaryService;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        AppDictionary::query()->delete();
    }
    /**
     * Run the migrations.
     *
     * Writes down what every registered enum says at this point, so a fresh
     * database can answer for its own `_eid` columns from the first minute.
     *
     * A migration runs once, and enums keep changing. Store the dictionary
     * again whenever they do — from a deploy step, or from a migration of the
     * application holding this one line.
     */
    public function up(): void
    {
        app(DictionaryService::class)->store();
    }
};
