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
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('suppressed_at')->nullable()->index()->after('ingestion_batch_id');
            $table->timestamp('suppression_expires_at')->nullable()->index()->after('suppressed_at');
            $table->foreignId('suppressed_by_user_id')->nullable()->after('suppression_expires_at')->constrained('users')->nullOnDelete();
            $table->string('suppression_reason')->nullable()->after('suppressed_by_user_id');
            $table->text('suppression_notes')->nullable()->after('suppression_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropForeign(['suppressed_by_user_id']);
            $table->dropColumn([
                'suppressed_at',
                'suppression_expires_at',
                'suppressed_by_user_id',
                'suppression_reason',
                'suppression_notes',
            ]);
        });
    }
};
