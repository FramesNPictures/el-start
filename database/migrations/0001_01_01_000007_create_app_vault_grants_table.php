<?php

use Fnp\ElStart\Models\AppVault;
use Fnp\ElStart\Models\AppVaultGrant;
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
        Schema::dropIfExists(AppVaultGrant::TABLE);
    }
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(AppVaultGrant::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vault_id')->constrained(AppVault::TABLE)->cascadeOnDelete();
            $table->morphs('keyable');
            $table->text('sealed_key');
            $table->timestamps();

            $table->unique(['vault_id', 'keyable_type', 'keyable_id']);
        });
    }
};
