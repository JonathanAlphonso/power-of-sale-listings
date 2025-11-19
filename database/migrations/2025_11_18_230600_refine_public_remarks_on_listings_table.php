<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('listings')) {
            return;
        }

        $driver = DB::getDriverName();

        if (Schema::hasColumn('listings', 'public_remarks_full')) {
            // Prefer the full remarks column as the source of truth.
            try {
                DB::statement("UPDATE `listings` SET public_remarks = public_remarks_full WHERE public_remarks_full IS NOT NULL AND public_remarks_full <> ''");
            } catch (\Throwable) {
                // Best effort only; ignore driver-specific errors in tests.
            }

            // Widen public_remarks to hold full text where supported.
            if ($driver !== 'sqlite') {
                try {
                    DB::statement('ALTER TABLE `listings` MODIFY `public_remarks` LONGTEXT NOT NULL');
                } catch (\Throwable) {
                    // Ignore if the driver does not support this exact syntax.
                }
            }

            // Drop the redundant full column now that public_remarks is canonical.
            try {
                Schema::table('listings', function (Blueprint $table): void {
                    $table->dropColumn('public_remarks_full');
                });
            } catch (\Throwable) {
                // Some drivers (e.g. SQLite during tests) may not support dropping columns easily.
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('listings')) {
            return;
        }

        $driver = DB::getDriverName();

        if (! Schema::hasColumn('listings', 'public_remarks_full')) {
            try {
                if ($driver === 'sqlite') {
                    Schema::table('listings', function (Blueprint $table): void {
                        $table->text('public_remarks_full')->default('');
                    });
                } else {
                    DB::statement('ALTER TABLE `listings` ADD `public_remarks_full` LONGTEXT NOT NULL');
                }
            } catch (\Throwable) {
                // Ignore if the driver cannot easily add the column.
            }

            // Best-effort backfill from public_remarks.
            try {
                DB::statement('UPDATE `listings` SET public_remarks_full = public_remarks');
            } catch (\Throwable) {
                // ignore
            }
        }
    }
};

