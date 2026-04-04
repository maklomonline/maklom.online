<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Tracks exactly when the last move/pass was processed server-side.
            // Used by ClockService to compute secondsSpent accurately.
            $table->timestamp('last_move_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('last_move_at');
        });
    }
};
