<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MoveMade implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public string $coordinate,
        public string $color,
        public array $capturedStones,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("game.{$this->game->id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'moveNumber' => $this->game->move_number,
            'coordinate' => $this->coordinate,
            'color' => $this->color,
            'capturedStones' => $this->capturedStones,
            'boardState' => $this->game->board_state,
            'koPoint' => $this->game->ko_point,
            'nextColor' => $this->game->current_color,
            'capturesBlack' => $this->game->captures_black,
            'capturesWhite' => $this->game->captures_white,
            'blackTimeLeft' => $this->game->black_time_left,
            'whiteTimeLeft' => $this->game->white_time_left,
            'blackPeriodsLeft' => $this->game->black_periods_left,
            'whitePeriodsLeft' => $this->game->white_periods_left,
        ];
    }
}
