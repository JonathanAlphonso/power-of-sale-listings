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
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->string('board_code', 32);
            $table->string('mls_number', 32);
            $table->string('status_code', 32)->nullable();
            $table->string('display_status', 64)->nullable();
            $table->string('availability', 32)->nullable();
            $table->string('property_class', 32)->nullable();
            $table->string('property_type')->nullable();
            $table->string('property_style')->nullable();
            $table->string('sale_type', 16)->nullable();
            $table->string('currency', 16)->default('CAD');
            $table->string('street_number', 32)->nullable();
            $table->string('street_name')->nullable();
            $table->string('street_address')->nullable();
            $table->string('unit_number', 32)->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('neighbourhood')->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('province', 16)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('days_on_market')->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bedrooms_possible')->nullable();
            $table->decimal('bathrooms', 4, 1)->nullable();
            $table->unsignedInteger('square_feet')->nullable();
            $table->string('square_feet_text')->nullable();
            $table->decimal('list_price', 12, 2)->nullable();
            $table->decimal('original_list_price', 12, 2)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('price_low', 12, 2)->nullable();
            $table->decimal('price_per_square_foot', 12, 2)->nullable();
            $table->integer('price_change')->nullable();
            $table->tinyInteger('price_change_direction')->nullable();
            $table->boolean('is_address_public')->default(true);
            $table->string('parcel_id')->nullable();
            $table->timestamp('modified_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['board_code', 'mls_number']);
            $table->index(['city', 'province']);
            $table->index('postal_code');
            $table->index('list_price');
            $table->index(['latitude', 'longitude']);
            $table->index('status_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
