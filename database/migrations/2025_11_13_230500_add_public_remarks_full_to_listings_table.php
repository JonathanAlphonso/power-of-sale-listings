<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (! Schema::hasColumn('listings', 'public_remarks_full')) {
            if ($driver === 'sqlite') {
                Schema::table('listings', function ($table): void {
                    $table->text('public_remarks_full')->default('');
                });
            } else {
                // MySQL / others: prefer LONGTEXT to store full remarks
                DB::statement('ALTER TABLE `listings` ADD `public_remarks_full` LONGTEXT NOT NULL');
            }

            // Backfill existing rows from public_remarks
            try {
                DB::statement('UPDATE `listings` SET public_remarks_full = public_remarks');
            } catch (\Throwable) {
                // ignore if table empty or driver specifics
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('listings', 'public_remarks_full')) {
            try {
                Schema::table('listings', function ($table): void {
                    $table->dropColumn('public_remarks_full');
                });
            } catch (\Throwable) {
                // ignore if driver cannot easily drop in tests
            }
        }
    }
};
