<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('game_rooms')->cascadeOnDelete();
            $table->foreignId('black_player_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('white_player_id')->nullable()->constrained('users')->nullOnDelete();
            $table->tinyInteger('board_size')->default(19);
            $table->decimal('komi', 4, 1)->default(6.5);
            $table->tinyInteger('handicap')->default(0);
            $table->enum('clock_type', ['byoyomi', 'fischer'])->default('byoyomi');
            $table->integer('main_time')->default(600);
            $table->tinyInteger('byoyomi_periods')->nullable();
            $table->integer('byoyomi_seconds')->nullable();
            $table->integer('fischer_increment')->nullable();
            $table->integer('black_time_left')->default(600);
            $table->integer('white_time_left')->default(600);
            $table->tinyInteger('black_periods_left')->nullable();
            $table->tinyInteger('white_periods_left')->nullable();
            $table->enum('current_color', ['black', 'white'])->default('black');
            $table->smallInteger('move_number')->default(0);
            $table->json('board_state');
            $table->smallInteger('captures_black')->default(0);
            $table->smallInteger('captures_white')->default(0);
            $table->string('ko_point', 5)->nullable();
            $table->tinyInteger('consecutive_passes')->default(0);
            $table->enum('status', ['active', 'scoring', 'finished', 'aborted'])->default('active');
            $table->string('result', 20)->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('end_reason', ['resign', 'timeout', 'score', 'agreement', 'abort'])->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('game_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->smallInteger('move_number');
            $table->enum('color', ['black', 'white']);
            $table->string('coordinate', 5)->nullable();
            $table->json('captured_stones')->nullable();
            $table->integer('time_spent')->default(0);
            $table->integer('time_left_after')->default(0);
            $table->tinyInteger('periods_left_after')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['game_id', 'move_number']);
        });

        Schema::create('game_observers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();

            $table->unique(['game_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_observers');
        Schema::dropIfExists('game_moves');
        Schema::dropIfExists('games');
    }
};
