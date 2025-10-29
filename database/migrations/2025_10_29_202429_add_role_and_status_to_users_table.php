<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 25)
                ->default('subscriber')
                ->after('email')
                ->index();
            $table->timestamp('suspended_at')
                ->nullable()
                ->after('role')
                ->index();
            $table->timestamp('invited_at')
                ->nullable()
                ->after('suspended_at');
            $table->foreignId('invited_by_id')
                ->nullable()
                ->after('invited_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId !== null) {
            DB::table('users')
                ->where('id', $firstUserId)
                ->update(['role' => 'admin']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by_id');
            $table->dropColumn(['invited_at']);
            $table->dropIndex(['suspended_at']);
            $table->dropIndex(['role']);
            $table->dropColumn(['suspended_at', 'role']);
        });
    }
};
