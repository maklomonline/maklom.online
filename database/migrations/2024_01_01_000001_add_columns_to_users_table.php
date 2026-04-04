<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->unique()->after('name');
            $table->string('display_name', 60)->nullable()->after('username');
            $table->string('avatar')->nullable()->after('display_name');
            $table->string('rank', 10)->default('30k')->after('avatar');
            $table->integer('rank_points')->default(0)->after('rank');
            $table->text('bio')->nullable()->after('rank_points');
            $table->boolean('is_admin')->default(false)->after('bio');
            $table->boolean('is_banned')->default(false)->after('is_admin');
            $table->text('ban_reason')->nullable()->after('is_banned');
            $table->timestamp('banned_until')->nullable()->after('ban_reason');
            $table->timestamp('last_seen_at')->nullable()->after('banned_until');
            $table->string('locale', 5)->default('th')->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_username_unique');
            $table->dropColumn([
                'username', 'display_name', 'avatar', 'rank', 'rank_points',
                'bio', 'is_admin', 'is_banned', 'ban_reason', 'banned_until',
                'last_seen_at', 'locale',
            ]);
        });
    }
};
