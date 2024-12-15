<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use App\Library\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Triumph\BracketsRepository;
use App\Repositories\Triumph\EventsRepository;
use App\Repositories\Triumph\BracketEntriesRepository;
use App\Repositories\Triumph\ChatsRepository;

class BracketController extends Controller
{
    protected $bracketsRepository;
    protected $eventsRepository;
    protected $bracketEntriesRepository;
    protected $chatsRepository;

    public function __construct(BracketsRepository $bracketsRepository, EventsRepository $eventsRepository, BracketEntriesRepository $bracketEntriesRepository, ChatsRepository $chatsRepository)
    {
        $this->bracketsRepository = $bracketsRepository;
        $this->eventsRepository = $eventsRepository;
        $this->bracketEntriesRepository = $bracketEntriesRepository;
        $this->chatsRepository = $chatsRepository;
    }

    public function index(Request $request, $eventId)
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

            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId
            ]);
            if (!$aEvent || $aEvent->status > 3) {
                throw new \Exception(__('messages.Event not found'), 404); //존재하지 않는 이벤트입니다.
            }
            $aBracket = $this->bracketsRepository->getBracketTournamentEventId([
                'event_id' => $eventId
            ]);
            if (!$aBracket) {
                throw new \Exception(__('messages.Bracket information not found'), 404); //브라켓 정보를 찾을 수 없습니다.
            }
            $tmpBracket = [];
            foreach ($aBracket as $item) {
                $ibracketId = $item->bracket_id;
                if (!isset($tmpBracket[$ibracketId])) {
                    $tmpBracket[$ibracketId] = [
                        'bracket_id' => $ibracketId,
                        'event_id' => $item->event_id,
                        'depth' => $item->depth,
                        'order' => $item->order,
                        'match_point' => $item->match_point,
                        'winner_entrant_id' => $item->winner_entrant_id,
                        'status' => $item->status,
                        'entries' => [],
                    ];
                }
                $tmpBracket[$ibracketId]['entries'][] = [
                    'score' => $item->score ?? 0,
                    'entry_status' => $item->entry_status,
                    'participant_id' => $item->participant_id,
                    'participant_name' => $item->entrant_name,
                    'participant_type' => $item->participant_type,
                    'is_dummy' => $item->dummy ? 'true' : 'false',
                    'participant_profile_img_url' => $item->image_url,
                ];
            }
            foreach ($tmpBracket as $key => $item) {
                if (count($item['entries']) < 2) {
                    $tmpBracket[$key]['entries'][] = [
                        'score' => 0,
                        'entry_status' => null,
                        'participant_id' => null,
                        'participant_name' => null,
                        'participant_type' => null,
                        'is_dummy' => 'false',
                        'participant_profile_img_url' => null,
                    ];
                }
            }
            $tmpBracket = array_values($tmpBracket);
            // 리턴값 성공 실패 따져야함, 리턴값이 정상적인 값인지 따져야함
            //getBracketTournamentEventId
            $aJsonData = [
                "status" => "success",
                "code" => 200,
                "message" => __('messages.Request successful'),
                "data" => [
                    'event' => [
                        'event_id' => $aEvent->event_id,
                        'team_size' => $aEvent->team_size,
                        'participant_capacity' => $aEvent->participant_capacity,
                        'match34' => $aEvent->match34,
                        'format' => $aEvent->format,
                    ],
                    'brackets' => $tmpBracket
                ]
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            $aJsonData = [
                "status" => "error",
                "code" => $e->getCode(),
                "message" => $e->getMessage(),
                "data" => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    public function participantsList(Request $request, $eventId, $bracketId)
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

            $redisParticipantsKey = $aValidated['service'] . '.event.' . $eventId . '.bracket.' . $bracketId . '.participant';
            $aParticipantResult = json_decode(Redis::get($redisParticipantsKey), true);

            if (!$aParticipantResult) {
                $aBracket = $this->bracketsRepository->getBracketData([
                    'event_id' => $eventId,
                    'bracket_id' => $bracketId,
                ]);
                if (!$aBracket) {
                    throw new \Exception(__('messages.Bracket information not found'), 404); //브라켓 정보를 찾을 수 없습니다.
                }

                $aBracketInfo = [];
                $aBracketEntryInfo = [];
                // 라라벨 그룹 정렬
                $aBracket = collect($aBracket)->groupBy('participant_id');
                foreach ($aBracket as $bracketItem) {
                    $tmpBracketParticipantInfo = [];

                    if ($bracketItem[0]->participant_type === 1) {
                        // 팀 멤버 추가
                        foreach ($bracketItem as $member) {
                            if($member->entrant_id != null){
                                $tmpBracketParticipantInfo[] = [
                                    'entrant_id' => $member->entrant_id,
                                    'member_id' => $member->part_member_id,
                                    'name' => $member->part_member_name,
                                    'user_profile_img_url' => $member->part_member_image_url,
                                    'is_dummy' => $member->is_dummy_member,
                                    'grade' => $member->part_member_id == $bracketItem[0]->leader_member_id ? 'leader' : 'member',
                                ];
                            }
                        }
                    }

                    // 브라켓 데이터 추가
                    $aBracketInfo = [
                        'depth' => $bracketItem[0]->depth,
                        'order' => $bracketItem[0]->order,
                        'match_point' => $bracketItem[0]->match_point,
                        'operators' => [
                            'member_id' => $bracketItem[0]->operators_member_id,
                            'name' => $bracketItem[0]->operators_member_name,
                            'user_profile_img_url' => $bracketItem[0]->operators_member_image_url,
                        ]
                    ];

                    // 팀 데이터 추가
                    $aBracketEntryInfo[] = [
                        'type' => $bracketItem[0]->participant_type,
                        'participant_id' => $bracketItem[0]->participant_id,
                        'entrant_id' => $bracketItem[0]->entrant_id,
                        'name' => $bracketItem[0]->entrant_name,
                        'entry_profile_img_url' => $bracketItem[0]->entrant_image_url,
                        'is_dummy' => $bracketItem[0]->is_dummy_team,
                        'entrant_member' => [ // 팀 리더 데이터 추가
                            'entrant_id' => $bracketItem[0]->entrant_id,
                            'member_id' => $bracketItem[0]->is_dummy_team === 1 ? null : $bracketItem[0]->leader_member_id,
                            'user_profile_img_url' => $bracketItem[0]->is_dummy_team === 1 ? null : $bracketItem[0]->leader_member_image_url,
                            'name' => $bracketItem[0]->is_dummy_team === 1 ? null : $bracketItem[0]->leader_member_name,
                            'is_dummy' => $bracketItem[0]->is_dummy_team,
                            'grade' => $bracketItem[0]->participant_type === 0 ? 'individual' : 'leader',
                        ],
                        'participants' => $tmpBracketParticipantInfo,
                    ];
                }
                $aParticipantResult = [
                    'bracket_info' => $aBracketInfo,
                    'entries' => $aBracketEntryInfo
                ];

                Redis::setex($redisParticipantsKey, 60, json_encode($aParticipantResult));
            }

            $aJsonData = [
                "status" => "success",
                "code" => 200,
                "message" => __('messages.Request successful'),
                "data" => $aParticipantResult
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            $aJsonData = [
                "status" => "error",
                "code" => $e->getCode(),
                "message" => $e->getMessage(),
                "data" => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    public function bracketStatus(Request $request, $eventId, $bracketId)
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

            $aBracket = $this->bracketsRepository->getBracketStatusData([
                'event_id' => $eventId,
                'bracket_id' => $bracketId,
            ]);
            if (!$aBracket) {
                throw new \Exception(__('messages.Bracket information not found'), 404); //브라켓 정보를 찾을 수 없습니다.
            }
            if(count($aBracket) < 2){
                throw new \Exception(__('messages.Every entries have not been determined'), 400); //참가자가 결정되지 않았습니다.
            }

            $aBracketStatus = [];
            $aBracketEntryStatus = [];
            foreach ($aBracket as $bracketItem) {
                $aBracketStatus = [
                    'bracket_id' => $bracketItem->bracket_id,
                    'winner_entrant_id' => $bracketItem->winner_entrant_id,
                    'bracket_start_dt' => $bracketItem->bracket_start_dt,
                    'bracket_end_dt' => $bracketItem->bracket_end_dt,
                    'bracket_status' => $bracketItem->bracket_status,
                ];

                $aBracketEntryStatus[] = [
                    'participant_id' => $bracketItem->participant_id,
                    'entry_score' => $bracketItem->entry_score,
                    'entry_status' => $bracketItem->entry_status,
                ];
            }

            $aJsonData = [
                "status" => "success",
                "code" => 200,
                "message" => __('messages.Request successful'),
                "data" => [
                    'bracket_info' => $aBracketStatus,
                    'entries' => $aBracketEntryStatus
                ]
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            $aJsonData = [
                "status" => "error",
                "code" => $e->getCode(),
                "message" => $e->getMessage(),
                "data" => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    public function store(Request $request, $eventId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'match_point' => 'required|array',
                'match_point.*' => 'required|numeric',
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
            if ($aMemberInfo->member_id != $aEvent->member_id) {
                throw new \Exception(__('messages.Only the organizer can make modifications'), 403); // 주최자만 수정할 수 있습니다.
            }
            $aBracket = $this->bracketsRepository->getBracketEventId([
                'event_id' => $eventId
            ]);
            if ($aBracket) {
                throw new \Exception(__('messages.The bracket has already been created.'), 403);
            }
            $aMetchAll = $this->getMatch($aEvent->participant_capacity);
            if (count($aMetchAll) < 1 || count($aValidated['match_point']) != count($aMetchAll)) {
                //잘못된 라운드 정보
                throw new \Exception(__('messages.The match points and round information do not match'), 403); //매치포인트의 수와 라운드의 정보가 일치하지 않습니다.
            }
            $aBracket = $this->bracketsRepository->insertBracketSingle([
                'event_id' => $eventId,
                'depth' => $aEvent->participant_capacity,
                'match34' => $aEvent->match34,
                'match_point' => implode(',', $aValidated['match_point']),
            ]);
            if ($aBracket->RETURN != 'SUC') {
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
            $aJsonData = [
                "status" => "error",
                "code" => $e->getCode(),
                "message" => $e->getMessage(),
                "data" => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    public function show(Request $request, $eventId, $bracketId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'chat_id' => 'sometimes|nullable|numeric',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            $aChat = $this->chatsRepository->getChatsBracketHistory([
                'bracket_id' => $bracketId,
                'chat_id' => $aValidated['chat_id'],
            ]);

            $aResult = [];
            if ($aChat) {
                $aChatMember = $this->bracketEntriesRepository->getBracketEntryMemberData([
                    'bracket_id' => $bracketId,
                ]);

                $aChatMemberId = array_unique(array_column($aChat, 'member_id'));

                $aChatMemberData = [];
                foreach ($aChatMemberId as $member) {
                    $iMemberEntrantId = null;
                    $strMemberGrade = null;
                    foreach ($aChatMember as $chatMember) {
                        if ($chatMember->member_id == $member || $chatMember->create_member_id == $member) {
                            $iMemberEntrantId = $chatMember->entrant_id;
                        }
                        $strMemberGrade = 'member';
                        if ($iMemberEntrantId === null) {
                            $strMemberGrade = 'manager';
                        } else if ($chatMember->create_member_id == $member) {
                            if ($chatMember->participant_type === 1) {
                                $strMemberGrade = 'leader';
                            } else {
                                $strMemberGrade = 'individual';
                            }
                            break;
                        }
                    }
                    $aChatMemberData[$member] = [
                        'entrant_id' => $iMemberEntrantId,
                        'grade' => $strMemberGrade,
                    ];
                }

                foreach ($aChat as $chat) {
                    $tmpType = 'member';
                    if ($chat->type === 1) {
                        $tmpType = 'system';
                    } else if ($chat->type === 2) {
                        $tmpType = 'manager';
                    } else if ($chat->type === 3) {
                        $tmpType = 'admin';
                    }
                    $aResult[] = [
                        "message" => [
                            [
                                "chat_id" => $chat->chat_id,
                                "type" => $tmpType,
                                "message" => $chat->message,
                                "timestamp" => Util::getISO8601($chat->created_dt),
                            ]
                        ],
                        "from" => [
                            "member_id" => $chat->member_id,
                            "member_img_url" => $chat->image_url,
                            "member_name" => $chat->name,
                            "entrant_id" => $aChatMemberData[$chat->member_id]['entrant_id'],
                            "grade" => $aChatMemberData[$chat->member_id]['grade'],
                        ],
                    ];
                }
            }

            $aJsonData = [
                "status" => "success",
                "code" => 200,
                "message" => __('messages.Request successful'),
                "data" => $aResult
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            $aJsonData = [
                "status" => "error",
                "code" => $e->getCode(),
                "message" => $e->getMessage(),
                "data" => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    public function edit($id)
    {
        return response()->json([
            "status" => "error",
            "code" => 404,
            "message" => __('messages.Bad Request'),
            "data" => []
        ], 404);
    }

    public function matchPointUpdate(Request $request, $eventId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'match_point' => 'required|array',
                'match_point.*' => 'required|numeric',
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
            $aEventEntry = $this->eventsRepository->getEventEntryIdAll([
                'event_id' => $eventId
            ]);
            if (count($aEventEntry) > 0) {
                throw new \Exception(__('messages.Cannot modify the entry in the created state'), 403); //엔트리가 생성된 상태에서는 수정이 불가능합니다.
            }
            $aMemberInfo = $request->user();
            if ($aMemberInfo->member_id != $aEvent->member_id) {
                throw new \Exception(__('messages.Only the organizer can make modifications'), 403); // 주최자만 수정할 수 있습니다.
            }
            $aMetchAll = $this->getMatch($aEvent->participant_capacity);
            if (count($aMetchAll) < 1 || count($aValidated['match_point']) != count($aMetchAll)) {
                //잘못된 라운드 정보
                throw new \Exception(__('messages.The match points and round information do not match'), 403); //매치포인트의 수와 라운드의 정보가 일치하지 않습니다.
            }
            //insert시 delete 진행된뒤 insert
            $aBracket = $this->bracketsRepository->insertBracketSingle([
                'event_id' => $eventId,
                'depth' => $aEvent->participant_capacity,
                'match34' => $aEvent->match34,
                'match_point' => implode(',', $aValidated['match_point']),
            ]);
            if ($aBracket->RETURN != 'SUC') {
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

    public function update(Request $request, $eventId, $bracketId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'winner_entrant_id' => 'sometimes|numeric',
                'status' => 'sometimes|numeric|in:3,4',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();
            $aBracket = $this->bracketsRepository->getBracketBracketId([
                'bracket_id' => $bracketId
            ]);
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId
            ]);
            if (!$aEvent || $aEvent->status > 3) {
                throw new \Exception(__('messages.Event not found'), 404); //존재하지 않는 이벤트입니다.
            }
            $aEntry = $this->bracketEntriesRepository->getBracketEntryBracketId([
                'bracket_id' => $bracketId
            ]);
            if (!$aBracket || !$aEntry) {
                throw new \Exception(__('messages.Event, bracket, or entry information is invalid'), 422); // 이벤트 또는 브라켓 또는 엔트리 정보가 잘못되었습니다.
            }
            //todo 이벤트 종료 체크해야함
            $aMemberInfo = $request->user();
            if ($aMemberInfo->member_id != $aEvent->member_id) {
                throw new \Exception(__('messages.Only the organizer can make modifications'), 403); // 주최자만 수정할 수 있습니다.
            }
            if ($aBracket->status == 4) {
                throw new \Exception(__('messages.Bracket has ended'), 403); //브라켓이 종료되었습니다.
            } else if ($aBracket->status < 2) {
                throw new \Exception(__('messages.Cannot modify the bracket while it is in preparation or ongoing.'), 403); //브라켓이 준비 또는 진행 중일 때에는 수정할 수 없습니다.
            } else if ($aValidated['status'] < $aBracket->status) {
                throw new \Exception(__('messages.Cannot revert to the previous state'), 403); //이전 상태로는 변경할 수 없습니다.
            }
            // 전체 컬럼값 재설정
            $aValidated['status'] = Arr::get($aValidated, 'status', $aBracket->status);
            $aValidated['winner_entrant_id'] = Arr::get($aValidated, 'winner_entrant_id', $aBracket->winner_entrant_id);
            $isEntryParticipant = false;
            foreach ($aEntry as $item) {
                if ($item->participant_id == $aValidated['winner_entrant_id']) {
                    $isEntryParticipant = true;
                }
            }
            if (!$isEntryParticipant) {
                throw new \Exception(__('messages.Participant information is incorrect'), 422); //참여자 정보가 잘못되었습니다.
            }
            $aParamBracket = [
                'bracket_id' =>  $aBracket->bracket_id,
                'event_id' => $aBracket->event_id,
                'depth' => $aBracket->depth,
                'order' => $aBracket->order,
                'match_point' => $aBracket->match_point,
                'winner_entrant_id' => $aValidated['winner_entrant_id'],
                'status' => $aValidated['status'],
            ];
            $aUpdateBracket = $this->bracketsRepository->updateBracket($aParamBracket);
            if ($aUpdateBracket->RETURN != 'SUC') {
                throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
            }
            if ($aValidated['status'] == 4) {
                if ($aBracket->depth > 2) {
                    //윗단계 가져오기
                    $aUpBracket = $this->bracketsRepository->getBracketDepthOrder([
                        'event_id' => $aBracket->event_id,
                        'depth' => $aBracket->depth / 2,
                        'order' => ceil($aBracket->order / 2)
                    ]);
                    if (!$aUpBracket) {
                        throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
                    }
                    $aInsertEntryWin = $this->bracketEntriesRepository->insertBracketEntry([
                        'participant_id' => $aValidated['winner_entrant_id'],
                        'bracket_id' => $aUpBracket->bracket_id,
                        'score' => 0,
                        'status' => 0,
                    ]);
                    if ($aInsertEntryWin->RETURN != 'SUC') {
                        throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
                    }
                    if ($aBracket->depth == 4 && $aEvent->match34 == 1) {
                        //3~4위전 업데이트
                        $loseEntryId = '';
                        foreach ($aEntry as $item) {
                            if ($item->participant_id != $aValidated['winner_entrant_id']) {
                                $loseEntryId = $item->participant_id;
                                break;
                            }
                        }

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
                    if ($aBracket->order == 1) {
                        //todo 모든 브라켓 상태가 종료일때 종료로 가야함

                        $aUpdateEvent = $this->eventsRepository->updateEventStatus([
                            'event_id' => $eventId,
                            'status' => 3, //이벤트 종료
                        ]);
                        if (!$aUpdateEvent) {
                            throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
                        }
                    }
                }
            } else {
                //하단 제거 브로드캐스팅 시작
                // if ($aBracket->order == 1) {
                //     $aUpdateEvent = $this->eventsRepository->updateEventStatus([
                //         'event_id' => $eventId,
                //         'status' => 3, //이벤트 종료
                //     ]);
                //     if (!$aUpdateEvent) {
                //         throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
                //     }
                // }
            }
            $aJsonData = [
                "status" => "success",
                "code" => 200,
                "message" => __('messages.Request successful'),
                "data" => [
                    'event_id' => $eventId
                ]
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            $aJsonData = [
                "status" => "error",
                "code" => $e->getCode(),
                "message" => $e->getMessage(),
                "data" => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
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
}
