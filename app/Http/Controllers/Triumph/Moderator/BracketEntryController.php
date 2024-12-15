<?php

namespace App\Http\Controllers\Triumph\Moderator;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Triumph\BracketsRepository;
use App\Repositories\Triumph\BracketEntriesRepository;
use App\Repositories\Triumph\BracketSetsRepository;
use App\Repositories\Triumph\EventsRepository;
use App\Events\triumph\BracketsStatus;
use Illuminate\Support\Arr;
use App\Http\Controllers\Controller;
use App\Jobs\NotificationsJob;
use App\Jobs\WebSocketJob;
use App\Models\Triumph\BracketEntries;
use App\Models\Triumph\Members;
use Illuminate\Support\Carbon;
use App\Services\WebsocketService;

class BracketEntryController extends Controller
{
    protected $bracketsRepository;
    protected $eventsRepository;
    protected $bracketEntriesRepository;
    protected $bracketSetsRepository;

    public function __construct(BracketsRepository $bracketsRepository, EventsRepository $eventsRepository, BracketEntriesRepository $bracketEntriesRepository, BracketSetsRepository $bracketSetsRepository)
    {
        $this->bracketsRepository = $bracketsRepository;
        $this->eventsRepository = $eventsRepository;
        $this->bracketEntriesRepository = $bracketEntriesRepository;
        $this->bracketSetsRepository = $bracketSetsRepository;
    }

