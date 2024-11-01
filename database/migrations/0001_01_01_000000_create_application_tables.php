<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Application Settings
        Schema::create('app_settings', function(Blueprint $table) {
            $table->char('uuid', 16)->charset('binary')->primary();
            $table->char('key', 32)->charset('binary')->index();
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
