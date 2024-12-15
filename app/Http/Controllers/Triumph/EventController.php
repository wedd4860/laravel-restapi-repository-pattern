<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use App\Http\Requests\Triumph\EventRequest;
use App\Http\Resources\Triumph\EventSearchResource;
use App\Http\Resources\Triumph\EventsResource;
use App\Models\Triumph\Brackets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Triumph\EventsRepository;
use App\Repositories\Triumph\BracketsRepository;
use App\Repositories\Triumph\ParticipantsRepository;
use App\Repositories\Triumph\UrlsRepository;
use App\Models\Triumph\Events;
use App\Models\Triumph\Games;
use App\Models\Triumph\Participants;
use App\Services\ShortenURLService;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    protected $eventsRepository;
    protected $bracketsRepository;
    protected $participantsRepository;
    protected $urlsRepository;

    public function __construct(EventsRepository $eventsRepository, BracketsRepository $bracketsRepository, ParticipantsRepository $participantsRepository, UrlsRepository $urlsRepository)
    {
        $this->eventsRepository = $eventsRepository;
        $this->bracketsRepository = $bracketsRepository;
        $this->participantsRepository = $participantsRepository;
        $this->urlsRepository = $urlsRepository;
    }

    public function me(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'sometimes|numeric',
                'order_type' => 'nullable|string|in:date,game,status',
                'order_by' => 'nullable|string|in:desc,asc',
            ]);
            if ($validator->fails()) {
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();
            $aMemberInfo = $request->user();
            $aEvent = ['list' => [], 'cnt'];
            $aEventCnt = $this->eventsRepository->getEventParticipationCount([
                'member_id' => $aMemberInfo->member_id
            ]);
            if (!$aEventCnt) {
                throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
            }
            $aEvent['cnt'] = $aEventCnt->cnt;
            $aValidated['order_type'] = Arr::get($aValidated, 'order_type', 'date');
            $aValidated['order_by'] = Arr::get($aValidated, 'order_by', 'desc');
            if ($aEvent['cnt'] > 0) {
                $aEventList = $this->eventsRepository->getEventParticipationPage([
                    'member_id' => $aMemberInfo->member_id,
                    'page' => $request->input('page', 1),
                    'perPage' => 12,
                    'order_type' => $aValidated['order_type'],
                    'order_by' => $aValidated['order_by'],
                ]);
            } else {
                $aEventList = [];
            }
            $aEvent['list'] = $aEventList;

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => [
                    'event' => $aEvent['list'],
                    'cnt' => $aEvent['cnt'],
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

    public function index(EventRequest $request)
    {
        try {
            $aEvent = Events::with(['games', 'members'])
                ->withCount(['participants' => function ($query) {
                    $query->whereNotNull('checkin_dt');
                }])
                ->whereIn('status', [0, 1, 2, 3])
                ->orderBy('status', 'asc')
                ->orderBy('event_id', 'desc')
                ->limit(12)->get();
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => EventSearchResource::collection($aEvent),
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

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'game_id' => 'required|numeric',
                'title' => 'required|string|max:250',
                'description' => 'sometimes|string|max:10000',
                'image_url' => 'sometimes|nullable|url',
                'password' => 'sometimes|nullable|string',
                'format' => 'required|numeric',
                'team_size' => 'required|numeric',
                'participant_capacity' => 'required|numeric|between:4,1024',
                'match34' => 'required|numeric',
                'event_start_dt' => 'sometimes|date_format:Y-m-d H:i:s',
                'entry_start_dt' => 'sometimes|date_format:Y-m-d H:i:s',
                'entry_end_dt' => 'sometimes|date_format:Y-m-d H:i:s',
                'status' => 'sometimes|numeric|in:0,1,2,3',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();
            if (!$this->geometricSequence($aValidated['participant_capacity'])) {
                throw new \Exception(__('messages.Participant information is incorrect'), 422); //참여자 정보가 잘못되었습니다.
            }

            $aGame = Games::where('game_id', $aValidated['game_id'])->first();
            if (!$aGame) {
                throw new \Exception(__('messages.Bad Request') . '(2)', 400);
            }

            $aMemberInfo = $request->user();
            // 제약사항 : 1분안에 2번 만들기 금지
            $redisUserKey = $aValidated['service'] . '.member.' . $aMemberInfo->member_id . '.event.create';
            $aRedisInfo = Redis::get($redisUserKey);
            if ($aRedisInfo) {
                throw new \Exception(__('messages.Too many requests. Please try again after a while'), 429); //(1분)허용된 요청량보다 많은 요청을 하였습니다. 잠시후 다시 시도해 주시기 바랍니다.
            }
            $iEvent = $this->eventsRepository->insertEvent([
                'game_id' => $aValidated['game_id'],
                'member_id' => $aMemberInfo->member_id,
                'title' => htmlspecialchars($aValidated['title']),
                'description' => htmlspecialchars(Arr::get($aValidated, 'description', '')),
                'image_url' => Arr::get($aValidated, 'image_url', ''),
                'password' => Arr::get($aValidated, 'password', ''),
                'format' => Arr::get($aValidated, 'format', 0),
                'team_size' => $aValidated['team_size'],
                'participant_capacity' => $aValidated['participant_capacity'],
                'match34' => $aValidated['match34'],
                'event_start_dt' => $aValidated['event_start_dt'] ?? '',
                'entry_start_dt' => $aValidated['entry_start_dt'] ?? '',
                'entry_end_dt' => $aValidated['entry_end_dt'] ?? '',
                'status' => 0,
            ]);
            if (!$iEvent) {
                throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
            }
            $aMatch = array_map(function () {
                return 1;
            }, $this->getMatch($aValidated['participant_capacity']));
            $aBracket = $this->bracketsRepository->insertBracketSingle([
                'event_id' => $iEvent,
                'depth' => $aValidated['participant_capacity'],
                'match34' => $aValidated['match34'],
                'match_point' => implode(',', $aMatch),
            ]);
            if ($aBracket->RETURN != 'SUC') {
                throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
            }

            $shortenURLService = new ShortenURLService();
            $isShortenUrl = $shortenURLService->createShortenUrl([
                'original_url' => '/event/' . $iEvent,
                'content_name' => 'event',
                'content_id' => $iEvent,
                'expired_dt' => null,
            ]);
            if (!$isShortenUrl) {
                throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
            }

            // 제약사항 : 1분안에 2번 만들기 금지
            Redis::setex($redisUserKey, 60, json_encode([
                'event_id' => $iEvent
            ])); // 초단위

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => [
                    'event_id' => $iEvent
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

    private function getMatch(int $participants): array
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

    private function geometricSequence($value): bool
    {
        //등비수열
        if ($value == 2 || $value == 0) {
            return true;
        }
        if ($value <= 0 || $value % 2 !== 0) {
            return false;
        }
        $previousValue = $value;
        while ($previousValue > 2) {
            if ($previousValue % 2 !== 0) {
                return false;
            }
            $previousValue = $previousValue / 2;
        }
        return true;
    }

    public function show(Request $request, $eventId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'password' => 'sometimes|string',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                // 기획자요청 모든 에러 404 : https://allo.io/c214351cb99caad12adbe526b6a9
                throw new \Exception(__('messages.Not Found'), 404);
            }
            $aValidated = $validator->validated();
            $event = Events::with(['members', 'games'])
                    ->where('event_id', $eventId)
                    ->where('status', '<', 4)
                    ->withCount(['participants' => function ($query) use ($eventId) {
                        $query->where('event_id', $eventId)->whereNotNull('checkin_dt');
                    }])
                    ->first();
            if (!$event) {
                throw new \Exception(__('messages.Not Found'), 404);
            }
            $aValidated['password'] = Arr::get($aValidated, 'password', '');
            if ($event->password && !Hash::check($aValidated['password'], $event->password)) {
                throw new \Exception(__('messages.Not Found'), 404);
            }

            $shortenURLService = new ShortenURLService();
            $strShortenUrl = $shortenURLService->getShortenUrl([
                'content_name' => 'event',
                'content_id' => $eventId,
            ]);

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => new EventsResource($event, $strShortenUrl)
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

    public function edit($id)
    {
        return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => __('messages.Bad Request'),
            'data' => []
        ], 404);
    }

    public function permission(Request $request, $eventId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Unauthorized'), 403);
            }
            $aValidated = $validator->validated();

            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId,
            ]);
            if (!$aEvent) {
                throw new \Exception(__('messages.Unauthorized'), 403);
            }
            if ($request->user()->member_id != $aEvent->member_id) {
                throw new \Exception(__('messages.Unauthorized'), 403);
            }
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            // 실패 시 반환
            $aJsonData = [
                'status' => 'fail',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    public function update(Request $request, $eventId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'title' => 'required|string|max:100',
                'description' => 'required|string|max:10000',
                'image_url' => 'sometimes|nullable|url',
                'participant_capacity' => 'sometimes|nullable|numeric|between:4,1024',
                'match34' => 'sometimes|nullable|numeric',
                'password' => 'sometimes|string',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                // 기획자요청 모든 에러 404 : https://allo.io/c214351cb99caad12adbe526b6a9
                throw new \Exception(__('messages.Not Found'), 404);
            }
            $aValidated = $validator->validated();
            $aMemberInfo = $request->user();
            // 제약사항 : 10초에 2번 수정 금지
            $redisUserKey = $aValidated['service'] . '.member.' . $aMemberInfo->member_id . '.event.' . $eventId . '.update';
            $aRedisInfo = Redis::get($redisUserKey);
            if ($aRedisInfo) {
                throw new \Exception(__('messages.Not Found'), 404);
            }
            $aEvent = $this->eventsRepository->getEventDetailId([
                'event_id' => $eventId,
            ]);
            if (!$aEvent) {
                throw new \Exception(__('messages.Not Found'), 404);
            }
            if ($aEvent->status > 3) {
                throw new \Exception(__('messages.Not Found'), 404);
            }
            if ($aEvent->password) {
                if (!Arr::get($aValidated, 'password')) {
                    throw new \Exception(__('messages.Not Found'), 404);
                }
                if ($aEvent->password && !Hash::check($aValidated['password'], $aEvent->password)) {
                    throw new \Exception(__('messages.Not Found'), 404);
                }
            }
            if ($aMemberInfo->member_id != $aEvent->member_id) {
                throw new \Exception(__('messages.Not Found'), 404);
            }

            $iParticipantCapacity = $aEvent->participant_capacity;
            $iMatch34 = $aEvent->match34;
            if (
                $aEvent->status === 0
                && !is_null($aValidated['participant_capacity'])
                && !is_null($aValidated['match34'])
                && ($aEvent->participant_capacity != $aValidated['participant_capacity'] || $aEvent->match34 != $aValidated['match34'])
            ) {
                $iParticipantCapacity = $aValidated['participant_capacity'];
                $iMatch34 = $aValidated['match34'];

                if (!$this->geometricSequence($iParticipantCapacity)) {
                    throw new \Exception(__('messages.Not Found'), 404);
                }

                $aMatch = array_map(function () {
                    return 1;
                }, $this->getMatch($iParticipantCapacity));

                $aBracket = $this->bracketsRepository->insertBracketSingle([
                    'event_id' => $eventId,
                    'depth' => $iParticipantCapacity,
                    'match34' => $iMatch34,
                    'match_point' => implode(',', $aMatch),
                ]);
                if ($aBracket->RETURN != 'SUC') {
                    throw new \Exception(__('messages.Not Found'), 404);
                }
            }

            $aValidated['title'] = Arr::get($aValidated, 'title', $aEvent->title);
            $aValidated['description'] = Arr::get($aValidated, 'description', $aEvent->description);
            $aValidated['image_url'] = Arr::get($aValidated, 'image_url', $aEvent->image_url);
            $aUpdateEvent = $this->eventsRepository->updateEventTitleDescription([
                'event_id' => $eventId,
                'title' => htmlspecialchars($aValidated['title']),
                'description' => htmlspecialchars(Arr::get($aValidated, 'description', '')),
                'image_url' => $aValidated['image_url'],
                'participant_capacity' => $iParticipantCapacity,
                'match34' => $iMatch34,
            ]);
            if (!$aUpdateEvent) {
                throw new \Exception(__('messages.Not Found'), 404);
            }

            // 제약사항 : 10초안에 2번 수정 금지
            Redis::setex($redisUserKey, 10, json_encode([
                'event_id' => $eventId
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

    public function destroy(Request $request, $eventId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'password' => 'sometimes|string',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                // 기획자요청 모든 에러 404 : https://allo.io/c214351cb99caad12adbe526b6a9
                throw new \Exception(__('messages.Not Found'), 404);
            }
            $aValidated = $validator->validated();
            $aMemberInfo = $request->user();
            $aEvent = $this->eventsRepository->getEventDetailId([
                'event_id' => $eventId,
            ]);
            if (!$aEvent) {
                throw new \Exception(__('messages.Not Found'), 404);
            }
            if ($aEvent->status > 3) {
                throw new \Exception(__('messages.Not Found'), 404);
            }
            if ($aEvent->password) {
                if (!Arr::get($aValidated, 'password')) {
                    throw new \Exception(__('messages.Not Found'), 404);
                }
                if ($aEvent->password && !Hash::check($aValidated['password'], $aEvent->password)) {
                    throw new \Exception(__('messages.Not Found'), 404);
                }
            }
            if ($aMemberInfo->member_id != $aEvent->member_id) {
                throw new \Exception(__('messages.Not Found'), 404);
            }
            $aUpdateEvent = $this->eventsRepository->deleteEventId([
                'event_id' => $eventId,
            ]);
            if ($aUpdateEvent->RETURN != 'SUC') {
                throw new \Exception(__('messages.Not Found'), 404);
            }

            // 단축 URL 삭제
            $this->urlsRepository->deleteShortenUrl([
                'content_type' => 2, // event = 2
                'content_id' => $eventId,
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

    public function rankerList(Request $request, $eventId)
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
            $event = Events::where('event_id', $eventId)
                ->where('status', 3)
                ->first();
            if (!$event) {
                throw new \Exception(__('messages.Bad Request'), 400);
            }

            $redisEventRankerListKey = $aValidated['service'] . '.event.' . $eventId . '.rankerList';
            $aRankData = json_decode(Redis::get($redisEventRankerListKey), true);

            if (!$aRankData) {
                $resultIds = [];
                $aEventRank = Brackets::with('bracketEntries')
                    ->where('event_id', $eventId)->where('depth', '<=', 4)
                    ->orderBy('depth', 'asc')->orderBy('order', 'asc')
                    ->get()->toArray();
                foreach ($aEventRank as $eventRank) {
                    if ($eventRank['depth'] === 2 && $eventRank['order'] === 1) { // 결승전에서 우승, 준우승 데이터 가져오기
                        $iWinnerId = $eventRank['winner_entrant_id'];
                        array_push($resultIds, $iWinnerId); // 우승
                        if ($iWinnerId === 0) { // iWinnerId가 0 -> 둘다 몰수패
                            array_push($resultIds, 0); // 준우승
                        } else {
                            $aLose = Arr::where($eventRank['bracket_entries'], function ($item) use ($iWinnerId) {
                                return $item['participant_id'] !== $iWinnerId;
                            });
                            array_push($resultIds, reset($aLose)['participant_id']); // 준우승
                        }
                    } else { // 3, 4위데이터 가져올건데
                        if ($event->match34 === 1) { // 3,4위전이 있으면
                            if ($eventRank['depth'] === 2 && $eventRank['order'] === 2) { // 3,4위전에서 3, 4위 데이터 가져오기
                                $iWinnerId = $eventRank['winner_entrant_id'];
                                array_push($resultIds, $iWinnerId); // 3위
                                if ($iWinnerId === 0) { // iWinnerId가 0 -> 둘다 몰수패
                                    array_push($resultIds, 0); // 4위
                                } else {
                                    $aLose = Arr::where($eventRank['bracket_entries'], function ($item) use ($iWinnerId) {
                                        return $item['participant_id'] !== $iWinnerId;
                                    });
                                    array_push($resultIds, reset($aLose)['participant_id']); // 4위
                                }
                            }
                            break;
                        } else { // 3, 4위전이 없다면
                            if ($eventRank['depth'] === 4) { // 4강전에서 데이터 가져오기
                                $iWinnerId = $eventRank['winner_entrant_id'];
                                if ($iWinnerId === 0) { // iWinnerId가 0 -> 둘다 몰수패
                                    array_push($resultIds, 0);
                                } else {
                                    $aLose = Arr::where($eventRank['bracket_entries'], function ($item) use ($iWinnerId) {
                                        return $item['participant_id'] !== $iWinnerId;
                                    });
                                    array_push($resultIds, reset($aLose)['participant_id']); // 3위 or 4위(공동)
                                }
                            }
                        }
                    }
                }

                $aRankerData = DB::table('participants')
                    ->join('bracket_sets', 'participants.participant_id', '=', 'bracket_sets.participant_id')
                    ->select(
                        'participants.participant_id',
                        'participants.entrant_id',
                        'participants.entrant_name',
                        'participants.entrant_image_url',
                        'participants.dummy',
                        DB::raw('SUM(CASE WHEN bracket_sets.winlose = 1 THEN 1 ELSE 0 END) AS winCount'),
                        DB::raw('SUM(CASE WHEN  bracket_sets.winlose = 0 THEN 1 ELSE 0 END) AS loseCount')
                    )
                    ->whereIn('participants.participant_id', $resultIds)
                    ->groupBy('participants.participant_id', 'participants.entrant_id', 'participants.entrant_name', 'participants.entrant_image_url', 'participants.dummy')
                    ->get()->toArray();

                $aSortedRankerData = collect($resultIds)->map(function ($id) use ($aRankerData) {
                    return collect($aRankerData)->firstWhere('participant_id', $id);
                });

                foreach ($aSortedRankerData as $key => $rankerData) {
                    $aRankData[] = [
                        'participant_id' => $rankerData?->participant_id ?? 0,
                        'entrant_id' => $rankerData?->entrant_id,
                        'entrant_name' => $rankerData?->entrant_name,
                        'entrant_img_url' => $rankerData?->entrant_image_url,
                        'is_dummy' => $rankerData?->dummy,
                        'ranking' => $key + 1,
                        'set_result' => [
                            'w' => $rankerData?->winCount,
                            'l' => $rankerData?->loseCount,
                        ]
                    ];
                }

                Redis::set($redisEventRankerListKey, json_encode($aRankData));
            }

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => $aRankData,
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
}
