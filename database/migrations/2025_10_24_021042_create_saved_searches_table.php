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
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('notification_channel', 32)->default('email');
            $table->string('notification_frequency', 32)->default('instant');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_ran_at')->nullable();
            $table->timestamp('last_matched_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->json('filters');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
