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
            if (! Schema::hasColumn('listings', 'listing_key')) {
                $table->string('listing_key', 64)->nullable()->after('external_id')->index();
            }

            if (! Schema::hasColumn('listings', 'transaction_type')) {
                $table->string('transaction_type', 64)->nullable()->after('display_status')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if (Schema::hasColumn('listings', 'transaction_type')) {
                $table->dropIndex(['transaction_type']);
                $table->dropColumn('transaction_type');
            }
            if (Schema::hasColumn('listings', 'listing_key')) {
                $table->dropIndex(['listing_key']);
                $table->dropColumn('listing_key');
            }
        });
    }
};
