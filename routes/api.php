<?php

use App\Http\Controllers\ApiSessionController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\FacePhotoController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrationCatalogController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('identity/dni', [RegistrationCatalogController::class, 'identity'])->middleware('throttle:6,1');
    Route::get('locations/regions', [RegistrationCatalogController::class, 'regions']);
    Route::get('locations/regions/{region}/provinces', [RegistrationCatalogController::class, 'provinces'])->where('region', '[0-9]{2}');
    Route::get('locations/provinces/{province}/districts', [RegistrationCatalogController::class, 'districts'])->where('province', '[0-9]{4}');
    Route::get('taxonomy', [RegistrationCatalogController::class, 'taxonomy']);
    Route::post('face/check', [FacePhotoController::class, 'check'])->middleware('throttle:12,1');
    Route::post('auth/register', [RegistrationController::class, 'store'])->middleware('throttle:6,1');
    Route::post('auth/login', [ApiSessionController::class, 'store'])->middleware('throttle:6,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('feed', [CommunityController::class, 'index']);
        Route::post('feed', [CommunityController::class, 'store'])->middleware(['can:posts.create', 'throttle:6,1']);
        Route::get('feed/{post}/comments', [CommunityController::class, 'comments']);
        Route::post('feed/{post}/comments', [CommunityController::class, 'comment'])->middleware(['can:posts.create', 'throttle:20,1']);
        Route::put('feed/{post}/reaction', [CommunityController::class, 'reaction'])->middleware('throttle:60,1');
        Route::put('feed/{post}/bookmark', [CommunityController::class, 'bookmark'])->middleware('throttle:60,1');
        Route::patch('feed/{post}', [CommunityController::class, 'state']);
        Route::delete('feed/{post}', [CommunityController::class, 'destroy']);
        Route::get('profile/avatar', [AvatarController::class, 'show']);
        Route::post('profile/avatar', [AvatarController::class, 'update'])->middleware(['can:profile.update', 'throttle:6,1']);
        Route::get('profile', [ProfileController::class, 'show']);
        Route::post('auth/logout', [ApiSessionController::class, 'destroy']);
        Route::patch('profile/location', [ProfileController::class, 'location'])->middleware('can:profile.update');
        Route::post('profile/photo', [FacePhotoController::class, 'update'])->middleware(['can:profile.update', 'throttle:6,1']);
        Route::get('profile/photo', [FacePhotoController::class, 'show']);
        Route::get('media', [MediaController::class, 'index']);
        Route::post('media', [MediaController::class, 'store'])->middleware('can:media.manage-own');
        Route::get('media/{media}', [MediaController::class, 'show']);
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->middleware('can:media.manage-own');
        Route::post('taxonomy', [RegistrationCatalogController::class, 'storeTaxonomy'])->middleware('can:taxonomy.manage');
    });
});
