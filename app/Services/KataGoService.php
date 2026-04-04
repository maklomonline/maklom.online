<?php

namespace App\Services;

use App\Models\Game;
use Illuminate\Support\Facades\Log;

/**
 * KataGoService — ใช้ KataGo Neural Network เป็น engine สำหรับบอทหมากล้อม
 *
 * ติดต่อกับ KataGo ผ่าน GTP (Go Text Protocol) โดยการรัน subprocess
 * รองรับ persistent process เพื่อลดเวลาในการโหลด neural network ข้ามระหว่างงาน
 *
 * ปรับความแข็งแกร่งตามระดับบอทผ่านพารามิเตอร์:
 *  - maxVisits       : จำนวน MCTS visits สูงสุด (มากขึ้น = แข็งกว่า ช้ากว่า)
 *  - rootNoiseEnabled: เปิด Dirichlet noise ที่ root (เล่นสุ่มมากขึ้น ≈ อ่อนลง)
 *
 * ระดับบอท:
 *  8k → maxVisits=2,   rootNoiseEnabled=true   (มือใหม่)
 *  5k → maxVisits=8,   rootNoiseEnabled=true   (ปานกลาง)
 *  2k → maxVisits=50,  rootNoiseEnabled=false  (ดีกว่าเฉลี่ย)
 *  1d → maxVisits=200, rootNoiseEnabled=false  (ระดับ dan)
 *  3d → maxVisits=800, rootNoiseEnabled=false  (แข็งแกร่ง)
 */
class KataGoService
{
    /** KataGo subprocess resources (persistent ข้ามระหว่าง job) */
    private mixed $process = null;

    /** @var array{0: resource, 1: resource, 2: resource} */
    private array $pipes = [];

