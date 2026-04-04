<?php

use App\Http\Controllers\Api\BotApiController;
use Illuminate\Support\Facades\Route;

// ─── Bot Server API ───────────────────────────────────────────────────────────
// ใช้สำหรับ bot client เชื่อมต่อกับเซิร์ฟเวอร์
Route::prefix('bot')->group(function () {

    // ยืนยันตัวตน (ไม่ต้องการ token)
    Route::post('/auth', [BotApiController::class, 'auth']);

    // ต้องการ token ทั้งหมดด้านล่าง
    Route::post('/heartbeat',        [BotApiController::class, 'heartbeat']);
    Route::post('/offline',          [BotApiController::class, 'goOffline']);

    Route::get('/challenges',                          [BotApiController::class, 'pendingChallenges']);
    Route::post('/challenges/{challenge}/accept',      [BotApiController::class, 'acceptChallenge']);

    Route::get('/games',                               [BotApiController::class, 'activeGames']);
    Route::get('/games/{game}',                        [BotApiController::class, 'gameState']);
    Route::post('/games/{game}/move',                  [BotApiController::class, 'makeMove']);
    Route::post('/games/{game}/pass',                  [BotApiController::class, 'pass']);
    Route::post('/games/{game}/resign',                [BotApiController::class, 'resign']);
    Route::post('/games/{game}/scoring/dead-stones',   [BotApiController::class, 'submitDeadStones']);
    Route::post('/games/{game}/confirm-score',         [BotApiController::class, 'confirmScore']);
});
