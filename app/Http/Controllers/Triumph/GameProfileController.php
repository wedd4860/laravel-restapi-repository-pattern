<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use App\Models\Triumph\PlatformGameMemberInfo;
use App\Models\Triumph\PlatformGameMembers;
use App\Models\Triumph\PlatformGames;
use App\Repositories\Triumph\PlatformGameMemberInfoRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GameProfileController extends Controller
{
    protected $platformGameMemberInfoRepository;

    public function __construct(PlatformGameMemberInfoRepository $platformGameMemberInfoRepository)
    {
        $this->platformGameMemberInfoRepository = $platformGameMemberInfoRepository;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }
            $aValidated = $validator->validated();

            $aMemberInfo = $request->user();

            $aPlatformGame = $this->platformGameMemberInfoRepository->getAvailablePlatformGames([
                'member_id' => $aMemberInfo->member_id,
            ]);

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => $aPlatformGame
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
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            $aMemberInfo = $request->user();

            $aGameProfile = PlatformGameMembers::with('platformGameMemberInfo')
                ->with(['platformGames' => function ($query) {
                    $query->with('games');
                }])->where('member_id', $aMemberInfo->member_id)
                ->orderBy('created_dt', 'desc')->get()->toArray();

            $aReturn = [];
            foreach ($aGameProfile as $gameProfile) {
                $aReturn[] = [
                    'platform_game_member_id' => $gameProfile['platform_game_member_id'],
                    'platform_game_id' => $gameProfile['platform_game_id'],
                    'platform_id' => $gameProfile['platform_games']['platform_id'],
                    'game_id' => $gameProfile['platform_games']['game_id'],
                    'game_connect_url' => $gameProfile['platform_games']['game_connect_url'],
                    'platform_provider_id' => $gameProfile['platform_provider_id'],
                    'game_provider_id' => $gameProfile['game_provider_id'],
                    'game_name' => $gameProfile['platform_games']['games']['name'],
                    'game_profile_image_url' => $gameProfile['platform_games']['games']['profile_image_url'],
                    'game_logo_image_url' => $gameProfile['platform_games']['games']['logo_image_url'],
                    'game_nick_name' => $gameProfile['platform_game_member_info']['val0'],
                    'game_data1' => $gameProfile['platform_game_member_info']['val1'],
                    'game_data2' => $gameProfile['platform_game_member_info']['val2'],
                    'game_data3' => $gameProfile['platform_game_member_info']['val3'],
                ];
            }
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => $aReturn,
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

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'platform_game_id' => 'required|numeric',
                'nick_name' => 'required|string|max:255',
                'val1' => 'sometimes|nullable|string|max:255',
                'val2' => 'sometimes|nullable|string|max:255',
                'val3' => 'sometimes|nullable|string|max:255',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request') . '(1)', 400);
            }
            $aValidated = $validator->validated();

            $aMemberInfo = $request->user();

            //platform_game_id 검증
            $aPlatformGame = PlatformGames::where('platform_game_id', $aValidated['platform_game_id'])->where('game_connect_status', 1)->first();
            if (!$aPlatformGame) {
                throw new \Exception(__('messages.Bad Request') . '(2)', 400);
            }

            $iPlatformGameMemberId = $this->platformGameMemberInfoRepository->insertPlatformGameMemberInfo([
                'platform_game_id' => $aValidated['platform_game_id'],
                'member_id' => $aMemberInfo->member_id,
                'nick_name' => $aValidated['nick_name'],
                'val1' => $aValidated['val1'] ? $aValidated['val1'] : null,
                'val2' => $aValidated['val2'] ? $aValidated['val2'] : null,
                'val3' => $aValidated['val3'] ? $aValidated['val3'] : null,
            ]);
            if (!$iPlatformGameMemberId) {
                throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
            }

            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => [
                    'platform_game_member_id' => $iPlatformGameMemberId
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

    public function update(Request $request, $platformGameMemberId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'nick_name' => 'required|string|max:255',
                'val1' => 'sometimes|nullable|string|max:255',
                'val2' => 'sometimes|nullable|string|max:255',
                'val3' => 'sometimes|nullable|string|max:255',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request') . '(1)', 400);
            }
            $aValidated = $validator->validated();

            $aMemberInfo = $request->user();

            //platform_game_id 검증
            $aPlatformGameMember = PlatformGameMemberInfo::whereHas('platformGameMember', function ($query) use ($aMemberInfo) {
                $query->where('member_id', $aMemberInfo->member_id);
            })->where('platform_game_member_id', $platformGameMemberId)->first();
            if (!$aPlatformGameMember) {
                throw new \Exception(__('messages.Bad Request') . '(2)', 400);
            }

            $aUpdatePlatformGameMemberInfo = $this->platformGameMemberInfoRepository->updatePlatformGameMemberInfo([
                'platform_game_member_id' => $platformGameMemberId,
                'nick_name' => $aValidated['nick_name'],
                'val1' => $aValidated['val1'] ? $aValidated['val1'] : null,
                'val2' => $aValidated['val2'] ? $aValidated['val2'] : null,
                'val3' => $aValidated['val3'] ? $aValidated['val3'] : null,
            ]);
            if (!$aUpdatePlatformGameMemberInfo) {
                throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
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

    public function destroy(Request $request, $platformGameMemberId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request') . '(1)', 400);
            }
            $aValidated = $validator->validated();

            $aMemberInfo = $request->user();

            //platform_game_id 검증
            $aPlatformGameMember = PlatformGameMemberInfo::whereHas('platformGameMember', function ($query) use ($aMemberInfo) {
                $query->where('member_id', $aMemberInfo->member_id);
            })->where('platform_game_member_id', $platformGameMemberId)->first();
            if (!$aPlatformGameMember) {
                throw new \Exception(__('messages.Bad Request') . '(2)', 400);
            }

            $isDeletePlatformGameMemberInfo = $this->platformGameMemberInfoRepository->deletePlatformGameMemberInfo([
                'platform_game_member_id' => $platformGameMemberId,
            ]);
            if (!$isDeletePlatformGameMemberInfo) {
                throw new \Exception(__('messages.Please contact the administrator'), 403); //관리자에게 문의 부탁드립니다.
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
}
