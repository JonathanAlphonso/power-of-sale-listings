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
        Schema::create('listing_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status_code', 64)->nullable();
            $table->string('status_label')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('changed_at')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_status_histories');
    }
};
