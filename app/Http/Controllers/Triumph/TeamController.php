<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use App\Http\Requests\Triumph\StoreTeamsRequest;
use App\Http\Resources\Triumph\CompositeTeamResource;
use App\Jobs\NotificationsJob;
use App\Models\Triumph\Members;
use App\Models\Triumph\TeamMembers;
use App\Models\Triumph\Teams;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Triumph\MembersRepository;
use App\Repositories\Triumph\TeamsRepository;
use App\Repositories\Triumph\TeamMembersRepository;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Repositories\Triumph\UrlsRepository;
use App\Services\ShortenURLService;

class TeamController extends Controller
{
    protected $membersRepository;
    protected $teamsRepository;
    protected $teamMembersRepository;
    protected $notificationTemplateService;
    protected $notificationsService;
    protected $urlsRepository;

    public function __construct(
        MembersRepository $membersRepository,
        TeamsRepository $teamsRepository,
        TeamMembersRepository $teamMembersRepository,
        UrlsRepository $urlsRepository,
    ) {
        $this->membersRepository = $membersRepository;
        $this->teamsRepository = $teamsRepository;
        $this->teamMembersRepository = $teamMembersRepository;
        $this->urlsRepository = $urlsRepository;
    }

    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'page' => 'sometimes|nullable|numeric',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            $aTeam = $this->teamsRepository->getTeamAll([
                'page' => $request->input('page', 1),
                'perPage' => 24
            ]);

            $aReturn = [];
            foreach ($aTeam as $teamData) {
                $aReturn[] = [
                    'team_id' => $teamData->team_id,
                    'team_name' => $teamData->team_name,
                    'team_cover_img_url' => $teamData->team_cover_img_url,
                    'team_member_count' => $teamData->team_member_count,
                    'team_leader_info' => [
                        'member_id' => $teamData->member_id,
                        'member_name' => $teamData->member_name,
                        'member_profile_img_url' => $teamData->member_profile_img_url,
                    ],
                    'game_info' => [
                        'game_id' => $teamData->game_id,
                        'game_name' => $teamData->game_name,
                        'game_logo_img_url' => $teamData->game_logo_img_url,
                        'game_img_url' => $teamData->game_img_url,
                        'game_box_img_url' => $teamData->game_box_img_url,
                        'game_bg_img_url' => $teamData->game_bg_img_url,
                    ]
                ];
            }

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => $aReturn

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

