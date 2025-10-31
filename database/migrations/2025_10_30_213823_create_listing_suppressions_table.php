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
        Schema::create('listing_suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('release_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->timestamp('suppressed_at')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('released_at')->nullable()->index();
            $table->string('release_reason')->nullable();
            $table->text('release_notes')->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'released_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_suppressions');
    }
};
