<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Building features
            $table->json('basement')->nullable()->after('structure_type');
            $table->boolean('basement_yn')->nullable()->after('basement');
            $table->json('foundation_details')->nullable()->after('basement_yn');
            $table->json('construction_materials')->nullable()->after('foundation_details');
            $table->json('roof')->nullable()->after('construction_materials');
            $table->json('architectural_style')->nullable()->after('roof');

            // Heating & Cooling
            $table->string('heating_type')->nullable()->after('architectural_style');
            $table->string('heating_source')->nullable()->after('heating_type');
            $table->json('cooling')->nullable()->after('heating_source');

            // Fireplace
            $table->boolean('fireplace_yn')->nullable()->after('cooling');
            $table->json('fireplace_features')->nullable()->after('fireplace_yn');
            $table->unsignedSmallInteger('fireplaces_total')->nullable()->after('fireplace_features');

            // Parking & Garage
            $table->string('garage_type')->nullable()->after('fireplaces_total');
            $table->boolean('garage_yn')->nullable()->after('garage_type');
            $table->unsignedSmallInteger('garage_parking_spaces')->nullable()->after('garage_yn');
            $table->unsignedSmallInteger('parking_total')->nullable()->after('garage_parking_spaces');
            $table->json('parking_features')->nullable()->after('parking_total');

            // Pool & Exterior
            $table->json('pool_features')->nullable()->after('parking_features');
            $table->json('exterior_features')->nullable()->after('pool_features');
            $table->json('interior_features')->nullable()->after('exterior_features');

            // Utilities
            $table->string('water')->nullable()->after('interior_features');
            $table->json('sewer')->nullable()->after('water');

            // Room details
            $table->unsignedSmallInteger('bedrooms_above_grade')->nullable()->after('bedrooms_possible');
            $table->unsignedSmallInteger('bedrooms_below_grade')->nullable()->after('bedrooms_above_grade');
            $table->unsignedSmallInteger('rooms_total')->nullable()->after('bathrooms');
            $table->unsignedSmallInteger('kitchens_total')->nullable()->after('rooms_total');

            // Washroom details (up to 5 washrooms with type, level, pieces)
            $table->json('washrooms')->nullable()->after('kitchens_total');

            // Listing office/brokerage info
            $table->string('list_office_name')->nullable()->after('public_remarks');
            $table->string('list_office_phone')->nullable()->after('list_office_name');
            $table->string('list_aor')->nullable()->after('list_office_phone');

            // Additional property details
            $table->string('cross_street')->nullable()->after('postal_code');
            $table->text('directions')->nullable()->after('cross_street');
            $table->string('zoning')->nullable()->after('directions');
            $table->unsignedSmallInteger('tax_year')->nullable()->after('tax_annual_amount');

            // Virtual tour
            $table->string('virtual_tour_url')->nullable()->after('list_aor');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn([
                'basement',
                'basement_yn',
                'foundation_details',
                'construction_materials',
                'roof',
                'architectural_style',
                'heating_type',
                'heating_source',
                'cooling',
                'fireplace_yn',
                'fireplace_features',
                'fireplaces_total',
                'garage_type',
                'garage_yn',
                'garage_parking_spaces',
                'parking_total',
                'parking_features',
                'pool_features',
                'exterior_features',
                'interior_features',
                'water',
                'sewer',
                'bedrooms_above_grade',
                'bedrooms_below_grade',
                'rooms_total',
                'kitchens_total',
                'washrooms',
                'list_office_name',
                'list_office_phone',
                'list_aor',
                'cross_street',
                'directions',
                'zoning',
                'tax_year',
                'virtual_tour_url',
            ]);
        });
    }
};
