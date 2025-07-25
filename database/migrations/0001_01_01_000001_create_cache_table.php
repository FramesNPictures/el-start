<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
            // --
            $table->index(['key', 'expiration'], 'idx_app_cache_key_expiration');
        });

        Schema::create('app_cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner')->index();
            $table->integer('expiration')->index();
            // --
            $table->index(['key', 'expiration'], 'idx_app_cache_locks_key_expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cache');
        Schema::dropIfExists('app_cache_locks');
    }
};
