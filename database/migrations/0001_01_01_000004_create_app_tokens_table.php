<?php

use Fnp\ElStart\Models\AppToken;
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
        Schema::dropIfExists(AppToken::TABLE);
    }
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(AppToken::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->unsignedSmallInteger('type_eid');
            $table->string('value');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['type_eid', 'value']);
        });
    }
};
