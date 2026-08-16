<?php

use Fnp\ElStart\Models\AppUser;
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
        Schema::dropIfExists(AppUser::TABLE);
    }
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(AppUser::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('email_hash', 64)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
