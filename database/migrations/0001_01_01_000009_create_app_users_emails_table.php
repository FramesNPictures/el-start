<?php

use Fnp\ElStart\Models\AppUser;
use Fnp\ElStart\Models\AppUserEmail;
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
        Schema::dropIfExists(AppUserEmail::TABLE);
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(AppUserEmail::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained(AppUser::TABLE)->cascadeOnDelete();
            $table->string('email');
            $table->timestamp('until');
            $table->timestamps();

            $table->index(['user_id', 'until']);
        });
    }
};
