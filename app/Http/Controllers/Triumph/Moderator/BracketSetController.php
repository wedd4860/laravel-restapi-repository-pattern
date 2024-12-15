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

    public function index(Request $request)
    {
        return response()->json([
            "status" => "error",
            "code" => 404,
            "message" => __('messages.Bad Request'),
            "data" => []
        ], 404);
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
            $aMemberInfo = $request->user();
            if (!in_array($aMemberInfo->member_id, [$aEvent->member_id])) {
                throw new \Exception(__('messages.Unauthorized'), 403); //권한이 없습니다.
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

            // $aUpdateEntry = $this->bracketEntriesRepository->updateBracketEntry([
            //     'bracket_id' => $bracketId,
            //     'participant_id' => $aValidated['participant_id'],
            //     'score' => $aBracketEntry->score,
            //     'status' => 2,
            // ]);
            // if ($aUpdateEntry->RETURN != 'SUC') {
            //     throw new \Exception(__('messages.Please contact the administrator 1'), 403); //관리자에게 문의 부탁드립니다.
            // }

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
                "status" => "success",
                "code" => 200,
                "message" => __('messages.Request successful'),
                "data" => []
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            // 실패 시 반환
            $aJsonData = [
                "status" => "error",
                "code" => $e->getCode(),
                "message" => $e->getMessage(),
                "data" => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    public function show($id)
    {
        return response()->json([
            "status" => "error",
            "code" => 404,
            "message" => __('messages.Bad Request'),
            "data" => []
        ], 404);
    }

    public function update(Request $request)
    {
        return response()->json([
            "status" => "error",
            "code" => 404,
            "message" => __('messages.Bad Request'),
            "data" => []
        ], 404);
    }

    public function destroy($id)
    {
        return response()->json([
            "status" => "error",
            "code" => 404,
            "message" => __('messages.Bad Request'),
            "data" => []
        ], 404);
    }
}
