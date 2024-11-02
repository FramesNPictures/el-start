<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Application Settings
        Schema::create('app_settings', function (Blueprint $table) {
            $table->char('uuid', 16)
                ->charset('binary')
                ->default(DB::raw('UUID_TO_BIN(UUID())'))
                ->primary();
            $table->string('key', 128)->index();
            $table->text('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
