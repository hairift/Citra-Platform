<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Registered in bootstrap/app.php under the `/api` prefix.
|
| These endpoints back the Blade UI's AJAX widgets on the same origin, so they
| use the session `web` guard. The previous version required `auth:sanctum`,
| a guard this project never installed - every route 500'd, on top of the file
| not being registered at all.
|
*/

Route::get('/ping', fn () => response()->json([
    'status'  => 'ok',
    'app'     => config('app.name'),
    'message' => 'CITRA API is running',
    'time'    => now()->toIso8601String(),
]));

Route::middleware(['web', 'auth'])->group(function () {

    // User
    Route::get('/user/stats', [ApiController::class, 'getUserStats']);
    Route::get('/user/weekly-progress', [ApiController::class, 'getWeeklyProgress']);
    Route::get('/user/character-mastery', [ApiController::class, 'getCharacterMastery']);
    Route::get('/user/achievements', [ApiController::class, 'getAchievements']);

    // Practice
    Route::get('/practice/history', [ApiController::class, 'getPracticeHistory']);

    // Content
    Route::get('/maestro/references', [ApiController::class, 'getMaestroReferences']);
    Route::get('/maestro/{id}/keyframes', [ApiController::class, 'getMaestroKeyframes'])
        ->whereNumber('id');
    Route::get('/datasets', [ApiController::class, 'getDatasets']);

    // Leaderboard
    Route::get('/leaderboard', [ApiController::class, 'getLeaderboard']);

    // Notifications
    Route::get('/notifications', [ApiController::class, 'getNotifications']);
    Route::post('/notifications/read', [ApiController::class, 'markNotificationsRead']);

    // AI backend health
    Route::get('/ai/status', [ApiController::class, 'aiStatus']);
});
