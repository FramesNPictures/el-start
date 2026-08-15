<?php

use Fnp\ElStart\Models\AppAudit;
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
        Schema::dropIfExists(AppAudit::TABLE);
    }
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(AppAudit::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('event')->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
};
