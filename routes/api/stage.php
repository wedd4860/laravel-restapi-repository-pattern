<?php

use App\Http\Controllers;
use App\Http\Controllers\Triumph;
use Illuminate\Support\Facades\Route;

// 인증 통합서비스
Route::group(['middleware' => ['auth:sanctum']], function () {
});

// 스테이지에서만 사용
Route::group(['prefix' => 'stage'], function () {
    Route::get('/member/{email}', [Triumph\StageAuthController::class, 'index'])
    ->middleware('stage:local,qa')
    ->where('email', '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}'); // /stage/member/{email} local, qa만 접근 가능
});