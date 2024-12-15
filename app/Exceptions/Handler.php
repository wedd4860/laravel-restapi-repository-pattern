<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Arr;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // 라우터 정보 가져오기
        $objRoute = $request->route();
        if ($objRoute) {
            $strRoute = $objRoute->getName();
            // 잘못된 요청입니다.
            $aJsonData = [
                'status' => 'error',
                'code' => 401,
                'message' => __('messages.Login is required'),
                'data' => []
            ];
            if (in_array($strRoute, ['auth.me', 'auth.logout'])) {
                return response()->json($aJsonData, $aJsonData['code']);
            }
        }
        return parent::render($request, $exception);
    }

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof AuthenticationException) {
            // 로그인이 필요하지만 인증되지 않은 경우
            $objRouteAction = $request->route()->action ?? [];
            if (in_array('auth:sanctum', Arr::get($objRouteAction, 'middleware', []))) {
                $aJsonData = [
                    'status' => 'error',
                    'code' => 401,
                    'message' => __('messages.Unauthorized'),
                    'data' => []
                ];
                return response()->json($aJsonData, $aJsonData['code']);
            }
        }
        return parent::render($request, $exception);
    }
}
