<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('game_annotations');

        Schema::create('game_annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->json('payload');
            $table->unsignedInteger('positions_count')->default(0);
            $table->string('last_position_key', 80)->nullable();
            $table->timestamps();

            $table->index(['game_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_annotations');

        Schema::create('game_annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('sgf_content');
            $table->timestamps();

            $table->index(['game_id', 'created_at']);
        });
    }
};
