<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers;
use App\Http\Controllers\Triumph;
use Laravel\Socialite\Facades\Socialite;

# 관리자 프론트 페이지
Route::group(['prefix' => 'masanggames'], function () {
    if (in_array(request()->ip(), config('globalvar.admin.ip'))) {
        Route::get('login', [Controllers\OAuthController::class, 'index']);
        Route::get('chat', [Triumph\PusherController::class, 'index']);
    }
});

# oauth
Route::group(['prefix' => 'auth'], function () {
    Route::get('/login/{providerId}', [Controllers\OAuthController::class, 'redirectToProvider'])
        ->where('providerId', 'google'); // 'google|steam|facebook|apple'
    Route::get('/login/{providerId}/callback', [Controllers\OAuthController::class, 'handleProviderCallback'])
        ->where('providerId', 'google'); // 'google|steam|facebook'
    Route::post('/login/{providerId}/callback', [Controllers\OAuthController::class, 'handleProviderCallback'])
        ->where('providerId', 'apple');
});

Route::fallback(function () {
    return view('status.404');
});
