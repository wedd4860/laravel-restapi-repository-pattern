<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StageMiddleware
{
    public function handle(Request $request, Closure $next, ...$stages)
    {
        $currentStage = config('app.env'); // .env의 APP_STAGE 값 가져오기

        $aJsonData = [
            'status' => 'error',
            'code' => 422,
            'message' => __('messages.Service Unavailable'),
            'data' => []
        ];

        if (!in_array($currentStage, $stages)) {
            return response()->json($aJsonData, $aJsonData['code']);
        }
        return $next($request);
    }
}
