<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite path: avoid engine-specific ALTER syntax; add column if missing and backfill values
            if (! Schema::hasColumn('listings', 'public_remarks')) {
                Schema::table('listings', function ($table): void {
                    $table->string('public_remarks', 4096)->default('');
                });
            }

            DB::statement("UPDATE listings SET listing_key = COALESCE(listing_key, external_id, mls_number, '')");
            DB::statement("UPDATE listings SET transaction_type = COALESCE(transaction_type, 'For Sale')");
            DB::statement("UPDATE listings SET availability = COALESCE(availability, 'Unavailable')");
            DB::statement("UPDATE listings SET public_remarks = COALESCE(public_remarks, '')");

            // Note: SQLite cannot easily modify existing columns to NOT NULL without table rebuild.
            // Application-level fills ensure these values are never null after imports.
            return;
        }

        // MySQL path: strong constraints + defaults
        if (! Schema::hasColumn('listings', 'public_remarks')) {
            DB::statement("ALTER TABLE `listings` ADD `public_remarks` varchar(4096) NOT NULL DEFAULT '' AFTER `street_address`");
        }

        DB::statement("UPDATE `listings` SET listing_key = COALESCE(listing_key, external_id, mls_number, '')");
        DB::statement("UPDATE `listings` SET transaction_type = COALESCE(transaction_type, 'For Sale')");
        DB::statement("UPDATE `listings` SET availability = COALESCE(availability, 'Unavailable')");
        DB::statement("UPDATE `listings` SET public_remarks = COALESCE(public_remarks, '')");

        DB::statement('ALTER TABLE `listings` MODIFY `listing_key` varchar(64) NOT NULL');
        DB::statement("ALTER TABLE `listings` MODIFY `transaction_type` varchar(64) NOT NULL DEFAULT 'For Sale'");
        DB::statement("ALTER TABLE `listings` MODIFY `availability` varchar(32) NOT NULL DEFAULT 'Unavailable'");
        DB::statement("ALTER TABLE `listings` MODIFY `public_remarks` varchar(4096) NOT NULL DEFAULT ''");
    }

    public function down(): void
    {
        // Relax constraints (keep data). Make columns nullable again.
        if (Schema::hasColumn('listings', 'listing_key')) {
            DB::statement('ALTER TABLE `listings` MODIFY `listing_key` varchar(64) NULL');
        }
        if (Schema::hasColumn('listings', 'transaction_type')) {
            DB::statement('ALTER TABLE `listings` MODIFY `transaction_type` varchar(64) NULL');
        }
        if (Schema::hasColumn('listings', 'availability')) {
            DB::statement('ALTER TABLE `listings` MODIFY `availability` varchar(32) NULL');
        }
        if (Schema::hasColumn('listings', 'public_remarks')) {
            DB::statement('ALTER TABLE `listings` MODIFY `public_remarks` varchar(4096) NULL');
        }
    }
};
