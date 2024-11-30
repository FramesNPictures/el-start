<?php

use FNP\ElStart\Helpers\AppBlueprint;
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
            $table = AppBlueprint::make($table);
            $table->id();
            $table->string('key', 128)->index();
            $table->text('value');
            $table->timestamps();
        });

        // Application Objects
        Schema::create('app_objects', function (Blueprint $table) {
            $table = AppBlueprint::make($table);
            $table->id();
            $table->text('class');
            $table->timestamps();
        });

        // Logs
        Schema::create('app_logs', function(Blueprint $table) {
            $table = AppBlueprint::make($table);
            $table->binaryUuid('uuid')->default(DB::raw('(UUID_TO_BIN(UUID()))'))->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('object_id')->nullable()->foreignId('app_objects')->index();
            $table->text('data');
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
