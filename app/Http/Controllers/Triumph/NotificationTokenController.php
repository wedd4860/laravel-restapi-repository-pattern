<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use App\Http\Requests\Triumph\NotificationTokenRequest;
use App\Http\Requests\Triumph\UpdateNotificationTokenRequest;
use App\Http\Resources\Triumph\NotificationTokenCheckResource;
use App\Services\NotificationTokensService;
use Illuminate\Http\Request;

class NotificationTokenController extends Controller
{
    protected $notificationTokensService;

    public function __construct(NotificationTokensService $notificationTokensService)
    {
        $this->notificationTokensService = $notificationTokensService;
    }

    // 토큰 체크
    public function check(NotificationTokenRequest $request)
    {
        try {
            $aValidated = $request->validated();
            $memberId = $request->user()->member_id;

            $aCheckToken = $this->notificationTokensService->getToken([
                'member_id' => $memberId,
                'token' => $aValidated['token'],
                'limit' => 1,
            ]);
            $aToken = $aCheckToken['data'];
            $result = [];
            foreach ($aToken as $token) {
                $result[] = $this->notificationTokensService->getParseDynamoDBData($token);
            }
            if (count($result) === 0) {
                throw new \Exception(__('messages.No token information available'), 400); // 토큰 정보가 없습니다.
            }
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => NotificationTokenCheckResource::collection($result),
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            $aJsonData = [
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    // 토큰 추가
    public function store(NotificationTokenRequest $request)
    {
        try {
            $aValidated = $request->validated();
            $memberId = $request->user()->member_id;

            $result = $this->notificationTokensService->createToken([
                'member_id' => $memberId,
                'token' => $aValidated['token'],
            ]);
            if ($result['status'] === 'error') {
                return response()->json(['message' => $result['message']], 500);
            }
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => []
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            $aJsonData = [
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    // 토큰 제거
    public function destroy(Request $request, string $tokenId = null)
    {
        try {
            $memberId = $request->user()->member_id;
            if ($tokenId) {
                // 특정 토큰 삭제 로직
                $result = $this->notificationTokensService->removeToken([
                    'member_id' => $memberId,
                    'token' => $tokenId,
                ]);
            } else {
                // 모든 토큰 삭제 로직
                $aToken = $this->notificationTokensService->getTokens([
                    'member_id' => $memberId,
                ]);

                if (count($aToken['data']) > 0) {
                    $tmpTokens = [];
                    foreach ($aToken['data'] as $token) {
                        $tmpTokens[] = $this->notificationTokensService->getParseDynamoDBData($token);
                    }
                    $params = array_column($tmpTokens, 'token');
                    $result = $this->notificationTokensService->deleteBatchWrite([
                        'tokens' => $params,
                        'member_id' => $memberId,
                    ]);
                } else {
                    $result['status'] = 'success';
                }
            }
            if ($result['status'] === 'error') {
                return response()->json(['message' => $result['message']], 500);
            }
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => []
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            $aJsonData = [
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }
}
