<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\BotGameController;
use App\Http\Controllers\GameAnnotationController;
use App\Http\Controllers\BotServerController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameInviteController;
use App\Http\Controllers\GameRoomController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LobbyController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// ─── Public ───────────────────────────────────────────────────────────────────
Route::get('/', [LobbyController::class, 'index'])->name('home');

// ─── Guest only ───────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/register', [Auth\RegisterController::class, 'showForm'])->name('register');
    Route::post('/register', [Auth\RegisterController::class, 'store']);

    Route::get('/login', [Auth\LoginController::class, 'showForm'])->name('login');
    Route::post('/login', [Auth\LoginController::class, 'store']);

    Route::get('/forgot-password', [Auth\PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [Auth\PasswordResetController::class, 'sendLink'])->name('password.email');
    Route::get('/reset-password/{token}', [Auth\PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [Auth\PasswordResetController::class, 'reset'])->name('password.update');
});

// ─── Auth required ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'not.banned'])->group(function () {

    Route::post('/logout', [Auth\LoginController::class, 'destroy'])->name('logout');

    // Email verification
    Route::get('/email/verify', [Auth\EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [Auth\EmailVerificationController::class, 'verify'])
        ->middleware('signed')->name('verification.verify');
    Route::post('/email/resend', [Auth\EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');

    // Verified routes
    Route::middleware('verified')->group(function () {

        // Lobby
        Route::get('/lobby', [LobbyController::class, 'index'])->name('lobby');

        // Users search
        Route::get('/users/search', [ProfileController::class, 'search'])->name('users.search');

        // Profile
        Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::get('/profile/{user:username}', [ProfileController::class, 'show'])->name('profile.show');

        // Rooms
        Route::get('/rooms', [GameRoomController::class, 'index'])->name('rooms.index');
        Route::get('/rooms/create', [GameRoomController::class, 'create'])->name('rooms.create');
        Route::post('/rooms', [GameRoomController::class, 'store'])->name('rooms.store');
        Route::get('/rooms/{room}', [GameRoomController::class, 'show'])->name('rooms.show');
        Route::get('/rooms/{room}/status', [GameRoomController::class, 'status'])->name('rooms.status');
        Route::post('/rooms/{room}/join', [GameRoomController::class, 'join'])->name('rooms.join');
        Route::delete('/rooms/{room}/leave', [GameRoomController::class, 'leave'])->name('rooms.leave');
        Route::delete('/rooms/{room}', [GameRoomController::class, 'destroy'])->name('rooms.destroy');

        // Games
        Route::get('/games/history/{user:username}', [GameController::class, 'history'])->name('games.history');
        Route::get('/games/{game}', [GameController::class, 'show'])->name('games.show');
        Route::get('/games/{game}/base-sgf', [GameAnnotationController::class, 'baseSgf'])->name('games.base-sgf');
        Route::post('/games/{game}/annotations', [GameAnnotationController::class, 'store'])->name('games.annotations.store');
        Route::put('/games/{game}/annotations/{annotation}', [GameAnnotationController::class, 'update'])->name('games.annotations.update');
        Route::delete('/games/{game}/annotations/{annotation}', [GameAnnotationController::class, 'destroy'])->name('games.annotations.destroy');
        Route::middleware('game.player')->group(function () {
            Route::post('/games/{game}/move', [GameController::class, 'move'])->name('games.move');
            Route::post('/games/{game}/pass', [GameController::class, 'pass'])->name('games.pass');
            Route::post('/games/{game}/resign', [GameController::class, 'resign'])->name('games.resign');
            Route::post('/games/{game}/scoring/toggle-dead-group', [GameController::class, 'toggleDeadGroup'])->name('games.toggle-dead-group');
            Route::post('/games/{game}/confirm-score', [GameController::class, 'confirmScore'])->name('games.confirm-score');
            Route::post('/games/{game}/cancel-scoring', [GameController::class, 'cancelScoring'])->name('games.cancel-scoring');
            Route::post('/games/{game}/timeout', [GameController::class, 'claimTimeout'])->name('games.timeout');
        });

        // Chat
        Route::get('/chat/global', [ChatController::class, 'globalMessages'])->name('chat.global');
        Route::get('/chat/{chatRoom}/messages', [ChatController::class, 'roomMessages'])->name('chat.messages');
        Route::post('/chat/{chatRoom}/messages', [ChatController::class, 'sendMessage'])->name('chat.send');
        Route::delete('/chat/messages/{message}', [ChatController::class, 'deleteMessage'])->name('chat.delete');

        // Friends
        Route::get('/friends', [FriendController::class, 'index'])->name('friends.index');
        Route::post('/friends/request/{user}', [FriendController::class, 'sendRequest'])->name('friends.request');
        Route::post('/friends/accept/{friendship}', [FriendController::class, 'acceptRequest'])->name('friends.accept');
        Route::post('/friends/decline/{friendship}', [FriendController::class, 'declineRequest'])->name('friends.decline');
        Route::post('/friends/block/{user}', [FriendController::class, 'block'])->name('friends.block');
        Route::post('/friends/unblock/{user}', [FriendController::class, 'unblock'])->name('friends.unblock');
        Route::delete('/friends/{user}', [FriendController::class, 'removeFriend'])->name('friends.remove');

        // Groups
        Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
        Route::get('/groups/create', [GroupController::class, 'create'])->name('groups.create');
        Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
        Route::get('/groups/{group:slug}', [GroupController::class, 'show'])->name('groups.show');
        Route::get('/groups/{group:slug}/edit', [GroupController::class, 'edit'])->name('groups.edit');
        Route::put('/groups/{group:slug}', [GroupController::class, 'update'])->name('groups.update');
        Route::delete('/groups/{group:slug}', [GroupController::class, 'destroy'])->name('groups.destroy');
        Route::post('/groups/{group:slug}/join', [GroupController::class, 'join'])->name('groups.join');
        Route::post('/groups/{group:slug}/leave', [GroupController::class, 'leave'])->name('groups.leave');
        Route::get('/groups/{group:slug}/members', [GroupController::class, 'members'])->name('groups.members');
        Route::delete('/groups/{group:slug}/members/{user}', [GroupController::class, 'kickMember'])->name('groups.kick');
        Route::post('/groups/{group:slug}/members/{user}/promote', [GroupController::class, 'promoteMember'])->name('groups.promote');

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

        // Bot games (server-side bots)
        Route::get('/bots', [BotGameController::class, 'index'])->name('bots.index');
        Route::post('/bots/{bot}/play', [BotGameController::class, 'play'])->name('bots.play');

        // Bot server (community bots)
        Route::get('/bot/register', [BotServerController::class, 'showRegisterForm'])->name('bot.register');
        Route::post('/bot/register', [BotServerController::class, 'submitRegister'])->name('bot.register.store');
        Route::get('/bot/download', [BotServerController::class, 'download'])->name('bot.download');

        // Game invites
        Route::post('/invites/send/{user}', [GameInviteController::class, 'send'])->name('invites.send');
        Route::post('/invites/accept/{invite}', [GameInviteController::class, 'accept'])->name('invites.accept');
        Route::post('/invites/decline/{invite}', [GameInviteController::class, 'decline'])->name('invites.decline');

        // Challenges (ท้าดวล)
        Route::post('/challenges/send/{user}', [ChallengeController::class, 'send'])->name('challenges.send');
        Route::post('/challenges/{challenge}/accept', [ChallengeController::class, 'accept'])->name('challenges.accept');
        Route::post('/challenges/{challenge}/decline', [ChallengeController::class, 'decline'])->name('challenges.decline');

        // ─── Admin ────────────────────────────────────────────────────────────
        Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
            Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

            Route::get('/users', [Admin\UserManagementController::class, 'index'])->name('users.index');
            Route::get('/users/{user}', [Admin\UserManagementController::class, 'show'])->name('users.show');
            Route::post('/users/{user}/ban', [Admin\UserManagementController::class, 'ban'])->name('users.ban');
            Route::post('/users/{user}/unban', [Admin\UserManagementController::class, 'unban'])->name('users.unban');
            Route::delete('/users/{user}', [Admin\UserManagementController::class, 'delete'])->name('users.delete');

            Route::get('/games', [Admin\GameManagementController::class, 'index'])->name('games.index');
            Route::get('/games/{game}', [Admin\GameManagementController::class, 'show'])->name('games.show');
            Route::post('/games/{game}/abort', [Admin\GameManagementController::class, 'abort'])->name('games.abort');

            Route::get('/groups', [Admin\GroupManagementController::class, 'index'])->name('groups.index');
            Route::get('/groups/{group}', [Admin\GroupManagementController::class, 'show'])->name('groups.show');
            Route::delete('/groups/{group}', [Admin\GroupManagementController::class, 'destroy'])->name('groups.destroy');

            Route::get('/logs', [Admin\LogController::class, 'index'])->name('logs');

            // Bot requests
            Route::get('/bot-requests', [Admin\BotRequestController::class, 'index'])->name('bot-requests.index');
            Route::post('/bot-requests/{botRequest}/approve', [Admin\BotRequestController::class, 'approve'])->name('bot-requests.approve');
            Route::post('/bot-requests/{botRequest}/reject', [Admin\BotRequestController::class, 'reject'])->name('bot-requests.reject');
        });
    });
});
