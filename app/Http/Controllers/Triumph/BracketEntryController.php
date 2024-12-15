<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Triumph\BracketsRepository;
use App\Repositories\Triumph\BracketEntriesRepository;
use App\Repositories\Triumph\EventsRepository;
use App\Events\triumph\BracketsStatus;
use App\Jobs\NotificationsJob;
use App\Models\Triumph\Members;
use App\Models\Triumph\ParticipantMembers;
use App\Models\Triumph\Participants;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class BracketEntryController extends Controller
{
    protected $bracketsRepository;
    protected $eventsRepository;
    protected $bracketEntriesRepository;

    public function __construct(
        BracketsRepository $bracketsRepository,
        EventsRepository $eventsRepository,
        BracketEntriesRepository $bracketEntriesRepository
    ) {
        $this->bracketsRepository = $bracketsRepository;
        $this->eventsRepository = $eventsRepository;
        $this->bracketEntriesRepository = $bracketEntriesRepository;
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

    public function create()
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
        try {
            $validator = Validator::make($request->all(), [
                'depth' => 'required|numeric',
                'entries' => 'required|array',
                'entries.*' => 'required|numeric',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();
            if (count($aValidated['entries']) != count(array_unique($aValidated['entries']))) {
                throw new \Exception(__('messages.Cannot add entry information') . '(1)', 403); //엔트리 정보를 추가할 수 없습니다.
            }
            if ($aValidated['depth'] != count($aValidated['entries'])) {
                throw new \Exception(__('messages.Cannot add entry information') . '(2)', 403); //엔트리 정보를 추가할 수 없습니다.
            }
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId
            ]);
            if (!$aEvent || $aEvent->status > 3) {
                throw new \Exception(__('messages.Event not found'), 404); //존재하지 않는 이벤트입니다.
            }
            $aMemberInfo = $request->user();
            if ($aMemberInfo->member_id != $aEvent->member_id) {
                throw new \Exception(__('messages.Only the organizer can make modifications'), 403); // 주최자만 수정할 수 있습니다.
            }
            if (count($aValidated['entries']) > $aEvent->participant_capacity) {
                throw new \Exception(__('messages.The number of matches exceeds the total participants or teams'), 400); //총 참여 인원 또는 팀보다 경기수가 많습니다.
            }
            $aMetchAll = $this->getMatch($aEvent->participant_capacity);
            if (count($aMetchAll) < 1 || !in_array($aValidated['depth'], $aMetchAll)) {
                throw new \Exception(__('messages.The match points and round information do not match'), 403); //매치포인트의 수와 라운드의 정보가 일치하지 않습니다.
            }
            if ($aEvent->participant_capacity != $aValidated['depth']) {
                throw new \Exception(__('messages.Cannot add entry information') . '(3)', 403); //엔트리 정보를 추가할 수 없습니다.
            }

            // entries에 해당하는 participants 체크인 상태 검증
            $aParticipantId = Participants::whereIn('participant_id', $aValidated['entries'])
                ->where('event_id', $eventId)
                ->whereNotNull('checkin_dt')
                ->pluck('participant_id');
            $aValidatedEntry = array_map('intval', Arr::wrap($aValidated['entries']));
            if(!empty(array_diff($aValidatedEntry, $aParticipantId->all()))){
                throw new \Exception(__('messages.Bad Request'), 400);
            }

            //주최자 및 참가자 전원 받는사람
            //주최자 : $aEvent->member_id
            // participant_id 검증
            if ($aEvent->team_size === 1) {
                // 개인전
                $aParticipantMemberId = Participants::whereIn('participant_id', $aValidated['entries'])
                    ->whereNotNull('entrant_id')
                    ->pluck('entrant_id');
                $aParticipantMemberId->push($aEvent->member_id); //주최자 추가
                $aParticipantMemberId = $aParticipantMemberId->unique(); // 중복값 제거
            } elseif ($aEvent->team_size > 1) {
                // 팀전
                $count_participant_members = ParticipantMembers::whereIn('participant_id', $aValidated['entries'])->count();
                if ($count_participant_members === 0) {
                    throw new \Exception(__('messages.Cannot add entry information') . '(4)', 403); //엔트리 정보를 추가할 수 없습니다.
                } else {
                    $aParticipantMemberId = ParticipantMembers::whereIn('participant_id', $aValidated['entries'])
                        ->where('member_id', '!=', 0)
                        ->pluck('member_id');
                    $aParticipantMemberId->push($aEvent->member_id); //주최자 추가
                    $aParticipantMemberId = $aParticipantMemberId->unique(); // 중복값 제거
                }
            } else {
                throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
            }
            $aEntryInsert = $this->bracketEntriesRepository->insertBracketEntrySingle([
                'event_id' => $eventId,
                'depth' => $aValidated['depth'],
                'entries' => implode(',', $aValidated['entries']),
            ]);
            if ($aEntryInsert->RETURN != 'SUC') {
                throw new \Exception(__('messages.Please contact the administrator') . '(1)', 403); //관리자에게 문의 부탁드립니다.
            }
            $aUpdateEvent = $this->eventsRepository->updateEventStatus([
                'event_id' => $eventId,
                'status' => 2, //이벤트 상태 : 진행
            ]);
            if (!$aUpdateEvent) {
                throw new \Exception(__('messages.Please contact the administrator') . '(2)', 403); //관리자에게 문의 부탁드립니다.
            }
            foreach ($aParticipantMemberId as $member_id) {
                // 알림
                NotificationsJob::dispatch(
                    'createNotification',
                    [
                        'lang' => Members::find($member_id)->language ?? 'en',
                        'template' => 'create_tournament_bracket',
                        'bind' => [
                            '[name]' => $aEvent->title,
                        ],
                    ],
                    [
                        'member_id' => $member_id,
                        'scheduled_time' => Carbon::now()->timestamp,  // 현재 시간 또는 특정 시간
                        'link' => "/event/{$eventId}/bracket",
                        'tag' => "event-{$eventId}",
                        'type' => 'event',
                        'profile_img' => $aMemberInfo->image_url,
                    ]
                );
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

    public function show($id)
    {
        return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => __('messages.Bad Request'),
            'data' => []
        ], 404);
    }

    public function edit($id)
    {
        return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => __('messages.Bad Request'),
            'data' => []
        ], 404);
    }

    public function update(Request $request, $eventId, $type)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'bracket_id' => 'required|numeric',
                'participant_id' => 'required|numeric',
                'score' => $type == 'score' ? 'required|numeric' : 'sometimes|numeric',
                'status' => $type == 'status' ? 'required|numeric|in:0,1,2' : 'sometimes|numeric|in:0,1,2',
            ]);

            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();
            // todo   Participants > create_member_id 컬럼과 내 memeber_id가 같은지 확인해야함

            $aMemberInfo = $request->user();
            // 제약사항 : 3초에 2번 수정 금지
            $redisUserKey = $aValidated['service'] . '.member.' . $aMemberInfo->member_id . '.event.bracket.entry.update';
            $aRedisInfo = json_decode(Redis::get($redisUserKey), true);
            if ($aRedisInfo) {
                throw new \Exception(__('messages.Too many requests. Please try again after a while'), 429); //(3초)허용된 요청량보다 많은 요청을 하였습니다. 잠시후 다시 시도해 주시기 바랍니다.
            }
            $aBracket = $this->bracketsRepository->getBracketBracketId([
                'bracket_id' => $aValidated['bracket_id'],
            ]);
            $aEntry = $this->bracketEntriesRepository->getBracketEntryBracketId([
                'bracket_id' => $aValidated['bracket_id'],
            ]);
            if (!$aEntry || !$aBracket || $aBracket->event_id != $eventId) {
                throw new \Exception(__('messages.Event, bracket, or entry information is invalid'), 422); //이벤트 또는 브라켓 또는 엔트리 정보가 잘못되었습니다.
            }

            $aEntryMe = null;
            foreach ($aEntry as $entryitem) {
                if ($entryitem->participant_id == $aValidated['participant_id']) {
                    $aEntryMe = $entryitem;
                    break;
                }
            }
            if (!$aEntryMe) {
                throw new \Exception(__('messages.Entry information not found'), 404); //엔트리 정보를 찾을 수 없습니다.
            }
            // 초기화
            $aValidated['score'] = Arr::get($aValidated, 'score', $aEntryMe->score);
            $aValidated['status'] = Arr::get($aValidated, 'status', $aEntryMe->status);
            //전체 엔트리 상태 가져오기
            $isEntryParticipant = false; //true 참가자 맞음
            foreach ($aEntry as $item) {
                if ($item->participant_id == $aValidated['participant_id']) {
                    $isEntryParticipant = true;
                }
            }
            if (!$isEntryParticipant) {
                // 잘못된 브라켓 정보
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            if ($type == 'status' && $aValidated['status'] < $aEntryMe->status) {
                throw new \Exception(__('messages.Cannot revert to the previous state'), 403); //이전 상태로는 변경할 수 없습니다.
            }
            $aEntryUpdate = $this->bracketEntriesRepository->updateBracketEntry([
                'bracket_id' => $aValidated['bracket_id'],
                'participant_id' => $aValidated['participant_id'],
                'score' => $aValidated['score'],
                'status' => $aValidated['status'],
            ]);

            // if (!$aEntryUpdate) {
            // todo 리턴값 받아야함
            //     throw new \Exception(__('messages.관리자에게 문의 부탁드립니다.'), 400);
            // }

            if ($type == 'status') {
                if ($aValidated['status'] > 0) {
                    event(new BracketsStatus(
                        $aValidated['bracket_id'],
                        $aValidated['status'],
                    ));
                    $aBracketParams = [
                        'bracket_id' => $aValidated['bracket_id'],
                        'event_id' => $eventId,
                        'depth' => $aBracket->depth,
                        'order' => $aBracket->order,
                        'match_point' => $aBracket->match_point,
                        'winner_entrant_id' => $aBracket->winner_entrant_id,
                        'status' => $aValidated['status'],
                    ];
                    $this->bracketEntriesRepository->updateBracketEntry([
                        'bracket_id' => $aValidated['bracket_id'],
                        'participant_id' => $aValidated['participant_id'],
                        'score' => $aEntryMe->score,
                        'status' => $aBracketParams['status'],
                    ]);

                    $aEntryStatus = ['complete' => true, 'ready' => true];
                    foreach ($aEntry as $entryitem) {
                        if ($entryitem->bracket_id == $aValidated['bracket_id'] && $entryitem->participant_id == $aValidated['participant_id']) {
                            $entryitem->status = $aBracketParams['status'];
                        }
                        if (in_array($entryitem->status, [0, 1])) {
                            $aEntryStatus['complete'] = false;
                        } elseif (in_array($entryitem->status, [0, 2])) {
                            $aEntryStatus['ready'] = false;
                        }
                    }

                    if ($aEntryStatus['complete']) {
                        // 모든 경기가 끝났을때
                        $aBracketParams['status'] = 2;
                        $this->bracketsRepository->updateBracket($aBracketParams);
                    } elseif ($aEntryStatus['ready']) {
                        // 모든 경기가 진행
                        $aBracketParams['status'] = 1;
                        $this->bracketsRepository->updateBracket($aBracketParams);
                    }
                }
            } elseif ($type == 'score') {
                if ($aEntryMe->status < 2) {
                    throw new \Exception(__('messages.The match is not finished. Cannot input the score'), 403); //경기가 끝나지 않았습니다. 스코어를 입력할 수 없습니다.
                }
                $this->bracketEntriesRepository->updateBracketEntry([
                    'bracket_id' => $aValidated['bracket_id'],
                    'participant_id' => $aValidated['participant_id'],
                    'score' => $aValidated['score'],
                    'status' => $aEntryMe->status,
                ]);
            }
            // 제약사항 : 3초 안에 2번 수정 금지
            Redis::setex($redisUserKey, 3, json_encode([
                'bracket_id' => $aValidated['bracket_id'],
                'participant_id' => $aValidated['participant_id'],
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