    public function index(Request $request)
    {
        return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => __('messages.Bad Request'),
            'data' => []
        ], 404);
    }

    private function getMatch(int $participants)
    {
        $round = log($participants, 2);
        if (!is_int($round) && $round != ceil($round)) {
            return [];
        }
        $aMatche = [];
        for ($i = 0; $i < $round; $i++) {
            $aMatche[] = pow(2, $round - $i);
        }
        return $aMatche;
    }

    public function store(Request $request, $eventId)
    {
        return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => __('messages.Bad Request'),
            'data' => []
        ], 404);
    }

    public function show($id)
    {
        return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => __('messages.Bad Request'),
            'data' => []
        ], 404);
    }

    public function update(Request $request, $eventId, $bracketId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'participant_id' => 'required|numeric',
                'score' => 'required|numeric',
                'status' => 'required|numeric|in:1,2',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId
            ]);
            if (!$aEvent || $aEvent->status > 3) {
                throw new \Exception(__('messages.Event not found'), 404); //존재하지 않는 이벤트입니다.
            }
            $aMemberInfo = $request->user();
            if (!in_array($aMemberInfo->member_id, [$aEvent->member_id])) {
                throw new \Exception(__('messages.Unauthorized'), 403); //권한이 없습니다.
            }
            $aBracket = $this->bracketsRepository->getBracketBracketId([
                'bracket_id' => $bracketId,
            ]);
            if (!$aBracket || $aBracket->event_id != $eventId) {
                throw new \Exception(__('messages.Event, bracket, or entry information is invalid'), 422); //이벤트 또는 브라켓 또는 엔트리 정보가 잘못되었습니다.
            }
            $aEntry = $this->bracketEntriesRepository->getBracketEntryBracketId([
                'bracket_id' => $bracketId,
            ]);
            if (!$aEntry) {
                throw new \Exception(__('messages.Event, bracket, or entry information is invalid'), 422); //이벤트 또는 브라켓 또는 엔트리 정보가 잘못되었습니다.
            }

            // 동점 여부 비교 시작
            $iAnotherEntryScore = null;
            $iAnotherEntryStatus = null;
            $iAnotherParticipantId = null;
            foreach ($aEntry as $entry) {
                // 엔트리 두개 중 내가 아닌 상대 id, score, status 저장
                if ($entry->participant_id != $aValidated['participant_id']) {
                    $iAnotherEntryScore = $entry->score;
                    $iAnotherEntryStatus = $entry->status;
                    $iAnotherParticipantId = $entry->participant_id;
                }
            }

            // participant_id 에 0이 있으면
            if($aValidated['participant_id'] === 0 || $iAnotherParticipantId === 0){
                throw new \Exception(__('messages.Event, bracket, or entry information is invalid'), 422); //이벤트 또는 브라켓 또는 엔트리 정보가 잘못되었습니다.
            }

            // 내가 보내는 점수 = 0 + 상대의 점수 = 0 + 상대의 상태 < 2 -> 아직 상대의 점수도 저장 안된 상태에서 내 점수가 0점인 것 -> pass
            // 내가 보내는 점수 != 0 or 상대의 점수 != 0 or 상대의 상태 >= 2
            //  + 내가 보내는 점수 = 상대의 점수 -> 동점 -> 상대 점수
            if ($aValidated['score'] !== 0) {
                if ($aValidated['score'] === $iAnotherEntryScore) {
                    // 상대 점수 초기화
                    $this->bracketEntriesRepository->updateBracketEntry([
                        'bracket_id' => $bracketId,
                        'participant_id' => $iAnotherParticipantId,
                        'score' => 0,
                        'status' => $iAnotherEntryStatus,
                    ]);
                    throw new \Exception(__('messages.Event, bracket, or entry information is invalid'), 422); //이벤트 또는 브라켓 또는 엔트리 정보가 잘못되었습니다.
                }
            }
            $this->bracketEntriesRepository->updateBracketEntry([
                'bracket_id' => $bracketId,
                'participant_id' => $aValidated['participant_id'],
                'score' => $aValidated['score'],
                'status' => 2,
            ]);

            $aSet = $this->bracketSetsRepository->getBracketSetIdAll([
                'bracket_id' => $bracketId,
            ]);
            if (!$aSet) {
                throw new \Exception(__('messages.Set information has not been reflected yet'), 400); //셋트정보가 아직 반영되지 않았습니다.
            }
            $aEndEntry = [
                'isEnd' => false,
                'info' => [],
            ];
            if (count($aSet) > 1 && $aBracket->status < 4) {
                $aEndEntry['isEnd'] = true;
            }
            if ($aEndEntry['isEnd']) {
                foreach ($aEntry as $key => $item) {
                    if ($aBracket->match_point == $item->score) {
                        $aEndEntry['info'][] = $item;
                    } elseif (
                        $aBracket->match_point == $aValidated['score']
                        && $item->participant_id == $aValidated['participant_id']
                    ) {
                        $aEndEntry['info'][] = $item;
                    }
                }

                //완전 종료일경우 브라켓 업데이트
                if (count($aEndEntry['info']) > 0) {
                    $iWinnerId = $aEndEntry['info'][0]->participant_id;
                    $aParamBracket = [
                        'bracket_id' => $aBracket->bracket_id,
                        'event_id' => $aBracket->event_id,
                        'depth' => $aBracket->depth,
                        'order' => $aBracket->order,
                        'match_point' => $aBracket->match_point,
                        'winner_entrant_id' => $iWinnerId,
                        'status' => 4, //강제 완료
                    ];
                    // 웹소켓 : 판정 완료
                    $webSocketService = new WebsocketService(
                        $request->bearerToken(),
                        'bracket-' . $bracketId,
                        'finish'
                    );
                    $webSocketService->flow();
                    // todo 웹소켓 작업이 먼저 선행된후 큐로 변경
                    // WebSocketJob::dispatch(
                    //     $request->bearerToken(),
                    //     'bracket-' . $aBracket->bracket_id,
                    //     'finish'
                    // );
                    $aUpdateBracket = $this->bracketsRepository->updateBracket($aParamBracket); //브라켓 업데이트
                    if ($aUpdateBracket->RETURN != 'SUC') {
                        throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
                    }

                    // 알림
                    $aNotificationMemberId = [];
                    if ($aEvent->team_size > 1) {
                        //팀전
                        $aBracketEntries = BracketEntries::with(['participants.participant_members'])
                        ->where('bracket_id', $aBracket->bracket_id)->get();
                        foreach ($aBracketEntries as $bracketEntry) {
                            foreach ($bracketEntry->participants->participant_members as $team_member) {
                                if ($team_member->member_id !== 0) {
                                    $aNotificationMemberId[] = $team_member->member_id;
                                }
                                if ($team_member->create_member_id != $aEvent->member_id) {
                                    //주최자 제외
                                    $aNotificationMemberId[] = $team_member->create_member_id;
                                }
                            }
                        }
                    } else {
                        // 개인전
                        $aBracketEntries = BracketEntries::with(['participants'])
                        ->where('bracket_id', $aBracket->bracket_id)->get();
                        foreach ($aBracketEntries as $bracketEntry) {
                            if ($bracketEntry->participants->entrant_id) {
                                $aNotificationMemberId[] = $bracketEntry->participants->entrant_id;
                            }
                        }
                    }
                    foreach (collect($aNotificationMemberId)->unique() as $notificationId) {
                        $aNotificationMember = Members::find($notificationId);
                        NotificationsJob::dispatch(
                            'createNotification',
                            [
                                'lang' => $aNotificationMember->language ?? 'en',
                                'template' => 'match_judgment_completed',
                                'bind' => [
                                    '[name]' => '',
                                ]
                            ],
                            [
                                'member_id' => $aNotificationMember->member_id,
                                'scheduled_time' => Carbon::now()->timestamp,  // 현재 시간 또는 특정 시간
                                'link' => "/event/{$eventId}/bracket/{$bracketId}",
                                'tag' => "event-{$eventId}",
                                'type' => 'event',
                                'profile_img' => $aMemberInfo->image_url,
                            ]
                        );
                    }

                    if ($aBracket->depth > 2) {
                        //2강 제외하고 업데이트
                        $aUpBracket = $this->bracketsRepository->getBracketDepthOrder([
                            'event_id' => $aBracket->event_id,
                            'depth' => $aBracket->depth / 2,
                            'order' => ceil($aBracket->order / 2)
                        ]);
                        if (!$aUpBracket) {
                            throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
                        }
                        // todo 더미 체크
                        $aInsertEntryWin = $this->bracketEntriesRepository->insertBracketEntry([
                            'participant_id' => $iWinnerId,
                            'bracket_id' => $aUpBracket->bracket_id,
                            'score' => 0,
                            'status' => 0, //더미면 2가 들어가야함
                        ]);
                        if ($aInsertEntryWin->RETURN != 'SUC') {
                            throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
                        }
                        if ($aBracket->depth == 4 && $aEvent->match34 == 1) {
                            //3~4위전 업데이트
                            $aEndEntry['info'] = array_values(array_filter($aEntry, function ($item) use ($iWinnerId) {
                                return $item->participant_id != $iWinnerId;
                            }));
                            $loseEntryId = $aEndEntry['info'][0]->participant_id;

                            //아래단계 가져오기
                            $aDownBracket = $this->bracketsRepository->getBracketDepthOrder([
                                'event_id' => $aBracket->event_id,
                                'depth' => 2,
                                'order' => 2
                            ]);
                            if (!$aDownBracket) {
                                throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
                            }
                            if ($loseEntryId > 0) {
                                $aInsertEntryLose = $this->bracketEntriesRepository->insertBracketEntry([
                                    'participant_id' => $loseEntryId,
                                    'bracket_id' => $aDownBracket->bracket_id,
                                    'score' => 0,
                                    'status' => 0,
                                ]);
                                if ($aInsertEntryLose->RETURN != 'SUC') {
                                    throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
                                }
                            }
                        }
                    } else {
                        //결승 또는 3,4위전일때
                        $aEndBracket = $this->bracketsRepository->getBracketEndGame([
                            'event_id' => $eventId,
                            'depth' => 2, // 결승, 3/4위전
                            'status' => 4, //종료는 4
                        ]);
                        if (($aEvent->match34 == 1 && count($aEndBracket) == 2) || ($aEvent->match34 == 0 && count($aEndBracket) == 1)) {
                            //완전종료
                            $aUpdateEvent = $this->eventsRepository->updateEventStatus([
                                'event_id' => $eventId,
                                'status' => 3, //이벤트 종료
                            ]);
                            if (!$aUpdateEvent) {
                                throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
                            }
                        }
                    }
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
            // 실패 시 반환
            $aJsonData = [
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    public function contingencyUpdate(Request $request, $eventId, $bracketId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'participant_id' => 'required|array',
                'participant_id.*' => 'required|numeric',
                'score' => 'required|array',
                'score.*' => 'required|numeric',
                'status' => 'required|numeric|in:7,8,9',
                'winner' => 'required|numeric',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            // winner = 0 인 경우는 status = 9 일 때만 가능
            if ($aValidated['winner'] === 0 && $aValidated['status'] != 9) {
                throw new \Exception(__('messages.Bad Request') . '(2)', 400);
            }
            // winner != 0 이면 winner 검증
            if ($aValidated['winner'] !== 0) {
                if (!in_array($aValidated['winner'], $aValidated['participant_id'])) {
                    throw new \Exception(__('messages.Bad Request') . '(3)', 400);
                }
            }
            // participant_id, score 검증
            if (count($aValidated['participant_id']) != 2 || count($aValidated['score']) != 2) {
                throw new \Exception(__('messages.Bad Request') . '(4)', 400);
            }
            // participant_id 에 0이 있으면
            if (in_array(0, $aValidated['participant_id'])) {
                if (array_count_values($aValidated['participant_id'])[0] == 2) { // 둘 다 0이면 status = 9 && winner = 0 만 통과
                    if (!($aValidated['status'] == 9 && $aValidated['winner'] === 0)) {
                        throw new \Exception(__('messages.Bad Request') . '(5_1)', 400);
                    }
                } else { // 하나만 0이면 status = 7 && winner != 0 만 통과
                    if (!($aValidated['status'] == 7 && $aValidated['winner'] !== 0)) {
                        throw new \Exception(__('messages.Bad Request') . '(5_2)', 400);
                    }
                }
            }

            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId
            ]);
            if (!$aEvent || $aEvent->status > 3) {
                throw new \Exception(__('messages.Event not found'), 404); //존재하지 않는 이벤트입니다.
            }
            $aMemberInfo = $request->user();
            if (!in_array($aMemberInfo->member_id, [$aEvent->member_id])) {
                throw new \Exception(__('messages.Unauthorized'), 403); //권한이 없습니다.
            }

            $aBracket = $this->bracketsRepository->getBracketBracketId([
                'bracket_id' => $bracketId,
            ]);
            if (!$aBracket || $aBracket->event_id != $eventId) {
                throw new \Exception(__('messages.Event, bracket, or entry information is invalid'), 422); //이벤트 또는 브라켓 또는 엔트리 정보가 잘못되었습니다.
            }

            // 점수 검증
            $iTotalScore = 0;
            foreach ($aValidated['score'] as $score) {
                if ($aBracket->match_point < $score) {
                    throw new \Exception(__('messages.Bad Request') . '(6)', 400);
                }
                $iTotalScore += $score;
            }
            if ($iTotalScore >= ($aBracket->match_point * 2)) {
                throw new \Exception(__('messages.Bad Request') . '(7)', 400);
            }

            $aEntry = $this->bracketEntriesRepository->getBracketEntryBracketId([
                'bracket_id' => $bracketId,
            ]);
            if (!$aEntry) {
                throw new \Exception(__('messages.Event, bracket, or entry information is invalid'), 422); //이벤트 또는 브라켓 또는 엔트리 정보가 잘못되었습니다.
            }

            // participant_id 검증
            foreach ($aEntry as $key => $entry) {
                if (!in_array($entry->participant_id, $aValidated['participant_id'])) {
                    throw new \Exception(__('messages.Bad Request') . '(8)', 400);
                }
            }

            $iLoserId = 0;
            $aParamBracketEntry = [];
            // 승패 구분 + 부전승/기권/몰수패 처리
            foreach ($aValidated['participant_id'] as $key => $participant) {
                if ($participant == $aValidated['winner']) { // 승자
                    if ($aValidated['status'] == 7) { // 부전승일때 승자에 기록
                        $aParamBracketEntry = [
                            'bracket_id' => $bracketId,
                            'participant_id' => $participant,
                            'score' => $aValidated['score'][$key],
                            'status' => $aValidated['status'], // 7
                        ];
                    } else { // 기권/몰수패일때 일반 상태
                        $aParamBracketEntry = [
                            'bracket_id' => $bracketId,
                            'participant_id' => $participant,
                            'score' => $aValidated['score'][$key],
                            'status' => 2,
                        ];
                    }
                } else { // 패자
                    $iLoserId = $aValidated['winner'] === 0 && $aValidated['status'] == 9 ? 0 : $participant;
                    if ($aValidated['status'] == 7) { // 부전패일 때 일반 상태
                        $aParamBracketEntry = [
                            'bracket_id' => $bracketId,
                            'participant_id' => $participant,
                            'score' => $aValidated['score'][$key],
                            'status' => 2
                        ];
                    } else { // 기권/몰수패일때 패자에 기록
                        $aParamBracketEntry = [
                            'bracket_id' => $bracketId,
                            'participant_id' => $participant,
                            'score' => $aValidated['score'][$key],
                            'status' => $aValidated['status'], // 8 or 9
                        ];
                    }
                }
                $this->bracketEntriesRepository->updateBracketEntry($aParamBracketEntry); //브라켓 업데이트
            }

            // entry 데이터 다시 가져오기
            $aEntryAfterUpdate = $this->bracketEntriesRepository->getBracketEntryBracketId([
                'bracket_id' => $bracketId,
            ]);

            $aEndEntry = [
                'isEnd' => false,
                'info' => [],
            ];

            // entry 둘 다 update 되었는지 체크
            $iEntryUpdateCheckCount = 0;
            foreach ($aEntryAfterUpdate as $entryAfterUpdate) {
                if ($entryAfterUpdate->status > 1) {
                    $iEntryUpdateCheckCount++;
                }
                // 승자 데이터 입력
                if ($entryAfterUpdate->participant_id == $aValidated['winner']) {
                    $aEndEntry['info'][] = $entryAfterUpdate;
                }
            }

            if ($iEntryUpdateCheckCount === 2 && $aBracket->status < 4) {
                $aEndEntry['isEnd'] = true;
            }
            // 둘다 업데이트 안되었으면
            if (!$aEndEntry['isEnd']) {
                throw new \Exception(__('messages.Please contact the administrator') . '(1)', 403); //관리자에게 문의 부탁드립니다.
            }

            // 승자 데이터 입력되었거나 둘다 몰수패이면
            if (count($aEndEntry['info']) > 0 || ($aValidated['status'] == 9 && $aValidated['winner'] === 0)) {
                // 브라켓 승자 입력, 브라켓 종료
                $aParamBracket = [
                    'bracket_id' => $aBracket->bracket_id,
                    'event_id' => $aBracket->event_id,
                    'depth' => $aBracket->depth,
                    'order' => $aBracket->order,
                    'match_point' => $aBracket->match_point,
                    'winner_entrant_id' => $aValidated['winner'],
                    'status' => 4, //강제 완료
                ];
                // 웹소켓 : 판정 완료
                $webSocketService = new WebsocketService(
                    $request->bearerToken(),
                    'bracket-' . $bracketId,
                    'finish'
                );
                $webSocketService->flow();
                // todo 웹소켓 작업이 먼저 선행된후 큐로 변경
                // WebSocketJob::dispatch(
                //     $request->bearerToken(),
                //     'bracket-' . $aBracket->bracket_id,
                //     'finish'
                // );

                // 브라켓 업데이트
                $this->bracketsRepository->updateBracket($aParamBracket);

                if ($aBracket->depth > 2) {
                    //2강 제외하고 업데이트
                    $aUpBracket = $this->bracketsRepository->getBracketDepthOrder([
                        'event_id' => $aBracket->event_id,
                        'depth' => $aBracket->depth / 2,
                        'order' => ceil($aBracket->order / 2)
                    ]);
                    if (!$aUpBracket) {
                        throw new \Exception(__('messages.Please contact the administrator') . '(3)', 403); //관리자에게 문의 부탁드립니다.
                    }

                    // todo 더미 체크
                    $aInsertEntryWin = $this->bracketEntriesRepository->insertBracketEntry([
                        'participant_id' => $aValidated['winner'],
                        'bracket_id' => $aUpBracket->bracket_id,
                        'score' => 0,
                        'status' => 0, //더미면 2가 들어가야함
                    ]);
                    if ($aInsertEntryWin->RETURN != 'SUC') {
                        throw new \Exception(__('messages.Please contact the administrator') . '(4)', 403); //관리자에게 문의 부탁드립니다.
                    }

                    //3~4위전 업데이트
                    if ($aBracket->depth == 4 && $aEvent->match34 == 1) {
                        //아래단계 가져오기
                        $aDownBracket = $this->bracketsRepository->getBracketDepthOrder([
                            'event_id' => $aBracket->event_id,
                            'depth' => 2,
                            'order' => 2
                        ]);
                        if (!$aDownBracket) {
                            throw new \Exception(__('messages.Please contact the administrator') . '(5)', 403); //관리자에게 문의 부탁드립니다.
                        }

                        // 3,4위전 엔트리 등록
                        $aInsertEntryLose = $this->bracketEntriesRepository->insertBracketEntry([
                            'participant_id' => $iLoserId,
                            'bracket_id' => $aDownBracket->bracket_id,
                            'score' => 0,
                            'status' => 0,
                        ]);
                        if ($aInsertEntryLose->RETURN != 'SUC') {
                            throw new \Exception(__('messages.Please contact the administrator') . '(6)', 403); //관리자에게 문의 부탁드립니다.
                        }
                    }
                } else {
                    //결승 또는 3,4위전일때
                    $aEndBracket = $this->bracketsRepository->getBracketEndGame([
                        'event_id' => $eventId,
                        'depth' => 2, // 결승, 3/4위전
                        'status' => 4, //종료는 4
                    ]);
                    if (($aEvent->match34 == 1 && count($aEndBracket) == 2) || ($aEvent->match34 == 0 && count($aEndBracket) == 1)) {
                        //완전종료
                        $aUpdateEvent = $this->eventsRepository->updateEventStatus([
                            'event_id' => $eventId,
                            'status' => 3, //이벤트 종료
                        ]);
                        if (!$aUpdateEvent) {
                            throw new \Exception(__('messages.Please contact the administrator') . '(7)', 403); //관리자에게 문의 부탁드립니다.
                        }
                    }
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
            // 실패 시 반환
            $aJsonData = [
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    public function destroy($id)
    {
        return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => __('messages.Bad Request'),
            'data' => []
        ], 404);
    }
}
