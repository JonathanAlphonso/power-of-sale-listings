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
            // Land size fields
            $table->decimal('lot_size_area', 12, 2)->nullable()->after('square_feet_text');
            $table->string('lot_size_units', 50)->nullable()->after('lot_size_area');
            $table->decimal('lot_depth', 10, 2)->nullable()->after('lot_size_units');
            $table->decimal('lot_width', 10, 2)->nullable()->after('lot_depth');

            // Building details
            $table->unsignedSmallInteger('stories')->nullable()->after('lot_width');
            $table->string('approximate_age', 50)->nullable()->after('stories');
            $table->string('structure_type', 100)->nullable()->after('approximate_age');

            // Financial fields
            $table->decimal('tax_annual_amount', 12, 2)->nullable()->after('structure_type');
            $table->decimal('association_fee', 10, 2)->nullable()->after('tax_annual_amount');

            // Indexes for commonly filtered fields
            $table->index('lot_size_area');
            $table->index('stories');
            $table->index('approximate_age');
            $table->index('tax_annual_amount');
            $table->index('association_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['lot_size_area']);
            $table->dropIndex(['stories']);
            $table->dropIndex(['approximate_age']);
            $table->dropIndex(['tax_annual_amount']);
            $table->dropIndex(['association_fee']);

            $table->dropColumn([
                'lot_size_area',
                'lot_size_units',
                'lot_depth',
                'lot_width',
                'stories',
                'approximate_age',
                'structure_type',
                'tax_annual_amount',
                'association_fee',
            ]);
        });
    }
};
