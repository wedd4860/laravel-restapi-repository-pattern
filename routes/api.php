<?php

use App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// tp api
require __DIR__ . "/api/triumph.php";
// tp websocket
require __DIR__ . "/api/websocket.php";
// tp stage
require __DIR__ . "/api/stage.php";

//없는 url
Route::fallback(function () {
    $aJsonData = [
        "status" => "error",
        "code" => 404,
        "message" => __('messages.Bad Request'),
        "data" => []
    ];
    return response()->json($aJsonData, $aJsonData['code']);
})->name('api.fallback.404');
