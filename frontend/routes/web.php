<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TutorialController;
use App\Http\Controllers\UploadController;
use App\Services\StatsService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::get('/', function (StatsService $stats) {
    return view('welcome', [
        'platformStats' => $stats->platformStats(),
        'karakters'     => config('citra.karakters', []),
    ]);
})->name('home');

Route::get('/about', function () {
    return view('about', ['karakters' => config('citra.karakters', [])]);
})->name('about');

/*
|--------------------------------------------------------------------------
| Guest-only auth
|--------------------------------------------------------------------------
| Wrapped in the `guest` middleware so a signed-in user hitting /login is
| bounced to the dashboard instead of being shown a pointless form.
*/

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Practice
    Route::get('/practice', [PracticeController::class, 'index'])->name('practice');
    Route::post('/practice/start', [PracticeController::class, 'start'])->name('practice.start');
    Route::post('/practice/end', [PracticeController::class, 'end'])->name('practice.end');
    Route::post('/practice/abort', [PracticeController::class, 'abort'])->name('practice.abort');

    // Tutorial + dataset gallery
    Route::get('/tutorial', [TutorialController::class, 'index'])->name('tutorial');
    Route::get('/dataset', [TutorialController::class, 'dataset'])->name('dataset');
    Route::get('/tutorial/{karakter}/{gerakan}', [TutorialController::class, 'show'])
        ->name('tutorial.show');

    // Leaderboard
    Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard');

    // History
    Route::get('/history', [HistoryController::class, 'index'])->name('history');
    Route::get('/history/{id}', [HistoryController::class, 'show'])
        ->whereNumber('id')
        ->name('history.show');

    // Profile
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/reset-progress', [SettingsController::class, 'resetProgress'])->name('settings.reset');
    Route::delete('/settings/delete-account', [SettingsController::class, 'deleteAccount'])->name('settings.delete');
    Route::get('/settings/export', [SettingsController::class, 'exportData'])->name('settings.export');

    // Maestro upload (admin-gated inside the controller)
    Route::post('/upload-maestro', [UploadController::class, 'upload'])->name('upload.maestro');
});
