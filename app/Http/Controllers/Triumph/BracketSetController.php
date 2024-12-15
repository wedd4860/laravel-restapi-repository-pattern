<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Triumph\BracketsRepository;
use App\Repositories\Triumph\BracketEntriesRepository;
use App\Repositories\Triumph\BracketSetsRepository;
use App\Repositories\Triumph\EventsRepository;
use App\Events\triumph\BracketsStatus;
use App\Jobs\NotificationsJob;
use App\Models\Triumph\BracketSets;
use App\Models\Triumph\Members;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;

class BracketSetController extends Controller
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

    public function index(Request $request, $eventId, $bracketId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'participant_id' => 'required|numeric',
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
            // 팀원인지 확인
            $aMemberInfo = $request->user();
            $aBracketEntryMember = $this->bracketEntriesRepository->getBracketEntryMemberId([
                'bracket_id' => $bracketId,
                'participant_id' => $aValidated['participant_id'],
            ]);
            $iEntryLeader = null;
            $aEntrymember = [];
            foreach ($aBracketEntryMember as $entryMember) {
                $iEntryLeader = $entryMember->create_member_id;
                $aEntrymember[] = $entryMember->member_id;
            }
            if (!in_array($aMemberInfo->member_id, [$aEvent->member_id, $iEntryLeader]) && !in_array($aMemberInfo->member_id, $aEntrymember)) {
                throw new \Exception(__('messages.Unauthorized'), 403); //권한이 없습니다.
            }
            $aSet = $this->bracketSetsRepository->getBracketSetId([
                'bracket_id' => $bracketId,
                'participant_id' => $aValidated['participant_id'],
            ]);
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => [
                    'set' => $aSet,
                ]
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

    public function store(Request $request, $eventId, $bracketId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'participant_id' => 'required|numeric',
                'winlose' => 'required|array',
                'winlose.*' => 'required|numeric',
                'judge_image_url' => 'required|array',
                'judge_image_url.*' => 'nullable|url',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();
            if (count($aValidated['judge_image_url']) != count(array_unique($aValidated['judge_image_url']))) {
                $iCntNull = 0;
                foreach ($aValidated['judge_image_url'] as $item) {
                    if (!$item) {
                        $iCntNull++;
                    }
                }
                if ($iCntNull > 0) {
                    $tmpICntNull = count(array_unique($aValidated['judge_image_url'])) - 1 + $iCntNull;
                    if (count($aValidated['judge_image_url']) != $tmpICntNull) {
                        throw new \Exception(__('messages.Duplicate image found'), 403); //중복된 이미지가 있습니다.
                    }
                }
            }
            if (count($aValidated['winlose']) != count($aValidated['judge_image_url'])) {
                throw new \Exception(__('messages.The quantity of recorded judgments and outcomes do not match'), 422); //판정 이미지와 승패의 기록된 수량이 일치하지 않습니다.
            }
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId
            ]);
            if (!$aEvent || $aEvent->status > 3) {
                throw new \Exception(__('messages.Event not found'), 404); //존재하지 않는 이벤트입니다.
            }
            //브라켓 엔트리만 체크
            $aBracketEntry = $this->bracketEntriesRepository->getBracketEntryId([
                'participant_id' => $aValidated['participant_id'],
                'bracket_id' => $bracketId,
            ]);
            if (!$aBracketEntry) {
                //잘못된 엔트리 정보
                throw new \Exception(__('messages.The match points and round information do not match'), 403); //매치포인트의 수와 라운드의 정보가 일치하지 않습니다.
            }
            // 구자현 todo
            // BracketEntry->status = 2일 BracketSets 업데이트 불가
            // -> BracketEntry->status = 2 상태는 둘다 준비 완료가 아님
            // bracket이 진행된 이후에 점수 등록 완료 or 경기 종료 로 정의 변경해야할듯
            // if (!$aBracketEntry->status < 2) { // <- 0일때 말고 다 예외처리 당함. 준비가 1이라면 얘도 짤림
            if ($aBracketEntry->status != 2) { // <- 2 이상일 때 예외처리
                throw new \Exception(__('messages.Cannot modify the bracket while it is in preparation or ongoing'), 403); //브라켓이 준비 또는 진행 중일 때에는 수정할 수 없습니다.
            }
            $aMemberInfo = $request->user();
            if (!in_array($aMemberInfo->member_id, [$aEvent->member_id, $aValidated['participant_id'], $aBracketEntry->create_member_id])) {
                throw new \Exception(__('messages.Input or modification is only allowed for the organizer or team leader'), 403); //입력 또는 수정은 주최자 또는 팀장만 할 수 있습니다.
            }
            // delect > insert 진행
            // $aSet = $this->bracketSetsRepository->getBracketSetIdAll([
            //     'bracket_id' => $bracketId,
            // ]);
            // if ($aSet) {
            //     foreach ($aSet as $item) {
            //         if ($item->participant_id == $aValidated['participant_id']) {
            //             throw new \Exception(__('messages.The match results have already been recorded'), 403); //이미 경기결과를 기록하였습니다.
            //         }
            //     }
            // }
            // 브라켓 매치포인트 확인
            $aBracket = $this->bracketsRepository->getBracketBracketId([
                'bracket_id' => $bracketId,
            ]);
            if (!$aBracket) {
                throw new \Exception(__('Bracket information not found.'), 404); //브라켓 정보를 찾을 수 없습니다.
            }
            $iMaxMatch = ($aBracket->match_point * 2) - 1;
            // 이벤트 매치포인트 확인
            if ($iMaxMatch < count($aValidated['winlose'])) {
                throw new \Exception(__('messages.The match points and round information do not match'), 403); //매치포인트의 수와 라운드의 정보가 일치하지 않습니다.
            }
            // 알림
            if (BracketSets::where('bracket_id', $bracketId)->count() === 0) {
                // 최초 1회 알림 보내기 작업
                $aNotificationMember = Members::find($aEvent->member_id);
                NotificationsJob::dispatch(
                    'createNotification',
                    [
                        'lang' => $aNotificationMember->language ?? 'en',
                        'template' => 'match_results_submit',
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
            // todo participant_id 검증 필요함
            $aSetInsert = $this->bracketSetsRepository->insertBracketSet([
                'bracket_id' => $bracketId,
                'participant_id' => $aValidated['participant_id'],
                'winlose' => implode(',', $aValidated['winlose']),
                'judge_image_url' => implode(',', $aValidated['judge_image_url']),
            ]);
            if ($aSetInsert->RETURN != 'SUC') {
                throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
            }
            //리턴값 성공 실패 따져야함
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
        return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => __('messages.Bad Request'),
            'data' => []
        ], 404);
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
