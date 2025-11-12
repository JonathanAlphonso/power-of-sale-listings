<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('listed_at')->nullable()->after('modified_at');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(<<<'SQL'
                UPDATE listings
                SET listed_at = DATE_SUB(
                    COALESCE(updated_at, created_at, NOW()),
                    INTERVAL COALESCE(days_on_market, 0) DAY
                )
                WHERE listed_at IS NULL AND days_on_market IS NOT NULL
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('listed_at');
        });
    }
};
