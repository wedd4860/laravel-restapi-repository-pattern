<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use App\Jobs\NotificationsJob;
use App\Models\Triumph\BracketEntries;
use App\Models\Triumph\Brackets;
use App\Models\Triumph\Events;
use App\Models\Triumph\Members;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class NotificationCallController extends Controller
{
    public function __construct()
    {
    }

    public function store(Request $request, $bracketId)
    {
        try {
            // assistance:주최자 호출
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'type' => 'required|in:assistance'
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            $isParticipantMember = false;
            $aMemberInfo = $request->user();
            $memberId = $aMemberInfo->member_id;
            // 제약사항 : 1분안에 2번 만들기 금지
            $redisUserKey = $aValidated['service'] . '.notification.push.' . $memberId . '.call.create';
            $aRedisInfo = Redis::get($redisUserKey);
            if ($aRedisInfo) {
                throw new \Exception(__('messages.Too many requests. Please try again after a while'), 429); //(1분)허용된 요청량보다 많은 요청을 하였습니다. 잠시후 다시 시도해 주시기 바랍니다.
            }
            // 참여자 검증
            $aBracketEntry = BracketEntries::with(['participants', 'participants.participant_members'])
                ->where('bracket_id', $bracketId)->get();
            foreach ($aBracketEntry as $bracketEntry) {
                if($isParticipantMember){
                    break;
                }
                $strEntrantName = $bracketEntry->participants->entrant_name;
                $strEntrantImageUrl = $bracketEntry->participants->entrant_image_url;
                if ($bracketEntry->participants->participant_type === 0) {
                    //개인전
                    if ($bracketEntry->participants->entrant_id == $memberId) {
                        $isParticipantMember = true;
                        break;
                    }
                } else {
                    //팀전
                    foreach ($bracketEntry->participants->participant_members as $participant_member) {
                        if ($participant_member->member_id == $memberId || $participant_member->create_member_id == $memberId) {
                            $isParticipantMember = true;
                            break;
                        }
                    }
                }
            }
            if (!$isParticipantMember) {
                throw new \Exception(__('messages.Unauthorized'), 401); // 권한이 없습니다.
            }
            //주최자 : $aEvent->member_id
            $aEvent = Events::with(['brackets', 'brackets.bracketEntries'])
                ->whereHas('brackets', function ($query) use ($bracketId) {
                    $query->where('bracket_id', $bracketId);
                })->first();

            if (!$aEvent) {
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            // 알림 : 주최자 호출
            NotificationsJob::dispatch(
                'createNotification',
                [
                    'lang' => Members::find($aEvent->member_id)->language ?? 'en',
                    'template' => 'move_to_the_game_room',
                    'bind' => [
                        '[name]' => $strEntrantName,
                    ]
                ],
                [
                    'member_id' => $aEvent->member_id,
                    'scheduled_time' => Carbon::now()->timestamp,  // 현재 시간 또는 특정 시간
                    'link' => "/event/{$aEvent->event_id}/bracket/{$bracketId}",
                    'tag' => "bracket-{$bracketId}",
                    'type' => 'bracket',
                    'profile_img' => $strEntrantImageUrl,
                ]
            );

            // 제약사항 : 10초 안에 2번 호출 금지
            Redis::setex($redisUserKey, 10, json_encode([
                'bracket_id' => $bracketId,
                'member_id' => $memberId
            ])); // 초단위

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