    public function me(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'page' => 'sometimes|nullable|numeric',
                'order_type' => 'nullable|string|in:date,game,team_name,grade',
                'order_by' => 'nullable|string|in:desc,asc',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            $aMember = $request->user();
            if (!$aMember) {
                throw new \Exception(__('messages.Login is required'), 401); // 로그인이 필요합니다.
            }

            $aReturn = ['list' => [], 'cnt' => 0];
            $aReturn['cnt'] = TeamMembers::where('member_id', $aMember->member_id)->count();

            $aValidated['order_type'] = Arr::get($aValidated, 'order_type', 'date');
            $aValidated['order_by'] = Arr::get($aValidated, 'order_by', 'desc');

            if ($aReturn['cnt'] > 0) {
                $aTeam = $this->teamsRepository->getTeamJoined([
                    'member_id' => $aMember->member_id,
                    'page' => $request->input('page', 1),
                    'perPage' => 12,
                    'order_type' => $aValidated['order_type'],
                    'order_by' => $aValidated['order_by'],
                ]);
            } else {
                $aTeam = [];
            }

            foreach ($aTeam as $teamData) {
                $aReturn['list'][] = [
                    'team_id' => $teamData->team_id,
                    'team_name' => $teamData->team_name,
                    'team_cover_img_url' => $teamData->team_cover_img_url,
                    'team_member_count' => $teamData->team_member_count,
                    'team_leader_info' => [
                        'member_id' => $teamData->member_id,
                        'member_name' => $teamData->member_name,
                        'member_profile_img_url' => $teamData->member_profile_img_url,
                    ],
                    'game_info' => [
                        'game_id' => $teamData->game_id,
                        'game_name' => $teamData->game_name,
                        'game_logo_image' => $teamData->game_logo_img_url,
                        'game_image' => $teamData->game_img_url,
                        'game_box_image' => $teamData->game_box_image,
                        'game_bg_image' => $teamData->game_bg_image,
                    ],
                    'team_my_info' => [
                        'status' => $teamData->my_team_status,
                        'grade' => $teamData->my_team_grade,
                    ]
                ];
            }

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => [
                    'team' => $aReturn['list'],
                    'cnt' => $aReturn['cnt'],
                ]

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

    public function store(StoreTeamsRequest $request)
    {
        try {
            $aValidated = $request->validated();
            $aMemberInfo = $request->user();
            if (!$aMemberInfo) {
                throw new \Exception(__('messages.Login is required'), 401); // 로그인이 필요합니다.
            }
            // 제약사항 : 1분안에 2번 만들기 금지
            $redisUserKey = $aValidated['service'] . '.member.' . $aMemberInfo->member_id . '.team.store';
            $aRedisInfo = json_decode(Redis::get($redisUserKey), true);
            if ($aRedisInfo) {
                throw new \Exception(__('messages.Too many requests. Please try again after a while'), 400); // 너무 많은 요청
            }

            $iResult = $this->teamsRepository->insertTeam([
                'member_id' => $aMemberInfo->member_id,
                'game_id' => $aValidated['game_id'] ?? '',
                'name' => htmlspecialchars($aValidated['name']),
                'image_url' => $aValidated['image_url'] ?? '',
            ]);
            if (!$iResult) {
                throw new \Exception(__('messages.Internal Server Error'), 500); // 내부 서버 오류가 발생했습니다.
            }

            $isTeamMember = $this->teamMembersRepository->insertTeamMember([
                'team_id' => $iResult,
                'member_id' => $aMemberInfo->member_id,
                'status' => 1,
                'grade' => 1,
            ]);
            if (!$isTeamMember) {
                throw new \Exception(__('messages.Internal Server Error'), 500); // 내부 서버 오류가 발생했습니다.
            }

            $shortenURLService = new ShortenURLService();
            $isShortenUrl = $shortenURLService->createShortenUrl([
                'original_url' => '/team/' . $iResult,
                'content_name' => 'team',
                'content_id' => $iResult,
                'expired_dt' => null,
            ]);
            if (!$isShortenUrl) {
                throw new \Exception(__('messages.Internal Server Error'), 500); // 내부 서버 오류가 발생했습니다.
            }

            // 제약사항 : 10초안에 2번 만들기 금지
            Redis::setex($redisUserKey, 10, json_encode([
                'team_id' => $iResult
            ])); // 초단위

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => [
                    'team' => [
                        'teamId' => $iResult
                    ]
                ]
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

    public function show(Request $request, $teamId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                // 기획자요청 모든 에러 404 : https://allo.io/c214351cb99caad12adbe526b6a9
                throw new \Exception(__('messages.Not Found'), 404);
            }
            $aValidated = $validator->validated();

            $aMemberInfo = $request->user();
            $aTeam = Teams::with(['games'])
                ->where('team_id', $teamId)->first();
            if (!$aTeam) {
                throw new \Exception(__('messages.Not Found'), 404);
            }

            // 팀
            $aTeamMyData = null;
            if ($aMemberInfo) {
                // orm 변경 : $this->teamMembersRepository->getTeamMemberId
                $aTeamMyData = TeamMembers::with('teams')
                ->whereHas('teams', function ($query) use ($teamId) {
                    $query->where('team_id', $teamId);
                })
                ->where('member_id', $aMemberInfo->member_id)->first();
            }

            if ($aTeamMyData && $aTeamMyData->grade == 1) {
                // orm 변경 : $this->teamMembersRepository->getTeamFullMember
                $aTeamMember = TeamMembers::with('members')
                ->where('team_id', $teamId)->get();
            } elseif (!$aTeamMyData || $aTeamMyData->grade != 1) {
                // orm 변경 : $this->teamMembersRepository->getTeamNormalMember
                $aTeamMember = TeamMembers::with('members')
                ->where('team_id', $teamId)->where('status', 1)->get();
            }

            $shortenURLService = new ShortenURLService();
            $strShortenUrl = $shortenURLService->getShortenUrl([
                'content_name' => 'team',
                'content_id' => $teamId,
            ]);

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => new CompositeTeamResource($aTeam, $aTeamMember, $aTeamMyData, $strShortenUrl),
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

    public function update(Request $request, $teamId, $type)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'name' => 'sometimes|string|max:20|specialChar1',
                'image_url' => 'sometimes|nullable|url',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request') . '(1)', 400);
            }
            $aValidated = $validator->validated();

            $aMemberInfo = $request->user();
            if (!$aMemberInfo) {
                throw new \Exception(__('messages.Login is required'), 401); // 로그인이 필요합니다.
            }

            // 팀멤버 데이터 가져오기 + 팀장 확인
            $isTeamMaster = TeamMembers::with('teams')
            ->whereHas('teams', function ($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })
            ->where('member_id', $aMemberInfo->member_id)->first();
            if ($isTeamMaster->grade != 1) {
                throw new \Exception(__('messages.Unauthorized'), 401); // 권한이 없습니다.
            }

            if ($type == 'name') {
                if (empty($aValidated['name']) || $aValidated['name'] == null) {
                    throw new \Exception(__('messages.Bad Request') . '(2)', 400);
                }
                $bResult = $this->teamsRepository->updateTeamName([
                    'team_id' => $teamId,
                    'name' => $aValidated['name'],
                ]);
            } elseif ($type == 'image') {
                $bResult = $this->teamsRepository->updateTeamImage([
                    'team_id' => $teamId,
                    'image_url' => $aValidated['image_url'],
                ]);
            } else {
                throw new \Exception(__('messages.Bad Request') . '(3)', 400);
            }
            if (!$bResult) {
                throw new \Exception(__('messages.Internal Server Error'), 500); // 내부 서버 오류가 발생했습니다.
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

    public function delete(Request $request, $teamId)
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

            $aMemberInfo = $request->user();
            if (!$aMemberInfo) {
                throw new \Exception(__('messages.Login is required'), 401); // 로그인이 필요합니다.
            }

            // 팀멤버 데이터 가져오기 + 팀장 확인
            $isTeamMaster = TeamMembers::with('teams')
            ->whereHas('teams', function ($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })
            ->where('member_id', $aMemberInfo->member_id)->first();
            if ($isTeamMaster->grade != 1) {
                throw new \Exception(__('messages.Unauthorized'), 401); // 권한이 없습니다.
            }

            // 멤버 검증
            $aTeamMember = $this->teamMembersRepository->getTeamNormalMember([
                'team_id' => $teamId,
            ]);
            if (count($aTeamMember) > 1) {
                throw new \Exception(__('messages.Team members are still present'), 400); // 팀 멤버가 아직 남아 있습니다.
            }

            // 멤버 날리기 팀장포함
            $isDeleteTeamMember = $this->teamMembersRepository->deleteTeamFullMember([
                'team_id' => $teamId,
            ]);
            if (!$isDeleteTeamMember) {
                throw new \Exception(__('messages.Internal Server Error'), 500); // 내부 서버 오류가 발생했습니다.
            }

            // 팀 날리기
            $isDeleteTeam = $this->teamsRepository->deleteTeam([
                'team_id' => $teamId,
            ]);
            if (!$isDeleteTeam) {
                throw new \Exception(__('messages.Internal Server Error'), 500); // 내부 서버 오류가 발생했습니다.
            }

            // 단축 URL 삭제
            $this->urlsRepository->deleteShortenUrl([
                'content_type' => 1, // team = 1
                'content_id' => $teamId,
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

    public function memberList(Request $request, $teamId)
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

            $aMemberInfo = $request->user();
            if (!$aMemberInfo) {
                throw new \Exception(__('messages.Login is required'), 401); // 로그인이 필요합니다.
            }

            // 팀멤버 데이터 가져오기
            $isTeamMember = TeamMembers::with('teams')
            ->whereHas('teams', function ($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })
            ->where('member_id', $aMemberInfo->member_id)->first();
            if (empty($isTeamMember) || $isTeamMember->status != 1) {
                throw new \Exception(__('messages.Unauthorized'), 401); // 권한이 없습니다.
            }
            $isTeamMaster = false;
            if ($isTeamMember->grade == 1) {
                $isTeamMaster = true;
            }

            if (!$isTeamMaster) {
                $aTeamMember = $this->teamMembersRepository->getTeamNormalMember([
                    'team_id' => $teamId,
                ]);
            } else {
                $aTeamMember = $this->teamMembersRepository->getTeamFullMember([
                    'team_id' => $teamId,
                ]);
            }
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => $aTeamMember
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

    public function memberStore(Request $request, $teamId)
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

            $aMemberInfo = $request->user();
            if (!$aMemberInfo) {
                throw new \Exception(__('messages.Login is required'), 401); // 로그인이 필요합니다.
            }

            // 팀 진위여부 확인
            $aTeam = $this->teamsRepository->getTeamId([
                'team_id' => $teamId,
            ]);
            if (!$aTeam) {
                throw new \Exception(__('messages.Bad Request'), 400); // 잘못된 요청입니다.
            }

            // 팀멤버 신청 가능 여부 확인
            $aTeamMember = TeamMembers::with('teams')
            ->whereHas('teams', function ($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })
            ->where('member_id', $aMemberInfo->member_id)->first();
            if ($aTeamMember && $aTeamMember->status != 2) {
                throw new \Exception(__('messages.Already applied'), 400); // 이미 신청되어 있습니다.
            }

            if ($aTeamMember && $aTeamMember->status == 2 && $aTeamMember->grade == 0) {
                // 팀 멤버 기록 있는데 임시+거절 상태일 경우 삭제
                $isResult = $this->teamMembersRepository->deleteTeamMember([
                    'team_id' => $teamId,
                    'member_id' => $aMemberInfo->member_id,
                ]);
                if (!$isResult) {
                    throw new \Exception(__('messages.Internal Server Error'), 500); // 내부 서버 오류가 발생했습니다.
                }
                // 팀 가입 신청 기록 재확인
                $aTeamMember2 = TeamMembers::with('teams')
                ->whereHas('teams', function ($query) use ($teamId) {
                    $query->where('team_id', $teamId);
                })
                ->where('member_id', $aMemberInfo->member_id)->first();
                if ($aTeamMember2) {
                    throw new \Exception(__('messages.Internal Server Error'), 500); // 내부 서버 오류가 발생했습니다.
                }
            }

            $isTeamMember = $this->teamMembersRepository->insertTeamMember([
                'team_id' => $teamId,
                'member_id' => $aMemberInfo->member_id,
                'status' => 0,
                'grade' => 0,
            ]);
            if (!$isTeamMember) {
                throw new \Exception(__('messages.Internal Server Error'), 500); // 내부 서버 오류가 발생했습니다.
            }

            $aTeamLeader = TeamMembers::with('teams')
                ->whereHas('teams', function ($query) use ($teamId) {
                    $query->where('team_id', $teamId);
                })
                ->where('grade', 1)->first();

            // 알림서비스
            NotificationsJob::dispatch(
                'createNotification',
                [
                    'lang' =>  Members::find($aTeamLeader->member_id)->language ?? 'en',
                    'template' => 'join_the_team',
                    'bind' => [
                        '[name]' => $aMemberInfo->name,
                    ]
                ],
                [
                    'member_id' => $aTeamLeader->member_id,
                    'scheduled_time' => Carbon::now()->timestamp,  // 현재 시간 또는 특정 시간
                    'link' => "/team/{$teamId}/applicants",
                    'tag' => "team-{$teamId}",
                    'type' => 'team',
                    'profile_img' => $aMemberInfo->image_url,
                ]
            );
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

    public function memberUpdate(Request $request, $teamId, $memberId, $type)
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

            $aMemberInfo = $request->user();
            if (!$aMemberInfo) {
                throw new \Exception(__('messages.Login is required'), 401); // 로그인이 필요합니다.
            }

            // 팀멤버 데이터 가져오기 + 팀장 확인
            $isTeamMaster = TeamMembers::with('teams')
            ->whereHas('teams', function ($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })
            ->where('member_id', $aMemberInfo->member_id)->first();
            if (!$isTeamMaster || $isTeamMaster->grade != 1) {
                throw new \Exception(__('messages.Unauthorized'), 401); // 권한이 없습니다.
            }

            $status = 0;
            $grade = 0;
            if ($type == 'approval') {
                // 승인일 때
                // 승인된 회원이 30명 이상이면 컷
                $aTeamMemberCount = $this->teamMembersRepository->getTeamMemberCount([
                    'team_id' => $teamId,
                ]);
                if ($aTeamMemberCount->members_count >= 30) {
                    throw new \Exception(__('messages.Too many team members'), 400); // 팀 멤버가 너무 많습니다.
                }
                $status = 1;
                $grade = 2;
            } elseif ($type == 'refuse') {
                // 거절일 때
                $status = 2;
                $grade = 0;
            } else {
                throw new \Exception(__('messages.Bad Request'), 400); // 잘못된 요청입니다.
            }

            $isResult = $this->teamMembersRepository->updateTeamMember([
                'team_id' => $teamId,
                'member_id' => $memberId,
                'status' => $status,
                'grade' => $grade,
            ]);
            if (!$isResult) {
                throw new \Exception(__('messages.Internal Server Error'), 500); // 내부 서버 오류가 발생했습니다.
            }

            // 알림서비스
            if ($type == 'approval') {
                // 알림 : 팀 수락
                NotificationsJob::dispatch(
                    'createNotification',
                    [
                        'lang' => Members::find($memberId)->language ?? 'en',
                        'template' => 'approved_to_join_the_team',
                        'bind' => [
                            '[name]' => $isTeamMaster->teams->name,
                        ]
                    ],
                    [
                        'member_id' => $memberId,
                        'scheduled_time' => Carbon::now()->timestamp,  // 현재 시간 또는 특정 시간
                        'link' => "/team/{$teamId}/members",
                        'tag' => "team-{$teamId}",
                        'type' => 'team',
                        'profile_img' => $aMemberInfo->image_url,
                    ]
                );
            } else {
                // 알림 : 팀 거절
                NotificationsJob::dispatch(
                    'createNotification',
                    [
                        'lang' => Members::find($memberId)->language ?? 'en',
                        'template' => 'refusal_to_join_the_team',
                        'bind' => [
                            '[name]' => $isTeamMaster->teams->name,
                        ]
                    ],
                    [
                        'member_id' => $memberId,
                        'scheduled_time' => Carbon::now()->timestamp,  // 현재 시간 또는 특정 시간
                        'link' => '/team',
                        'tag' => "team-{$teamId}",
                        'type' => 'team',
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
            $aJsonData = [
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    public function memberDelete(Request $request, $teamId, $memberId, $type)
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

            $aMemberInfo = $request->user();
            if (!$aMemberInfo) {
                throw new \Exception(__('messages.Login is required'), 401); // 로그인이 필요합니다.
            }

            // 팀멤버 데이터 가져오기 + 팀장 확인
            $isTeamMember = TeamMembers::with('teams')
            ->whereHas('teams', function ($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })
            ->where('member_id', $aMemberInfo->member_id)->first();

            if ($type == 'kick') {
                // 강퇴일 때
                // 팀장아니면 아웃
                if ($isTeamMember->grade != 1) {
                    throw new \Exception(__('messages.Unauthorized'), 401); // 권한이 없습니다.
                }
                // 본인 삭제 못함
                if ($aMemberInfo->member_id == $memberId) {
                    throw new \Exception(__('messages.Cannot kick oneself'), 400); // 자기 자신을 제명할 수 없습니다.
                }
                $deleteMemberId = $memberId;
            } elseif ($type == 'withdrawal') {
                // 탈퇴일 때
                // 다른사람 삭제 못함
                if ($aMemberInfo->member_id != $memberId) {
                    throw new \Exception(__('messages.Unauthorized'), 401); // 권한이 없습니다.
                }
                // 팀장은 탈퇴안됨
                if ($isTeamMember->grade == 1) {
                    throw new \Exception(__('messages.Team leader cannot leave the team'), 400); // 팀장은 팀을 나갈 수 없습니다.
                }
                $deleteMemberId = $aMemberInfo->member_id;
            } else {
                throw new \Exception(__('messages.Bad Request'), 400); // 잘못된 요청입니다.
            }

            $isResult = $this->teamMembersRepository->deleteTeamMember([
                'team_id' => $teamId,
                'member_id' => $deleteMemberId,
            ]);
            if (!$isResult) {
                throw new \Exception(__('messages.Internal Server Error'), 500); // 내부 서버 오류가 발생했습니다.
            }

            //알림서비스
            if ($type == 'kick') {
                // 알림 : 강퇴
                NotificationsJob::dispatch(
                    'createNotification',
                    [
                        'lang' => Members::find($aMemberInfo->member_id)->language ?? 'en',
                        'template' => 'team_kickoff',
                        'bind' => [
                            '[name]' => $aMemberInfo->name,
                        ]
                    ],
                    [
                        'member_id' => $memberId,
                        'scheduled_time' => Carbon::now()->timestamp,  // 현재 시간 또는 특정 시간
                        'link' => "/team/{$teamId}/applicants",
                        'tag' => "team-{$teamId}",
                        'type' => 'team',
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
