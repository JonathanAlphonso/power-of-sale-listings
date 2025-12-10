<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes for commonly filtered columns in public search.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->index('property_type');
            $table->index('bedrooms');
            $table->index('bathrooms');
            $table->index('listed_at'); // Used for days on market sorting
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropIndex('listings_property_type_index');
            $table->dropIndex('listings_bedrooms_index');
            $table->dropIndex('listings_bathrooms_index');
            $table->dropIndex('listings_listed_at_index');
        });
    }
};
