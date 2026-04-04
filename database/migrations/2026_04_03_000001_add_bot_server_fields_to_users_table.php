<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('bot_api_token', 80)->nullable()->unique()->after('bot_level');
            $table->boolean('bot_online')->default(false)->after('bot_api_token');
            $table->timestamp('bot_last_heartbeat')->nullable()->after('bot_online');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bot_api_token', 'bot_online', 'bot_last_heartbeat']);
        });
    }
};
