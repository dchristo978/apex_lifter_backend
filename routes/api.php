<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\GymController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkoutSetController;
use Illuminate\Support\Facades\Route;

Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);

// Password reset by emailed code (throttled to curb abuse / email enumeration).
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:6,1');
Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:6,1');

// Public: gym locations and their leaderboards are browsable without login.
Route::get('gyms', [GymController::class, 'index']);
Route::get('gyms/{gym}/leaderboard', [GymController::class, 'leaderboard']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::delete('auth/account', [AuthController::class, 'deleteAccount']);
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::post('profile/avatar', [ProfileController::class, 'uploadAvatar']);

    Route::post('gyms/checkin', [GymController::class, 'checkin']);
    Route::get('gyms/checkin/latest', [GymController::class, 'latestCheckin']);
    Route::get('gyms/{gym}/active-checkins', [GymController::class, 'activeCheckins']);

    Route::get('machines', [MachineController::class, 'index']);
    Route::get('machines/{machine}/progress', [ProgressController::class, 'show']);

    Route::get('users/{user}', [UserController::class, 'show']);
    Route::get('users/{user}/sessions', [UserController::class, 'sessions']);
    Route::get('users/{user}/medals', [UserController::class, 'medals']);

    Route::get('workout-sets', [WorkoutSetController::class, 'index']);
    Route::post('workout-sets', [WorkoutSetController::class, 'store']);
    Route::delete('workout-sets/{workoutSet}', [WorkoutSetController::class, 'destroy']);

    Route::get('leaderboard', [LeaderboardController::class, 'show']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);

    // Challenges & Arena
    Route::get('challenges', [ChallengeController::class, 'index']);
    Route::get('challenges/arena', [ChallengeController::class, 'arena']);
    Route::get('challenges/history', [ChallengeController::class, 'history']);
    Route::post('challenges', [ChallengeController::class, 'store']);
    Route::get('challenges/{challenge}', [ChallengeController::class, 'show']);
    Route::post('challenges/{challenge}/video', [ChallengeController::class, 'submitVideo']);
    Route::post('challenges/{challenge}/decline', [ChallengeController::class, 'decline']);
    Route::post('challenges/{challenge}/cancel', [ChallengeController::class, 'cancel']);
    Route::post('challenges/{challenge}/vote', [ChallengeController::class, 'vote']);
    Route::patch('challenges/{challenge}/medal-note', [ChallengeController::class, 'updateMedalNote']);
});
