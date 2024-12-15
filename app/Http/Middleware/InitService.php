<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InitService
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // 리턴값 설정
        $request->headers->set('Accept', 'application/json');

        // 언어 설정
        $strLang = $request->header('Accept-Language') ?? 'en';
        if (in_array($strLang, ['ko', 'kr'])) {
            $strLang = 'kr';
        } else if (in_array($strLang, ['jp'])) {
            $strLang = 'jp';
        } else if (in_array($strLang, ['zh-cn'])) {
            $strLang = 'zh-cn';
        } else if (in_array($strLang, ['en'])) {
            $strLang = 'en';
        }
        app()->setLocale($strLang);

        // 서비스 검증
        $aService = config('globalvar.service');
        $strAddValidator = 'required';
        if (is_array($aService)) {
            $strAddValidator = 'required|in:' . implode(',', $aService);
        }
        // 서비스 이용이 불가능합니다.
        $aJsonData = [
            "status" => "error",
            "code" => 422,
            "message" => __('messages.Service Unavailable'),
            "data" => []
        ];
        $validator = validator($request->all(), [
            'service' => $strAddValidator,
        ]);
        if ($validator->fails() && !Str::startsWith($request->getRequestUri(), ['/api/invitations'])) {
            return response()->json($aJsonData, $aJsonData['code']);
        }
        return $next($request);
    }
}
