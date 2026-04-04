<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->tinyInteger('board_size')->default(19);
            $table->enum('clock_type', ['byoyomi', 'fischer'])->default('byoyomi');
            $table->integer('main_time')->default(600);
            $table->tinyInteger('byoyomi_periods')->nullable();
            $table->integer('byoyomi_seconds')->nullable();
            $table->integer('fischer_increment')->nullable();
            $table->decimal('komi', 4, 1)->default(6.5);
            $table->tinyInteger('handicap')->default(0);
            $table->boolean('is_private')->default(false);
            $table->string('password')->nullable();
            $table->enum('status', ['waiting', 'playing', 'finished', 'cancelled'])->default('waiting');
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->smallInteger('max_observers')->default(50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_rooms');
    }
};
