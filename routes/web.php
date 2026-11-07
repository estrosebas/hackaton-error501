<?php

use App\Http\Controllers\FacePhotoController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// These JSON routes intentionally use web sessions and CSRF protection.
Route::prefix('api')->group(function () {
    Route::post('/auth/register', [RegistrationController::class, 'store'])->middleware(['guest', 'throttle:6,1']);
    Route::post('/auth/login', [SessionController::class, 'store'])->middleware(['guest', 'throttle:6,1']);
    Route::post('/face/check', [FacePhotoController::class, 'check'])->middleware('throttle:12,1');
    Route::middleware('auth')->group(function () {
        Route::get('/auth/user', fn (Request $request) => response()->json([
            'user' => $request->user(),
            'face_enrolled' => $request->user()->faceProfile()->exists(),
        ])->header('Cache-Control', 'no-store'));
        Route::post('/auth/logout', [SessionController::class, 'destroy']);
        Route::post('/profile/photo', [FacePhotoController::class, 'update'])->middleware('throttle:6,1');
        Route::get('/profile/photo', [FacePhotoController::class, 'show']);
    });
});

Route::view('/{path?}', 'app')->where('path', '(?!api(?:/|$)).*')->name('login');
