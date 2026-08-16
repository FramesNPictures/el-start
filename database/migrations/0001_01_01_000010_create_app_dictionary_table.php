<?php

use Fnp\ElStart\Models\AppDictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(AppDictionary::TABLE);
    }
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(AppDictionary::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('entity');
            $table->string('name');
            $table->integer('value');
            $table->timestamps();

            $table->unique(['entity', 'name']);
            $table->index(['entity', 'value']);
        });
    }
};
