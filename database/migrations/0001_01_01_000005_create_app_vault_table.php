<?php

use Fnp\ElStart\Models\AppVault;
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
        Schema::dropIfExists(AppVault::TABLE);
    }
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(AppVault::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->morphs('vaultable');
            $table->unsignedInteger('detail_eid');
            $table->text('value');
            $table->timestamps();

            $table->unique(['vaultable_type', 'vaultable_id', 'detail_eid']);
        });
    }
};
