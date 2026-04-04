<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('challenged_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('board_size')->default(19);
            $table->string('clock_type')->default('byoyomi');
            $table->integer('main_time')->default(600);
            $table->tinyInteger('byoyomi_periods')->default(3);
            $table->integer('byoyomi_seconds')->default(30);
            $table->integer('fischer_increment')->default(10);
            $table->tinyInteger('handicap')->default(0);
            $table->enum('status', ['pending', 'accepted', 'declined', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
