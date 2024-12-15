<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use App\Jobs\NotificationsJob;
use App\Models\Triumph\BracketEntries;
use App\Models\Triumph\Brackets;
use App\Models\Triumph\Events;
use App\Models\Triumph\Members;
use App\Models\Triumph\ParticipantMembers;
use App\Models\Triumph\Participants;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class NotificationStatusController extends Controller
{
    public function __construct()
    {
    }

    public function store(Request $request, int $bracketId)
    {
        try {
            // accepted:수락, rejected:거절, started:시작, finished:종료, result:결과 입력, 제거 : judgement:판정
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'type' => 'required|in:accepted,rejected,result,assistance'
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            $aMemberInfo = $request->user();
            $memberId = $aMemberInfo->member_id;

            //주최자 : get()일경우 $aEvent->pluck('member_id')[0], first()일경우 $aEvent->member_id
            $aEvent = Events::with(['brackets', 'brackets.bracketEntries'])
                ->whereHas('brackets', function ($query) use ($bracketId) {
                    $query->where('bracket_id', $bracketId);
                })->first();
            $aBracket = [];
            foreach ($aEvent->brackets as $bracket) {
                foreach ($bracket->bracketEntries as $bracketEntry) {
                    $aBracket[] = [
                        'bracket_id' => $bracket->bracket_id,
                        'participant_id' => $bracketEntry->participant_id
                    ];
                }
            }
            $aParticipantId = collect($aBracket)->where('bracket_id', $bracketId)->pluck('participant_id')->all();
            if ($aEvent->team_size > 1) {
                // 팀전일때
                $aParticipantMembers = ParticipantMembers::with('participants')->whereIn('participant_id', $aParticipantId)->get();
                // 내팀 참여자 정보
                $aParticipantsMe = $aParticipantMembers->where('create_member_id', $memberId);
                $strEntrantName = $aParticipantsMe->first()?->participants->entrant_name;
                $strPluckKey = 'member_id';
            } else {
                // 개인전 일때
                $aParticipantMembers = Participants::whereIn('participant_id', $aParticipantId)->get();
                // 내팀 참여자 정보
                $aParticipantsMe = $aParticipantMembers->where('create_member_id', $memberId);
                $strEntrantName = $aParticipantsMe->first()?->entrant_name;
                $strPluckKey = 'entrant_id';
            }
            // 리더들
            $aLeader = $aParticipantsMe->pluck('member_id')->count();
            if ($aLeader === 0) {
                throw new \Exception(__('messages.Participant information is incorrect'), 400); //참여자 정보가 잘못되었습니다.
            }

            // 참가자 전원
            $aParticipants = $aParticipantMembers->pluck($strPluckKey)->all();
            // 상대팀 참가자 전원
            $aOpponents = $aParticipantMembers->where('create_member_id', '!=', $memberId)->pluck($strPluckKey)->all();
            if ($aEvent->team_size > 1) {
                //상대팀 리더
                $iOpponentLeader = $aParticipantMembers->where('create_member_id', '!=', $memberId)->pluck('create_member_id')->first();
                //전체 리더
                $aOpponentLeader = $aParticipantMembers->pluck('create_member_id')->unique()->all();
            }

            $aNotificationConfig = [
                [
                    'members' => [],
                    'template' => '',
                    'link' => "/event/{$aEvent->event_id}/bracket/{$bracketId}",
                    'tag' => "bracket-{$bracketId}",
                    'type' => 'bracket',
                ]
            ];

            if ($aValidated['type'] == 'accepted') {
                $aNotificationConfig[0]['template'] = 'opponent_accepted_match'; // 수락
            } elseif ($aValidated['type'] == 'rejected') {
                $aNotificationConfig[0]['template'] = 'opponent_declined_match'; // 거절
            } elseif ($aValidated['type'] == 'result') {
                $aNotificationConfig[0]['template'] = 'match_results_entered'; // 결과 입력
            }

            if (in_array($aValidated['type'], ['accepted', 'rejected'])) {
                // 상대 브라켓 참가자 전원 (경기 수락 / 경기 거절)
                $aNotificationConfig[0]['members'] = $aOpponents;
                if ($aEvent->team_size > 1) {
                    //팀전일때 상대 리더 추가
                    $aNotificationConfig[0]['members'][] = $iOpponentLeader;
                }

                if (BracketEntries::where('bracket_id', $bracketId)->where('status', 2)->count() > 0) {
                    $aNotificationConfig[1] = [
                        'members' => $aParticipants,
                        'template' => 'match_started',
                        'link' => "/event/{$aEvent->event_id}/bracket/{$bracketId}",
                        'tag' => "bracket-{$bracketId}",
                        'type' => 'bracket',
                    ];
                    if ($aEvent->team_size > 1) {
                        //팀전일때 전체 리더 추가
                        foreach ($aOpponentLeader as $leader) {
                            $aNotificationConfig[1]['members'][] = $leader;
                        }
                    }
                }
            } elseif (in_array($aValidated['type'], ['result'])) {
                // 주최자 (경기 결과 입력)
                $aNotificationConfig[0]['members'][] = $aEvent->member_id;
                $aNotificationConfig[0]['link'] = "/manage/event/{$aEvent->event_id}/dashboard";
                $aNotificationConfig[0]['tag'] = "manage-{$aEvent->event_id}";
                $aNotificationConfig[0]['type'] = 'manage';
            }
            $aSentMember = array_fill(0, count($aNotificationConfig), []);
            foreach ($aNotificationConfig as $key => $item) {
                foreach ($item['members'] as $itemMemberId) {
                    if ($itemMemberId === 0) {
                        continue;
                    }
                    if (in_array($itemMemberId, $aSentMember[$key])) {
                        continue;
                    }
                    if (in_array($aValidated['type'], ['accepted', 'rejected'])) {
                        // 수락 거절에는 주최자 알림 필요없음
                        if ($itemMemberId === $aEvent->member_id) {
                            continue;
                        }
                    }
                    $aSentMember[$key][] = $itemMemberId;
                    NotificationsJob::dispatch(
                        'createNotification',
                        [
                            'lang' => Members::find($itemMemberId)->language ?? 'en',
                            'template' => $aNotificationConfig[$key]['template'],
                            'bind' => [
                                '[name]' => $strEntrantName,
                            ],
                        ],
                        [
                            'member_id' => $itemMemberId,
                            'scheduled_time' => Carbon::now()->timestamp,  // 현재 시간 또는 특정 시간
                            'link' => $aNotificationConfig[$key]['link'],
                            'tag' => $aNotificationConfig[$key]['tag'],
                            'type' => $aNotificationConfig[$key]['type'],
                            'profile_img' => $aMemberInfo->image_url,
                        ]
                    );
                }
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
