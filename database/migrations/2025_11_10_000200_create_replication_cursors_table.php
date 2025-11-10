<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replication_cursors', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 100)->unique();
            $table->timestamp('last_timestamp')->nullable();
            $table->string('last_key', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replication_cursors');
    }
};
