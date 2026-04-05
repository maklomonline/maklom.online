<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->json('dead_stones')->nullable()->after('consecutive_passes');
            $table->boolean('score_confirmed_black')->default(false)->after('dead_stones');
            $table->boolean('score_confirmed_white')->default(false)->after('score_confirmed_black');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['dead_stones', 'score_confirmed_black', 'score_confirmed_white']);
        });
    }
};
