<?php

use Fnp\ElStart\Models\AppVaultKey;
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
        Schema::dropIfExists(AppVaultKey::TABLE);
    }
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(AppVaultKey::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->morphs('keyable');
            $table->text('public_key');
            $table->text('secret_key');
            $table->timestamps();

            $table->unique(['keyable_type', 'keyable_id']);
        });
    }
};
