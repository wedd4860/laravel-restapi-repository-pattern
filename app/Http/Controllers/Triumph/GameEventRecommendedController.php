<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use App\Http\Resources\Triumph\EventSearchResource;
use App\Models\Triumph\Events;
use App\Services\ScoreWeightCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class GameEventRecommendedController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, int $gameId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'device' => 'required|in:mobile,web,android,ios',
                'type' => 'sometimes|in:date,reg_start,reg_end,indiv,team,max_part',
                // type = date: 대회 개최일, reg_start: 참여자 신청 시작일, reg_end: 참여자 신청 종료일, indiv: 개인전, team: 팀전, max_part: 최대참가수
            ]);
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }
            $aValidated = $validator->validated();
            $strType = Arr::get($aValidated, 'type', 'date');
            $strOrderBy = 'event_start_dt';
            if ($strType == 'date') {
                $strOrderBy = 'event_start_dt';
            } elseif ($strType == 'reg_start') {
                $strOrderBy = 'entry_start_dt';
            } elseif ($strType == 'reg_end') {
                $strOrderBy = 'entry_end_dt';
            } elseif ($strType == 'max_part') {
                $strOrderBy = 'participant_capacity';
            }

            $redisUserKey = $aValidated['service'] . '.games.' . $gameId . '.event.recommended.' . $strType . '.select';
            $aRedisInfo = json_decode(Redis::get($redisUserKey), true);

            if (!$aRedisInfo) {
                $query = Events::with(['platformGames.games', 'platformGames.platforms', 'members'])
                    ->whereHas('platformGames.games', function ($query) use ($gameId) {
                        $query->where('game_id', $gameId);
                    })
                    ->withCount('participants');

                if ($strType == 'indiv') {
                    $query->where('team_size', '1');
                } elseif ($strType == 'team') {
                    $query->where('team_size', '>', '1');
                }
                $aEvents = $query->orderBy($strOrderBy, 'desc')->orderBy('created_dt', 'desc')->limit(4)->get();
                $aRedisInfo = EventSearchResource::collection($aEvents);
                Redis::setex($redisUserKey, 600, json_encode($aRedisInfo)); // 초단위
            }
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => $aRedisInfo,
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
