<?php

namespace App\Jobs;

use App\Models\Game;
use App\Services\BotService;
use App\Services\ClockTimeoutException;
use App\Services\GameService;
use App\Services\GoEngine\GoRuleException;
use App\Services\KataGoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BotMoveJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /** ให้ queue worker retry ได้สูงสุด 2 ครั้ง */
    public int $tries = 2;

    /**
     * timeout สูงสุด 600 วินาที
     * บอทระดับ 3d ใช้ maxVisits=200 บน CPU อาจใช้เวลา ~140 วินาที
     * บวก KataGo startup ~15 วินาที + buffer → 600 วินาที
     */
    public int $timeout = 600;

    public function __construct(
        public readonly int $gameId,
        public readonly string $botColor
    ) {}

    public function handle(
        KataGoService $kataGoService,
        BotService $botService,
        GameService $gameService
    ): void {
        $game = Game::find($this->gameId);

        if (! $game) {
            return;
        }

        // ตรวจสอบว่ายังเป็นตาของบอทอยู่หรือไม่
        if (! $game->isActive()) {
            return;
        }
        if ($game->current_color !== $this->botColor) {
            return;
        }

        $botUser = ($this->botColor === 'black')
            ? $game->blackPlayer
            : $game->whitePlayer;

        if (! $botUser || ! $botUser->is_bot) {
            return;
        }

        // รอ 1 วินาทีเพื่อให้รู้สึกเป็นธรรมชาติ
        sleep(1);

        // เลือก engine: ใช้ KataGo ถ้าตั้งค่าไว้ มิฉะนั้น fallback เป็น PHP engine เดิม
        $coordinate = $this->getMove($kataGoService, $botService, $game);

        try {
            if ($coordinate === null) {
                // บอทตัดสินใจ pass
                $game = $gameService->passMove($game, $botUser);

                // ถ้า pass ทั้งสองฝ่าย → scoring phase เริ่มต้นอัตโนมัติ
                // ถ้าเกมยังอยู่ใน scoring และบอทเป็นฝ่ายต้องยืนยัน → auto confirm
                $game->refresh();
                if ($game->status === 'scoring') {
                    sleep(1);
                    $gameService->confirmScoreForBot($game, $botUser);
                }
            } else {
                $gameService->makeMove($game, $botUser, $coordinate);
            }
        } catch (GoRuleException) {
            // ตาไม่ถูกกฎหมาย → บอท pass แทน
            try {
                $game = $gameService->passMove($game, $botUser);
                $game->refresh();
                if ($game->status === 'scoring') {
                    sleep(1);
                    $gameService->confirmScoreForBot($game, $botUser);
                }
            } catch (GoRuleException) {
                // เกมอาจจบแล้ว ไม่ต้องทำอะไร
            } catch (ClockTimeoutException) {
                $gameService->handleTimeout($game, $this->botColor);
            }
        } catch (ClockTimeoutException) {
            $gameService->handleTimeout($game, $this->botColor);
        }
    }

    /**
     * เลือกตาโดยใช้ KataGo ถ้าพร้อม มิฉะนั้น fallback เป็น PHP BotService
     */
    private function getMove(
        KataGoService $kataGoService,
        BotService $botService,
        Game $game
    ): ?string {
        if ($kataGoService->isEnabled()) {
            try {
                return $kataGoService->getBotMove($game, $this->botColor);
            } catch (\Throwable $e) {
                Log::warning('KataGo failed, falling back to PHP engine', [
                    'game_id' => $game->id,
                    'error'   => $e->getMessage(),
                ]);
                // fallthrough to PHP engine
            }
        }

        return $botService->getBotMove($game, $this->botColor);
    }
}