    public function __destruct()
    {
        $this->closeProcess();
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * ตรวจสอบว่า KataGo ถูกตั้งค่าและ binary มีอยู่จริงหรือไม่
     */
    public function isEnabled(): bool
    {
        $binary = config('katago.binary');

        return $binary !== '' && file_exists((string) $binary);
    }

    /**
     * คืน coordinate string (เช่น "D4") หรือ null เพื่อ pass
     */
    public function getBotMove(Game $game, string $color): ?string
    {
        $level  = $this->getBotLevel($game, $color);
        $config = config("katago.levels.{$level}", config('katago.levels.8k'));

        try {
            return $this->generateMove($game, $color, $config);
        } catch (\Throwable $e) {
            Log::error('KataGoService error', [
                'game_id' => $game->id,
                'color'   => $color,
                'level'   => $level,
                'error'   => $e->getMessage(),
            ]);
            // รีเซ็ต process เพื่อให้ครั้งถัดไปเริ่มใหม่
            $this->closeProcess();

            throw $e;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Core GTP Logic
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * สั่ง KataGo ให้เลือกตาสำหรับเกมนี้
     */
    private function generateMove(Game $game, string $color, array $levelConfig): ?string
    {
        $this->ensureProcessRunning();

        // รีเซ็ตกระดานและตั้งค่าพื้นฐาน
        $this->sendCommand('clear_board');
        $this->sendCommand("boardsize {$game->board_size}");
        $this->sendCommand("komi {$game->komi}");

        // ตั้งค่าความแข็งแกร่งตามระดับบอท
        $this->sendCommand("kata-set-param maxVisits {$levelConfig['maxVisits']}");

        $noiseVal = ($levelConfig['rootNoiseEnabled'] ?? false) ? 'true' : 'false';
        $this->sendCommand("kata-set-param rootNoiseEnabled {$noiseVal}");

        // rootPolicyTemperature: สูงขึ้น = เลือก move สุ่มขึ้นจาก policy → เล่นอ่อนลง
        // ค่า default ของ KataGo = 1.0 (ไม่สุ่ม)
        $temp = (float) ($levelConfig['rootPolicyTemperature'] ?? 1.0);
        $this->sendCommand("kata-set-param rootPolicyTemperature {$temp}");

        // noise concentration: ต่ำลง = noise กระจุกตัวมากขึ้น → ผลต่อการเลือก move แรงขึ้น
        if (isset($levelConfig['rootDirichletNoiseTotalConcentration'])) {
            $conc = (float) $levelConfig['rootDirichletNoiseTotalConcentration'];
            $this->sendCommand("kata-set-param rootDirichletNoiseTotalConcentration {$conc}");
        }

        // noise weight: สัดส่วน noise ที่ผสมเข้าไปใน policy (default = 0.25)
        if (isset($levelConfig['rootDirichletNoiseWeight'])) {
            $weight = (float) $levelConfig['rootDirichletNoiseWeight'];
            $this->sendCommand("kata-set-param rootDirichletNoiseWeight {$weight}");
        }

        // วาง handicap stones ถ้ามี (ก่อน replay moves)
        if ($game->handicap >= 2) {
            $handicapCoords = $this->getHandicapCoords($game->board_size, $game->handicap);
            if (! empty($handicapCoords)) {
                $this->sendCommand('set_free_handicap ' . implode(' ', $handicapCoords));
            }
        }

        // Replay ทุก move ในเกมเพื่อสร้าง board state ใน KataGo
        $moves = $game->moves()->orderBy('move_number')->get();
        foreach ($moves as $move) {
            $gtpCoord = $move->coordinate ?? 'pass';
            $this->sendCommand("play {$move->color} {$gtpCoord}");
        }

        // แจ้ง KataGo เรื่อง time control เพื่อให้เดินภายในเวลาของ clock จริง
        $this->sendTimeSettings($game);
        $this->sendTimeLeft($game, $color);

        // timeout ของ sendCommand ต้องรองรับเวลาคิดสูงสุดที่ KataGo อาจใช้
        $genmoveTimeout = $this->calcGenmoveTimeout($game, $color);

        // สั่ง genmove และ parse ผลลัพธ์
        $response = $this->sendCommand("genmove {$color}", timeout: $genmoveTimeout);

        return $this->parseGenmoveResponse($response);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Time Control
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * ส่ง GTP time_settings เพื่อบอก KataGo เรื่อง time control ของเกม
     */
    private function sendTimeSettings(Game $game): void
    {
        $mainTime = (int) $game->main_time;

        if ($game->clock_type === 'byoyomi') {
            $byoyomiSeconds = (int) ($game->byoyomi_seconds ?? 30);
            $byoyomiPeriods = (int) ($game->byoyomi_periods ?? 1);
            $this->sendCommand("time_settings {$mainTime} {$byoyomiSeconds} {$byoyomiPeriods}");
        } else {
            // Fischer — ไม่มี byo-yomi
            $this->sendCommand("time_settings {$mainTime} 0 0");
        }
    }

    /**
     * ส่ง GTP time_left เพื่อบอก KataGo ว่าเหลือเวลากี่วินาที/กี่ period
     */
    private function sendTimeLeft(Game $game, string $color): void
    {
        $timeLeft    = $color === 'black' ? $game->black_time_left    : $game->white_time_left;
        $periodsLeft = $color === 'black' ? $game->black_periods_left : $game->white_periods_left;

        if ($game->clock_type === 'byoyomi') {
            $periods = max(0, (int) ($periodsLeft ?? 0));
            $this->sendCommand("time_left {$color} {$timeLeft} {$periods}");
        } else {
            $this->sendCommand("time_left {$color} {$timeLeft} 0");
        }
    }

    /**
     * คำนวณ timeout สำหรับ genmove command
     * ต้องมากกว่าเวลาที่ KataGo อาจใช้คิด + buffer
     */
    private function calcGenmoveTimeout(Game $game, string $color): int
    {
        $timeLeft = $color === 'black' ? $game->black_time_left : $game->white_time_left;

        if ($game->clock_type === 'byoyomi') {
            // ในช่วง byo-yomi KataGo จะใช้ไม่เกิน byoyomi_seconds ต่อตา
            $byoyomiSeconds = (int) ($game->byoyomi_seconds ?? 30);
            $maxThink = max($timeLeft, $byoyomiSeconds);
        } else {
            // Fischer: KataGo จะแบ่งเวลาที่เหลือ — สูงสุดที่อาจใช้คือ timeLeft ทั้งหมด
            $maxThink = $timeLeft;
        }

        // บวก 15 วินาที buffer สำหรับ overhead และ network latency
        return max(30, $maxThink + 15);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Process Management
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * ตรวจสอบว่า KataGo process ยังทำงานอยู่ ถ้าไม่ใช่ให้สร้างใหม่
     */
    private function ensureProcessRunning(): void
    {
        if ($this->process !== null) {
            $status = proc_get_status($this->process);
            if ($status['running']) {
                return;
            }
            $this->closeProcess();
        }

        $this->startProcess();
    }

    /**
     * เริ่ม KataGo GTP subprocess
     * ใช้ probe command (protocol_version) เพื่อรอให้ neural network โหลดเสร็จ
     */
    private function startProcess(): void
    {
        $binary = (string) config('katago.binary');
        $model  = (string) config('katago.model');
        $cfg    = (string) config('katago.config');

        if (! file_exists($binary)) {
            throw new \RuntimeException(
                "KataGo binary ไม่พบ: '{$binary}' — กรุณาตั้งค่า KATAGO_BINARY ใน .env"
            );
        }

        $cmd = escapeshellarg($binary) . ' gtp';
        if ($model !== '') {
            $cmd .= ' -model ' . escapeshellarg($model);
        }
        if ($cfg !== '') {
            $cmd .= ' -config ' . escapeshellarg($cfg);
        }

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin ของ KataGo
            1 => ['pipe', 'w'],  // stdout ของ KataGo
            2 => ['pipe', 'w'],  // stderr ของ KataGo
        ];

        $this->process = proc_open($cmd, $descriptors, $this->pipes);

        if (! is_resource($this->process)) {
            $this->process = null;
            throw new \RuntimeException('ไม่สามารถเริ่ม KataGo process ได้');
        }

        // non-blocking เพื่อให้ fgets() คืน false ทันทีเมื่อไม่มีข้อมูล
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);

        // รอให้ KataGo โหลด neural network เสร็จโดยส่ง probe command
        // KataGo อาจใช้เวลา 10–120 วินาทีสำหรับการโหลดครั้งแรก
        $this->sendCommand('protocol_version', timeout: 120);

        Log::info('KataGo process started', ['cmd' => $cmd]);
    }

    /**
     * ปิด KataGo process และ cleanup pipes
     */
    private function closeProcess(): void
    {
        if ($this->process === null) {
            return;
        }

        try {
            if (is_resource($this->pipes[0] ?? null)) {
                @fwrite($this->pipes[0], "quit\n");
                @fclose($this->pipes[0]);
            }
            foreach ([1, 2] as $i) {
                if (is_resource($this->pipes[$i] ?? null)) {
                    @fclose($this->pipes[$i]);
                }
            }
            @proc_close($this->process);
        } catch (\Throwable) {
            // ignore cleanup errors
        }

        $this->process = null;
        $this->pipes   = [];
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  GTP Protocol
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * ส่งคำสั่ง GTP ไปยัง KataGo และรับ response
     *
     * GTP response format:
     *   "= value\n\n"  → สำเร็จ
     *   "? error\n\n"  → ผิดพลาด
     *
     * @throws \RuntimeException เมื่อ KataGo ไม่ตอบภายใน timeout
     */
    private function sendCommand(string $command, int $timeout = 30): string
    {
        if (! is_resource($this->pipes[0] ?? null)) {
            throw new \RuntimeException('KataGo stdin pipe ไม่พร้อมใช้งาน');
        }

        fwrite($this->pipes[0], $command . "\n");

        $response  = '';
        $startTime = microtime(true);

        while ((microtime(true) - $startTime) < $timeout) {
            // ดูด stderr ออก (KataGo ส่ง progress/log ผ่าน stderr)
            while (($err = fgets($this->pipes[2])) !== false) {
                Log::debug('KataGo stderr: ' . rtrim($err));
            }

            $line = fgets($this->pipes[1]);

            if ($line === false) {
                // ยังไม่มีข้อมูล รอสักครู่
                usleep(10_000); // 10ms
                continue;
            }

            $response .= $line;

            // GTP response จบเมื่อได้บรรทัดว่างหลัง response line
            // (= value\n + \n  หรือ  ? error\n + \n)
            if (trim($line) === '' && ltrim($response) !== '') {
                break;
            }
        }

        $response = trim($response);

        if ($response === '') {
            throw new \RuntimeException(
                "KataGo ไม่ตอบสนองต่อคำสั่ง '{$command}' ภายใน {$timeout} วินาที"
            );
        }

        if (str_starts_with($response, '?')) {
            Log::warning('KataGo GTP error response', [
                'command'  => $command,
                'response' => $response,
            ]);
        }

        return $response;
    }

    /**
     * Parse response ของ genmove command
     *
     * ตัวอย่าง:
     *   "= D4"     → "D4"   (วางหมากที่ D4)
     *   "= pass"   → null   (pass)
     *   "= resign" → null   (ยอมแพ้ → ให้ game logic จัดการ)
     *   "? error"  → null   (ผิดพลาด → pass แทน)
     */
    private function parseGenmoveResponse(string $response): ?string
    {
        if (! str_starts_with($response, '=')) {
            return null; // GTP error → pass
        }

        $move = strtolower(trim(substr($response, 1)));

        if ($move === 'pass' || $move === 'resign') {
            return null;
        }

        return strtoupper($move);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Coordinate Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * สร้าง list ของ GTP coordinates สำหรับ handicap stones
     *
     * ใช้ตำแหน่งเดียวกับ BoardService::HANDICAP_POINTS เพื่อให้ตรงกับ
     * board state ที่ถูกสร้างขึ้นตอนเริ่มเกม
     *
     * @return string[]  เช่น ["D16", "Q4"]
     */
    private function getHandicapCoords(int $boardSize, int $handicap): array
    {
        // ตำแหน่งเหมือนกับ BoardService::HANDICAP_POINTS (row, col) แบบ 0-indexed จากมุมซ้ายบน
        $allPoints = match ($boardSize) {
            19 => [
                2 => [[3, 15], [15, 3]],
                3 => [[3, 15], [15, 3], [15, 15]],
                4 => [[3, 3], [3, 15], [15, 3], [15, 15]],
                5 => [[3, 3], [3, 15], [15, 3], [15, 15], [9, 9]],
                6 => [[3, 3], [3, 15], [15, 3], [15, 15], [3, 9], [15, 9]],
                7 => [[3, 3], [3, 15], [15, 3], [15, 15], [3, 9], [15, 9], [9, 9]],
                8 => [[3, 3], [3, 15], [15, 3], [15, 15], [3, 9], [15, 9], [9, 3], [9, 15]],
                9 => [[3, 3], [3, 15], [15, 3], [15, 15], [3, 9], [15, 9], [9, 3], [9, 15], [9, 9]],
            ],
            13 => [
                2 => [[3, 9], [9, 3]],
                3 => [[3, 9], [9, 3], [9, 9]],
                4 => [[3, 3], [3, 9], [9, 3], [9, 9]],
                5 => [[3, 3], [3, 9], [9, 3], [9, 9], [6, 6]],
            ],
            9 => [
                2 => [[2, 6], [6, 2]],
                3 => [[2, 6], [6, 2], [6, 6]],
                4 => [[2, 2], [2, 6], [6, 2], [6, 6]],
            ],
            default => [],
        };

        $points = $allPoints[$handicap] ?? [];
        $coords = [];

        foreach ($points as [$row, $col]) {
            // col: 0→A, 1→B, ..., 7→H, 8→J, 9→K ... (ข้าม I)
            $colChar   = $col >= 8 ? chr(ord('A') + $col + 1) : chr(ord('A') + $col);
            $rowNumber = $boardSize - $row; // row 0 (array top) = row boardSize ใน GTP
            $coords[]  = $colChar . $rowNumber;
        }

        return $coords;
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function getBotLevel(Game $game, string $color): string
    {
        $botUser = ($color === 'black') ? $game->blackPlayer : $game->whitePlayer;

        return $botUser?->bot_level ?? '8k';
    }
}
