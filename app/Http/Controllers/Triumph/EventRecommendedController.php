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

class EventRecommendedController extends Controller
{
    public function index(Request $request, int $gameId = null)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'type' => 'sometimes|in:redis',
            ]);
            if ($validator->fails()) {
                $errors = $validator->errors();
                throw new \Exception(__('messages.Bad Request'), 400);
            }
            $aValidated = $validator->validated();

            $redisUserKey = $aValidated['service'] . '.game.event.recommended.weighted.select';
            $aRedisInfo = json_decode(Redis::get($redisUserKey), true);
            if (Arr::get($aValidated, 'type')) {
                return response()->json([
                    'status' => 'success',
                    'code' => 200,
                    'message' => __('messages.Request successful'),
                    'data' => $aRedisInfo,
                ], 200);
            }

            if (!$aRedisInfo) {
                // 하단은 스케줄이 돌아야 하는부분이나 없을때를 대비해서 로직 추가
                $scoreWeightCalculator = new ScoreWeightCalculator();
                $aWeightEvents = $scoreWeightCalculator->updateScores();
                Redis::setex($redisUserKey, 3600, json_encode($aWeightEvents)); // 초단위
                $aRedisInfo = $aWeightEvents;
            }
            if ($gameId > 0) {
                // 선택된 게임 가중치 추천목록
                $aIdxWeightedEvent = collect(Arr::get($aRedisInfo, $gameId))->pluck('event_id')->unique()->take(4)->all();
            } else {
                // 전체 게임 가중치 추천목록
                $gameId = 'total';
                $tmpEvent = [];
                foreach ($aRedisInfo as $weightEvent) {
                    foreach ($weightEvent as $item) {
                        $tmpEvent[] = $item;
                    }
                }
                usort($tmpEvent, function ($item1, $item2) {
                    return $item2['score'] <=> $item1['score'];
                });
                $aIdxWeightedEvent = collect($tmpEvent)->pluck('event_id')->unique()->take(6)->all();
            }

            // 이벤트
            $redisUserKey = $aValidated['service'] . '.games.' . $gameId . '.event.recommended.weighted.select';
            $aRedisInfo = json_decode(Redis::get($redisUserKey), true);

            if (!$aRedisInfo) {
                $query = Events::with(['games', 'members'])
                    ->whereHas('games', function ($query) use ($gameId) {
                        if ($gameId != 'total') {
                            $query->where('game_id', $gameId);
                        }
                    })
                    ->withCount(['participants' => function ($query) {
                        $query->whereNotNull('checkin_dt');
                    }])
                    ->whereIn('event_id', $aIdxWeightedEvent);
                if (count($aIdxWeightedEvent) > 0) {
                    $query->orderByRaw('FIELD(event_id, ' . implode(',', $aIdxWeightedEvent) . ')');
                }
                $aEvents = $query->get();
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
