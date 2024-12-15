<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use App\Http\Requests\Triumph\NotificationMeRequest;
use App\Http\Requests\Triumph\UpdateNotificationMeRequest;
use App\Services\NotificationsService;
use App\Services\WebPushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class NotificationMeController extends Controller
{
    protected $notificationsService;

    public function __construct(NotificationsService $notificationsService)
    {
        $this->notificationsService = $notificationsService;
    }

    // 내 알림 조회
    public function show(NotificationMeRequest $request)
    {
        try {
            $aValidated = $request->validated();
            $memberId = $request->user()->member_id;

            // dynamodb 내 알림 조회
            $aMyNotification = $this->notificationsService->getNotifications([
                'member_id' => $memberId,
                'limit' => 200,
                'lastEvaluatedKey' => $aValidated['last_evaluated_key'] ?? null,
            ]);
            $aNotification = $aMyNotification['data'];

            // dynamodb 안읽은 내 알림 조회
            $aMyNotificationUnread = $this->notificationsService->getNotifications([
                'member_id' => $memberId,
                'limit' => 200,
                'lastEvaluatedKey' => null,
                'read_status' => 'unread',
            ]);

            $aNotificationUnread = $aMyNotificationUnread['data'];
            $aData = [
                'notifications' => [],
                'notifications_unread' => [],
                'last_evaluated_key' => $aMyNotification['last_evaluated_key'],
            ];
            foreach ($aNotification as $notification) {
                $aData['notifications'][] = $this->notificationsService->getParseDynamoDBData($notification);
            }
            foreach ($aNotificationUnread as $notification) {
                $aData['notifications_unread'][] = $this->notificationsService->getParseDynamoDBData($notification);
            }

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => $aData
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

    public function update(UpdateNotificationMeRequest $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'notification_id' => 'required|array',
                'notification_id.*' => 'string',
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();
            $memberId = $request->user()->member_id;

            $this->notificationsService->updateBatchRead([
                'notification_id' => $aValidated['notification_id'],
                'member_id' => $memberId
            ]);

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

    public function updateAll(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();
            $memberId = $request->user()->member_id;

            $aMyNotification = $this->notificationsService->getNotifications([
                'member_id' => $memberId,
                'limit' => 500,
                'lastEvaluatedKey' => null,
            ]);
            $aNotification = $aMyNotification['data'];

            $tmpNotifications = [];
            foreach ($aNotification as $notification) {
                $tmpNotifications[] = $this->notificationsService->getParseDynamoDBData($notification);
            }
            $aNotificationId = array_column($tmpNotifications, 'notification_id');
            if (count($aNotificationId) > 1) {
                $result = $this->notificationsService->updateBatchRead([
                    'notification_id' => $aNotificationId,
                    'member_id' => $memberId
                ]);
            }

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => $result
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
