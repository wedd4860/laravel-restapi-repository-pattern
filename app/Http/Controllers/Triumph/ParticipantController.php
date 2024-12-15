<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use App\Models\Triumph\TeamMembers;
use App\Models\Triumph\Brackets;
use App\Models\Triumph\Participants;
use App\Models\Triumph\PlatformGameMembers;
use App\Models\Triumph\PlatformGames;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Triumph\ParticipantsRepository;
use App\Repositories\Triumph\ParticipantMembersRepository;
use App\Repositories\Triumph\EventsRepository;
use App\Repositories\Triumph\TeamsRepository;
use App\Repositories\Triumph\TeamMembersRepository;

class ParticipantController extends Controller
{
    protected $participantsRepository;
    protected $participantMembersRepository;
    protected $eventsRepository;
    protected $teamsRepository;
    protected $teamMembersRepository;

    public function __construct(
        ParticipantsRepository $participantsRepository,
        ParticipantMembersRepository $participantMembersRepository,
        EventsRepository $eventsRepository,
        TeamsRepository $teamsRepository,
        TeamMembersRepository $teamMembersRepository,
    ) {
        $this->participantsRepository = $participantsRepository;
        $this->participantMembersRepository = $participantMembersRepository;
        $this->eventsRepository = $eventsRepository;
        $this->teamsRepository = $teamsRepository;
        $this->teamMembersRepository = $teamMembersRepository;
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
            $aMemberInfo = $request->user();
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId
            ]);
            if (!$aEvent || $aEvent->status > 3) {
                throw new \Exception(__('messages.Event not found'), 404); //존재하지 않는 이벤트입니다.
            }
            if ($aEvent->member_id != $aMemberInfo->member_id) {
                throw new \Exception(__('messages.Unauthorized'), 403); //권한이 없습니다.
            }

            $aParticipant = [];
            if ($aEvent->team_size == 1) { // 개인전
                $aParticipant = $this->participantsRepository->getParticipant([
                    'event_id' => $eventId,
                ]);
            } elseif ($aEvent->team_size > 1) { // 팀전
                $aParticipantTeam = $this->participantsRepository->getParticipantTeam([
                    'event_id' => $eventId,
                ]);
                $aParticipantTeamMember = null;
                $nowParticipantId = 0;
                $beforeParticipantId = 0;
                foreach ($aParticipantTeam as $participant) {
                    $nowParticipantId = $participant->participant_id;
                    if ($beforeParticipantId != $nowParticipantId) {
                        if ($aParticipant) {
                            $aParticipant[count($aParticipant) - 1]['team_member'] = $aParticipantTeamMember;
                        }
                        $aParticipant[] = [
                            'participant_id' => $participant->participant_id,
                            'event_id' => $participant->event_id,
                            'participant_type' => $participant->participant_type,
                            'entrant_id' => $participant->entrant_id,
                            'entrant_name' => $participant->entrant_name,
                            'checkin_dt' => $participant->checkin_dt,
                            'dummy' => $participant->dummy,
                            'create_member_id' => $participant->create_member_id,
                            'create_dt' => $participant->create_dt,
                            'update_dt' => $participant->update_dt,
                            'entrant_img_url' => $participant->entrant_image_url,
                            'team_member' => [],
                        ];
                        $aParticipantTeamMember = null;
                    }
                    if ($participant->team_member_id === 0 || $participant->team_member_id === null) {
                        $teamMemberId = null;
                    } else {
                        $teamMemberId = $participant->team_member_id;
                    }
                    $aParticipantTeamMember[] = [
                        'member_id' => $teamMemberId,
                        'member_name' => $participant->team_member_name,
                        'member_profile_img_url' => $participant->team_member_profile_img_url,
                    ];
                    if (!next($aParticipantTeam)) {
                        $aParticipant[count($aParticipant) - 1]['team_member'] = $aParticipantTeamMember;
                    }
                    $beforeParticipantId = $nowParticipantId;
                }
            }
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => $aParticipant
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

    public function checkInList(Request $request, $eventId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                //return response()->json(['errors' => $validator->errors()], 400);
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            // 해당 이벤트 정보 가져오기
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId,
            ]);
            if (!$aEvent || $aEvent->status > 3) {
                throw new \Exception(__('messages.Event not found'), 404); //존재하지 않는 이벤트입니다.
            }

            $aParticipant = [];
            if ($aEvent->team_size == 1) { // 개인전
                $aParticipant = $this->participantsRepository->getParticipantCheckin([
                    'event_id' => $eventId,
                ]);
            } elseif ($aEvent->team_size > 1) { // 팀전
                $aParticipantTeam = $this->participantsRepository->getParticipantCheckinTeam([
                    'event_id' => $eventId,
                ]);
                $aParticipantTeamMember = null;
                $nowParticipantId = 0;
                $beforeParticipantId = 0;
                foreach ($aParticipantTeam as $participant) {
                    $nowParticipantId = $participant->participant_id;
                    if ($beforeParticipantId != $nowParticipantId) {
                        if ($aParticipant) {
                            $aParticipant[count($aParticipant) - 1]['team_member'] = $aParticipantTeamMember;
                        }
                        $aParticipant[] = [
                            'participant_id' => $participant->participant_id,
                            'event_id' => $participant->event_id,
                            'participant_type' => $participant->participant_type,
                            'entrant_id' => $participant->entrant_id,
                            'entrant_name' => $participant->entrant_name,
                            'checkin_dt' => $participant->checkin_dt,
                            'dummy' => $participant->dummy,
                            'create_member_id' => $participant->create_member_id,
                            'create_dt' => $participant->create_dt,
                            'update_dt' => $participant->update_dt,
                            'entrant_img_url' => $participant->entrant_image_url,
                            'team_member' => [],
                        ];
                        $aParticipantTeamMember = null;
                    }
                    if ($participant->team_member_name) {
                        if ($participant->team_member_id === 0 || $participant->team_member_id === null) {
                            $teamMemberId = null;
                        } else {
                            $teamMemberId = $participant->team_member_id;
                        }
                        $aParticipantTeamMember[] = [
                            'member_id' => $teamMemberId,
                            'member_name' => $participant->team_member_name,
                            'member_profile_img_url' => $participant->team_member_profile_img_url,
                        ];
                    }
                    if (!next($aParticipantTeam)) {
                        $aParticipant[count($aParticipant) - 1]['team_member'] = $aParticipantTeamMember;
                    }
                    $beforeParticipantId = $nowParticipantId;
                }
            }
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => $aParticipant
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

    public function me(Request $request, $eventId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aMemberInfo = $request->user();
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId
            ]);
            if (!$aEvent || $aEvent->status > 3) {
                throw new \Exception(__('messages.Event not found'), 404); //존재하지 않는 이벤트입니다.
            }

            // 로그인 계정의 해당 이벤트 참여 이력 확인
            $aParticipant = $this->participantsRepository->getParticipantCreateId([
                'event_id' => $eventId,
                'create_member_id' => $aMemberInfo->member_id,
            ]);

            $isParticipantMemberGrade = null;
            if ($aEvent->team_size > 1) {
                // 팀전일 경우 팀 멤버 참여 이력 확인
                $aParticipantMember = $this->participantMembersRepository->getParticipantMemberId([
                    'event_id' => $eventId,
                    'member_id' => $aMemberInfo->member_id,
                ]);

                // 엔트리 멤버 기록 있으면
                if ($aParticipantMember) {
                    // 엔트리 멤버 등록자가 본인일 때 팀장
                    if ($aParticipantMember->create_member_id == $aMemberInfo->member_id) {
                        $isParticipantMemberGrade = 1; // 팀장
                    } else {
                        $isParticipantMemberGrade = 2; // 팀원
                    }
                } else {
                    // 멤버에 없지만 엔트리 등록자가 본인이면 팀장
                    if ($aParticipant && $aParticipant->create_member_id == $aMemberInfo->member_id) {
                        $isParticipantMemberGrade = 1; // 팀장
                    }
                }

                if ($isParticipantMemberGrade == 2) {
                    // 엔트리에 등록된 팀원일 때 엔트리 팀 정보 받아옴
                    $aParticipant = $this->participantsRepository->getParticipantId([
                        'event_id' => $eventId,
                        'participant_id' => $aParticipantMember->participant_id,
                    ]);
                }
            }

            $isGameProfile = false;
            // 해당 게임이 프로필 연동을 지원하는지 확인
            $aPlatformGame = PlatformGames::where('game_id', $aEvent->game_id)
                ->where('game_connect_status', 1)
                ->first();
            if($aPlatformGame){
                // 연동된 프로필에 해당 계정 데이터가 있는지 확인
                $aGameProfile = PlatformGameMembers::where('platform_game_id', $aPlatformGame->platform_game_id)
                    ->where('member_id', $aMemberInfo->member_id)
                    ->first();
                // 프로필에 해당 계정 데이터 없으면 프로필 연동 창 오픈
                if(!$aGameProfile){
                    $isGameProfile = true;
                }
            }

            $aBracket = null;
            // 시작된 이벤트일 때, 마지막으로 참여중인 bracket_id 가져오기
            if ($aParticipant && $aEvent->status > 1) {
                $aBracket = Brackets::select('brackets.*')
                ->join('bracket_entries', 'brackets.bracket_id', '=', 'bracket_entries.bracket_id')
                ->where('bracket_entries.participant_id', $aParticipant->participant_id)
                ->where('brackets.event_id', $eventId)
                ->where('brackets.status', '<', 4)
                ->orderBy('brackets.depth', 'asc')
                ->first();
            }

            $aReturn = [
                'entrant_id' => !$aParticipant ? null : $aParticipant->entrant_id,
                'checkin_dt' => !$aParticipant ? null : $aParticipant->checkin_dt,
                'my_team_grade' => $isParticipantMemberGrade,
                'last_bracket_id' => !$aBracket ? null : $aBracket->bracket_id,
                'is_game_profile_open' => $isGameProfile,
            ];
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

    public function participableMember(Request $request, $eventId, $teamId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aMemberInfo = $request->user();
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId
            ]);
            if (!$aEvent || $aEvent->status > 3) {
                throw new \Exception(__('messages.Event not found'), 404); //존재하지 않는 이벤트입니다.
            }

            $aTeam = $this->teamsRepository->getTeamId([
                'team_id' => $teamId
            ]);
            if (!$aTeam) {
                throw new \Exception(__('messages.Please contact the administrator') . '(2)', 403); //관리자에게 문의 부탁드립니다.
            }

            // 해당 팀의 해당 이벤트 참여 정보
            $aParticipantMember = $this->participantMembersRepository->getParticipantMemberTeamId([
                'event_id' => $eventId,
                'team_id' => $teamId
            ]);

            $aReturn = [];
            foreach ($aParticipantMember as $memberData) {
                $memberApplied = 0;
                if ($memberData->event_id != null) {
                    $memberApplied = 1;
                } elseif ($memberData->leader_event_id != null) {
                    $memberApplied = 1;
                }
                $aReturn[] = [
                    'team_id' => $memberData->team_id,
                    'member_id' => $memberData->member_id,
                    'member_name' => $memberData->member_name,
                    'status' => $memberData->status,
                    'grade' => $memberData->grade,
                    'member_created_at' => $memberData->created_dt,
                    'member_profile_img_url' => $memberData->member_profile_img_url,
                    'member_applied' => $memberApplied,
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

    public function storePerson(Request $request, $eventId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                //return response()->json(['errors' => $validator->errors()], 400);
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            $aMemberInfo = $request->user();
            if (!$aMemberInfo) {
                throw new \Exception(__('messages.Login is required'), 401); // 로그인이 필요합니다.
            }

            // 해당 이벤트 정보 가져오기
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId,
            ]);
            if (!$aEvent || $aEvent->status !== 0) { // 이벤트 상태가 준비가 아닐 경우
                throw new \Exception(__('messages.Please contact the administrator') . '(1)', 403); //관리자에게 문의 부탁드립니다.
            }
            if ($aEvent->entry_start_dt > now()->format('Y-m-d H:i:s') || $aEvent->entry_end_dt < now()->format('Y-m-d H:i:s')) {
                throw new \Exception(__('messages.Not the entry application time'), 403); // 엔트리 신청 가능 시간이 아닙니다.
            }
            if ($aEvent->member_id == $aMemberInfo->member_id) {
                throw new \Exception(__('messages.Organizers cannot participate'), 403); // 주최자는 참여할 수 없습니다.
            }
            if ($aEvent->team_size > 1) { // 팀 경기일 경우
                throw new \Exception(__('messages.Please contact the administrator') . '(2)', 403); //관리자에게 문의 부탁드립니다.
            }

            // 로그인 계정의 해당 이벤트 참여 이력 확인
            $aParticipant = $this->participantsRepository->getParticipantCreateId([
                'event_id' => $eventId,
                'create_member_id' => $aMemberInfo->member_id,
            ]);
            if ($aParticipant) {
                throw new \Exception(__('messages.Already participated'), 401); // 이미 참여했습니다.
            }

            $aSetInsert = $this->participantsRepository->insertParticipantPerson([
                'event_id' => $eventId,
                'entrant_id' => $aMemberInfo->member_id,
                'create_member_id' => $aMemberInfo->member_id,
            ]);
            if (!$aSetInsert || $aSetInsert->RETURN != 'SUC') {
                throw new \Exception(__('messages.Please contact the administrator') . '(3)', 403); //관리자에게 문의 부탁드립니다.
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

    public function storeTeam(Request $request, $eventId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'entrant_id' => 'required|numeric',
                'member_ids' => 'required|array',
                'member_ids.*' => 'nullable|numeric',
                'member_names' => 'required|array',
                'member_names.*' => 'nullable|string|max:20|specialChar2',
            ]);
            if ($validator->fails()) {
                //return response()->json(['errors' => $validator->errors()], 400);
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            $aMemberInfo = $request->user();
            if (!$aMemberInfo) {
                throw new \Exception(__('messages.Login is required'), 401); // 로그인이 필요합니다.
            }

            // 해당 이벤트 정보 가져오기
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId,
            ]);
            if (!$aEvent || $aEvent->status !== 0) { // 이벤트 상태가 준비가 아닐 경우
                throw new \Exception(__('messages.Please contact the administrator') . '(1)', 403); //관리자에게 문의 부탁드립니다.
            }
            if ($aEvent->entry_start_dt > now()->format('Y-m-d H:i:s') || $aEvent->entry_end_dt < now()->format('Y-m-d H:i:s')) {
                throw new \Exception(__('messages.Not the entry application time'), 403); // 엔트리 신청 가능 시간이 아닙니다.
            }
            if ($aEvent->member_id == $aMemberInfo->member_id) {
                throw new \Exception(__('messages.Organizers cannot participate'), 403); // 주최자는 참여할 수 없습니다.
            }
            if ($aEvent->team_size == 1) { // 개인 경기일 경우
                throw new \Exception(__('messages.Please contact the administrator') . '(2)', 403); //관리자에게 문의 부탁드립니다.
            }
            if ($aEvent->team_size != count($aValidated['member_ids']) || $aEvent->team_size != count($aValidated['member_names'])) {
                throw new \Exception(__('messages.Team members count does not match'), 400); // 팀원 수가 맞지 않습니다.
            }

            // 로그인 계정(팀장)의 해당 이벤트 참여 이력 확인
            $aParticipant = $this->participantsRepository->getParticipantCreateId([
                'event_id' => $eventId,
                'create_member_id' => $aMemberInfo->member_id,
            ]);
            if ($aParticipant) {
                throw new \Exception(__('messages.Already participated') . '(1)', 401); // 이미 참여했습니다.
            }

            //로그인 계정(팀장)의 해당 이벤트 팀원으로 참가한 이력 확인
            $aParticipantMember = $this->participantMembersRepository->getParticipantMemberId([
                'event_id' => $eventId,
                'member_id' => $aMemberInfo->member_id,
            ]);
            if ($aParticipantMember) {
                throw new \Exception(__('messages.Already participated') . '(2)', 401); // 이미 참여했습니다.
            }

            // 입력한 팀 정보, 팀에서의 내 정보 가져오기
            $aMyTeamMember = TeamMembers::with('teams')
            ->whereHas('teams', function ($query) use ($aValidated) {
                $query->where('team_id', $aValidated['entrant_id']);
            })
            ->where('member_id', $aMemberInfo->member_id)->first();
            if (!$aMyTeamMember) { // 팀 존재하는지
                throw new \Exception(__('messages.Team information not found'), 404); // 팀 정보를 찾을 수 없습니다.
            }
            if ($aMyTeamMember->grade != 1) { // 팀장 여부 확인
                throw new \Exception(__('messages.Only team leaders can apply to participate'), 403); // 팀장만 참여 신청할 수 있습니다.
            }

            // 입력받은 팀의 해당 이벤트 참여 이력 확인
            $aParticipantTeam = $this->participantsRepository->getParticipantEntrantId([
                'event_id' => $eventId,
                'entrant_id' => $aValidated['entrant_id'],
            ]);
            if ($aParticipantTeam) { // 엔트리에 해당 팀 존재하는지
                throw new \Exception(__('messages.Already participated') . '(3)', 401); // 이미 참여했습니다.
            }

            // 팀원 예외처리
            foreach ($aValidated['member_ids'] as $a => $team_member_id) {
                $aTeamMemberEach = [];
                if ($team_member_id) {
                    if ($aEvent->member_id == $team_member_id) {
                        throw new \Exception(__('messages.Organizers cannot participate'), 403); // 주최자는 참여할 수 없습니다.
                    }

                    // 입력한 팀원들이 해당 팀이 맞는지 체크
                    $aTeamMemberEach = TeamMembers::with('teams')
                    ->whereHas('teams', function ($query) use ($aValidated) {
                        $query->where('team_id', $aValidated['entrant_id']);
                    })
                    ->where('member_id', $team_member_id)->first();
                    if (!$aTeamMemberEach || $aTeamMemberEach->status != 1) {
                        throw new \Exception(__('messages.Invalid team member'), 404); // 유효하지 않은 팀원입니다.
                    }

                    // 해당 이벤트에서 다른 팀원으로 참가한 이력 확인
                    $aParticipantMemberEach = $this->participantMembersRepository->getParticipantMemberId([
                        'event_id' => $eventId,
                        'member_id' => $team_member_id,
                    ]);
                    if ($aParticipantMemberEach) {
                        throw new \Exception(__('messages.Team member who has already participated in the event exists') . ' (' . $aParticipantMemberEach->member_name . ')', 401); // 이미 팀으로 참여한 멤버가 존재합니다.
                    }

                    // 해당 이벤트에서 다른 팀의 팀장으로 참가한 이력 확인
                    $aParticipantEach = $this->participantsRepository->getParticipantCreateId([
                        'event_id' => $eventId,
                        'create_member_id' => $team_member_id,
                    ]);
                    if ($aParticipantEach) {
                        throw new \Exception(__('messages.Team member who has already participated in the event exists') . ' (' . $aParticipantEach->member_name . ')', 401); // 이미 팀으로 참여한 멤버가 존재합니다.
                    }
                } else {
                    if (!$aValidated['member_names'][$a]) {
                        throw new \Exception(__('messages.Re-enter the name of the dummy team member'), 422); // 더미 팀원의 이름을 다시 입력해 주시기 바랍니다.
                    }
                }
            }

            $aSetInsert = $this->participantsRepository->insertParticipanTeam([
                'event_id' => $eventId,
                'entrant_id' => $aValidated['entrant_id'],
                'member_ids' => implode(',', $aValidated['member_ids']),
                'member_names' => implode(',', $aValidated['member_names']),
                'create_member_id' => $aMemberInfo->member_id,
            ]);
            if (!$aSetInsert || $aSetInsert->RETURN != 'SUC') {
                throw new \Exception(__('messages.Please contact the administrator') . '(3)', 403); //관리자에게 문의 부탁드립니다.
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

    public function update(Request $request, $eventId, $type)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'entrant_id' => 'required|numeric',
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

            // 해당 이벤트 정보 가져오기
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId,
            ]);
            if (!$aEvent || $aEvent->status !== 0) { // 이벤트 상태가 준비가 아닐 경우
                throw new \Exception(__('messages.The event is not in the preparation stage'), 403); //이벤트가 준비 단계가 아닙니다.
            }
            if ($aEvent->entry_start_dt > now()->format('Y-m-d H:i:s') || $aEvent->entry_end_dt < now()->format('Y-m-d H:i:s')) {
                throw new \Exception(__('messages.Not the entry application time'), 403); // 엔트리 신청 가능 시간이 아닙니다.
            }

            // 로그인 계정의 해당 이벤트 참여 이력 확인
            $aParticipant = $this->participantsRepository->getParticipantCreateId([
                'event_id' => $eventId,
                'create_member_id' => $aMemberInfo->member_id,
            ]);
            if (!$aParticipant) {
                throw new \Exception(__('messages.You must register first to participate'), 403); // 참가신청을 먼저 해야합니다.
            }
            if ($aParticipant->entrant_id != $aValidated['entrant_id']) {
                throw new \Exception(__('messages.Please contact the administrator') . '(1)', 403); //관리자에게 문의 부탁드립니다.
            }

            if ($type == 'in') { // 체크인
                if ($aParticipant->checkin_dt != null) {
                    throw new \Exception(__('messages.Check-in has already been completed'), 403); // 체크인이 이미 진행된 상태입니다.
                }

                $isParticipant = $this->participantsRepository->updateParticipantsCheckIn([
                    'event_id' => $eventId,
                    'participant_id' => $aParticipant->participant_id,
                ]);
            } elseif ($type == 'out') { // 체크아웃
                if ($aParticipant->checkin_dt == null) {
                    throw new \Exception(__('messages.Check-in has not yet been completed'), 403); //관리자에게 문의 부탁드립니다.
                    // 체크인이 아직 진행되지 않았습니다.
                }

                $isParticipant = $this->participantsRepository->updateParticipantsCheckOut([
                    'event_id' => $eventId,
                    'participant_id' => $aParticipant->participant_id,
                ]);
            } else {
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            if (!$isParticipant) {
                throw new \Exception(__('messages.Please contact the administrator') . '(2)', 403); //관리자에게 문의 부탁드립니다.
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

    public function delete(Request $request, $eventId, $entrantId)
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

            // 해당 이벤트 정보 가져오기
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId,
            ]);
            if (!$aEvent || $aEvent->status !== 0) { // 이벤트 상태가 준비가 아닐 경우
                throw new \Exception(__('messages.Please contact the administrator') . '(1)', 403); //관리자에게 문의 부탁드립니다.
            }

            // 참가자 정보 가져오기
            $aParticipant = $this->participantsRepository->getParticipantEntrantId([
                'event_id' => $eventId,
                'entrant_id' => $entrantId,
            ]);
            if (!$aParticipant) {
                throw new \Exception(__('messages.Please contact the administrator') . '(2)', 403); //관리자에게 문의 부탁드립니다.
            }
            if ($aParticipant->create_member_id != $aMemberInfo->member_id) {
                throw new \Exception(__('messages.Unauthorized'), 403); //권한이 없습니다.
            }
            if ($aParticipant->checkin_dt) {
                throw new \Exception(__('messages.Entry cannot be canceled when check-in is completed'), 403); // 체크인이 완료된 상태에서는 엔트리를 취소할 수 없습니다.
            }

            $participantId = $aParticipant->participant_id;

            $isParticipantDelete = $this->participantsRepository->deleteParticipant([
                'event_id' => $eventId,
                'participant_id' => $participantId,
            ]);
            if (!$isParticipantDelete) {
                throw new \Exception(__('messages.Please contact the administrator') . '(3)', 403); //관리자에게 문의 부탁드립니다.
            }
            if ($isParticipantDelete && $aEvent->team_size > 1) {
                $isParticipantMemberDelete = $this->participantMembersRepository->deleteParticipantMember([
                    'participant_id' => $participantId,
                ]);
                if (!$isParticipantMemberDelete) {
                    throw new \Exception(__('messages.Please contact the administrator') . '(4)', 403); //관리자에게 문의 부탁드립니다.
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

    public function storeDummyPerson(Request $request, $eventId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'entrant_name' => 'required|string|max:20|specialChar2',
            ]);
            if ($validator->fails()) {
                //return response()->json(['errors' => $validator->errors()], 400);
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();
            $aMemberInfo = $request->user();
            // 해당 이벤트 정보 가져오기
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId,
            ]);
            if (!$aEvent) {
                throw new \Exception(__('messages.Please contact the administrator') . '(1)', 403); //관리자에게 문의 부탁드립니다.
            }
            if ($aEvent->member_id != $aMemberInfo->member_id) {
                throw new \Exception(__('messages.Input or modification is only allowed for the organizer or team leader'), 401); // 입력 또는 수정은 주최자 또는 팀장만 할 수 있습니다.
            }
            if ($aEvent->team_size > 1) { // 팀전이면
                throw new \Exception(__('messages.Please contact the administrator') . '(2)', 403); //관리자에게 문의 부탁드립니다.
            }

            // 해당 이벤트에 추가된 더미 갯수 가져오기
            $iParticipantCount = $this->participantsRepository->getParticipantEventIdCount([
                'event_id' => $eventId,
                'dummy' => 1, //1은 더미
            ]);
            if (!$iParticipantCount) {
                throw new \Exception(__('messages.Please contact the administrator') . '(3)', 403); //관리자에게 문의 부탁드립니다.
            }
            if ($iParticipantCount->CNT >= $aEvent->participant_capacity) {
                throw new \Exception(__('messages.Cannot generate more dummy data'), 400); // 더 이상 더미를 생성할수 없습니다.
            }

            $aSetInsert = $this->participantsRepository->insertParticipantDummyPerson([
                'event_id' => $eventId,
                'entrant_name' => $aValidated['entrant_name'],
                'create_member_id' => $aMemberInfo->member_id,
            ]);
            if (!$aSetInsert || $aSetInsert->RETURN != 'SUC') {
                throw new \Exception(__('messages.Please contact the administrator') . '(4)', 403); //관리자에게 문의 부탁드립니다.
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

    public function storeDummyTeam(Request $request, $eventId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'entrant_name' => 'required|string|max:20|specialChar1',
                'member_ids' => 'required|array',
                'member_ids.*' => 'nullable',
                'member_names' => 'required|array',
                'member_names.*' => 'required|string|max:20|specialChar2',
            ]);
            if ($validator->fails()) {
                //return response()->json(['errors' => $validator->errors()], 400);
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            foreach ($aValidated['member_ids'] as $a => $team_member_id) {
                $aValidated['member_ids'][$a] = null;
            }

            $aMemberInfo = $request->user();
            // 해당 이벤트 정보 가져오기
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId,
            ]);
            if (!$aEvent) {
                throw new \Exception(__('messages.Please contact the administrator') . '(1)', 403); //관리자에게 문의 부탁드립니다.
            }
            if ($aEvent->member_id != $aMemberInfo->member_id) {
                throw new \Exception(__('messages.Input or modification is only allowed for the organizer or team leader'), 401); // 입력 또는 수정은 주최자 또는 팀장만 할 수 있습니다.
            }
            if ($aEvent->team_size == 1) { // 개인전이면
                throw new \Exception(__('messages.Please contact the administrator') . '(2)', 403); //관리자에게 문의 부탁드립니다.
            }
            if ($aEvent->team_size != count($aValidated['member_ids']) || $aEvent->team_size != count($aValidated['member_names'])) {
                throw new \Exception(__('messages.Team members count does not match'), 400); // 팀원 수가 맞지 않습니다.
            }

            // 해당 이벤트에 추가된 더미 갯수 가져오기
            $iParticipantCount = $this->participantsRepository->getParticipantEventIdCount([
                'event_id' => $eventId,
                'dummy' => 1, //1은 더미
            ]);
            if (!$iParticipantCount) {
                throw new \Exception(__('messages.Please contact the administrator') . '(3)', 403); //관리자에게 문의 부탁드립니다.
            }
            if ($iParticipantCount->CNT >= $aEvent->participant_capacity) {
                throw new \Exception(__('messages.Cannot generate more dummy data'), 400); //더이상 더미를 생성할수 없습니다.
            }

            $aSetInsert = $this->participantsRepository->insertParticipantDummyTeam([
                'event_id' => $eventId,
                'entrant_name' => $aValidated['entrant_name'],
                'member_ids' => implode(',', $aValidated['member_ids']),
                'member_names' => implode(',', $aValidated['member_names']),
                'create_member_id' => $aMemberInfo->member_id,
            ]);
            if (!$aSetInsert || $aSetInsert->RETURN != 'SUC') {
                throw new \Exception(__('messages.Please contact the administrator') . '(4)', 403); //관리자에게 문의 부탁드립니다.
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

    public function deleteDummy(Request $request, $eventId, $participantId)
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
            // 해당 이벤트 정보 가져오기
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId,
            ]);
            if (!$aEvent) {
                throw new \Exception(__('messages.Please contact the administrator') . '(1)', 403); //관리자에게 문의 부탁드립니다.
            }
            if ($aEvent->member_id != $aMemberInfo->member_id) {
                throw new \Exception(__('messages.Only the organizer can delete'), 401); // 주최자만 삭제할 수 있습니다.
            }

            $isParticipantDummyDelete = $this->participantsRepository->deleteParticipantDummy([
                'event_id' => $eventId,
                'participant_id' => $participantId,
            ]);
            if (!$isParticipantDummyDelete) {
                throw new \Exception(__('messages.Please contact the administrator') . '(2)', 403); //관리자에게 문의 부탁드립니다.
            }
            if ($isParticipantDummyDelete && $aEvent->team_size > 1) {
                $isParticipantDummyMemberDelete = $this->participantMembersRepository->deleteParticipantDummyMember([
                    'participant_id' => $participantId,
                ]);
                if (!$isParticipantDummyMemberDelete) {
                    throw new \Exception(__('messages.Please contact the administrator') . '(3)', 403); //관리자에게 문의 부탁드립니다.
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

    public function sync(Request $request, $eventId)
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
            // 해당 이벤트 정보 가져오기
            $aEvent = $this->eventsRepository->getEventId([
                'event_id' => $eventId,
            ]);
            if (!$aEvent || $aEvent->status > 1 || $aEvent->team_size > 1) {
                throw new \Exception(__('messages.Please contact the administrator') . '(1)', 403); //관리자에게 문의 부탁드립니다.
            }

            $aParticipant = Participants::where('event_id', $aEvent->event_id)
                ->where('create_member_id', $aMemberInfo->member_id)
                ->first();
            if (!$aParticipant) {
                throw new \Exception(__('messages.Please contact the administrator') . '(2)', 403); //관리자에게 문의 부탁드립니다.
            }

            // 해당 게임이 프로필 연동을 지원하는지 확인
            $aPlatformGame = PlatformGames::where('game_id', $aEvent->game_id)
                ->where('game_connect_status', 1)
                ->first();
            $aGameProfile = null;
            if($aPlatformGame){
                // 해당 게임에 해당하는 해당 계정의 프로필 가져오기
                $aGameProfile = PlatformGameMembers::with('platformGameMemberInfo')
                    ->where('platform_game_id', $aPlatformGame->platform_game_id)
                    ->where('member_id', $aMemberInfo->member_id)
                    ->first();
            }
            $aGameProfileInfo = $aGameProfile?->platformGameMemberInfo;
            if($aGameProfileInfo){
                $strEntrantName = $aGameProfileInfo->val0;
            }else{
                $strEntrantName = $aMemberInfo->name;
            }

            $this->participantsRepository->updateParticipantProfile([
                'event_id' => $eventId,
                'participant_id' => $aParticipant->participant_id,
                'entrant_name' => $strEntrantName,
                'entrant_image_url' => $aMemberInfo->image_url,
            ]);

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => [
                    'entrant_name' => $strEntrantName,
                    'entrant_image_url' => $aMemberInfo->image_url
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
}
