<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_media', function (Blueprint $table): void {
            $table->string('stored_disk', 64)->nullable()->after('url');
            $table->string('stored_path', 2048)->nullable()->after('stored_disk');
            $table->timestamp('stored_at')->nullable()->after('stored_path');

            $table->index(['stored_disk', 'stored_at']);
        });
    }

    public function down(): void
    {
        Schema::table('listing_media', function (Blueprint $table): void {
            $table->dropIndex(['stored_disk', 'stored_at']);
            $table->dropColumn(['stored_disk', 'stored_path', 'stored_at']);
        });
    }
};
