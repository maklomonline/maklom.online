<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('game_rooms')->cascadeOnDelete();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invitee_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'declined', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('user_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->integer('games_played')->default(0);
            $table->integer('games_won')->default(0);
            $table->integer('games_lost')->default(0);
            $table->integer('games_drawn')->default(0);
            $table->integer('win_streak')->default(0);
            $table->integer('best_win_streak')->default(0);
            $table->integer('total_moves')->default(0);
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('target_type', 50)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('notes')->nullable();
            $table->string('ip_address', 45);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('user_stats');
        Schema::dropIfExists('game_invites');
    }
};
