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
        Schema::create('app_users', function (Blueprint $table) {
            $table->id();
            $table->char('uuid',16)->charset('binary')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('app_users_details', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->index();
            $table->char('key', 32)->charset('binary')->index();
            $table->text('value');
            $table->timestamps();
            $table->dateTime('expires_at')->nullable();
            $table->primary(['user_id', 'key']);
            $table->index('created_at');
            $table->index('updated_at');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('app_users_tokens');
    }
};
