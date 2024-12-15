<?php

use App\Http\Controllers;
use App\Http\Controllers\Triumph;
use Illuminate\Support\Facades\Route;

// 인증 통합서비스
Route::group(['middleware' => ['auth:sanctum']], function () {
    // 웹소켓 전용 api
    Route::group(['prefix' => 'websocket'], function () {
        Route::prefix('notification')->group(function () {
            Route::prefix('bracket')->group(function () {
                Route::post('/{bracketId}/status', [Triumph\NotificationStatusController::class, 'store'])->whereNumber('bracketId'); // 경기 상태 알림 보내기
            });
        });
    });
});
