<?php

namespace App\Services;

use App\Models\Triumph\Events;
use App\Models\Triumph\Games;
use App\Models\Triumph\PlatformGames;
use Illuminate\Support\Carbon;

class ScoreWeightCalculator
{
    protected $games = [
        0 => 0.5, //디폴트
        1 => 0.7, //MicroVolts Recharged
        2 => 0.5, //Other
        3 => 0.7, //Gunz
        4 => 0.6, //DKonline
    ];

    protected $timeScore = [
        '1 hour' => 50,
        '3 hours' => 30,
        '1 day' => 20,
        '3 days' => 15,
        '7 days' => 10,
    ];

    public function calculateScore($events)
    {
        if ($events->count() == 0) {
            return false;
        }

        $aScore = [];
        foreach ($events as $event) {
            // 최대 참가자가 0명인건 잘못된 데이터
            if ($event->participant_capacity == 0) {
                continue;
            }
            $iPm = (float) $event->participant_capacity; // Pm: 최대 참가자 수
            $iPc = (float) $event->participants_count; // 현재 체크인 참가자
            $iEt = (float) 0; // 엔트리 시간 점수 : 엔트리 마감까지 남은 시간에 따른 점수
            $iWp = (float) 1; //이벤트 가중치 : 기본 1, 추후 중요도에 따라 조절
            $iView = (float) 0; // [미구현] view : 조회수
            $iWi = (float) 0.1; // [미구현] wi 관심도 가중치 : 기본 0.1 , 추후 중요도에 따라 조절
            $iWe = (float) 1; // [미구현] we: 대회 밀도 가중치 : 기본 1

            foreach ($this->timeScore as $strTime => $iTimeScore) {
                if ($this->getIsTimeScore($event->entry_end_dt, $strTime)) {
                    $iEt = $iTimeScore;
                    break;
                }
            }

            // 시간가중치 기본값은 5
            if ($iEt == 0) {
                $iEt = 5;
            }
            $aScore[] = [
                'event_id' => $event->event_id,
                'game_id' => $event->games->game_id,
                'score' => (($iPc / $iPm) * $iEt * $iWp) + ($iView * $iWi) + (($iPm / $iEt) * $iWe),
                'score_string' => "(({$iPc} / {$iPm}) * {$iEt} * {$iWp}) + ({$iView} * {$iWi}) + (({$iPm} / {$iEt}) * {$iWe})",
            ];
        }
        // 역순정렬 추가
        usort($aScore, function ($item1, $item2) {
            return $item2['score'] <=> $item1['score'];
        });
        return $aScore;
    }

    protected function getIsTimeScore($endTime, $time)
    {
        $targetTime = strtotime("-{$time}", strtotime($endTime));
        return time() >= $targetTime;
    }

    public function updateScores()
    {
        $aGames = Games::all(['game_id'])->pluck('game_id')->unique();
        $aScore = [];
        foreach ($aGames as $gameId) {
            $aEvents = Events::with(['games'])
                ->whereHas('games', function ($query) use ($gameId) {
                    $query->where('game_id', $gameId);
                })
                ->withCount(['participants'])
                ->where('entry_end_dt', '>=', Carbon::now()->subWeek())
                ->where('status', '0')
                ->orderBy('entry_end_dt', 'asc')
                ->limit(6)->get();
            if ($aEvents->count() > 0) {
                $aScore[$gameId] = $this->calculateScore($aEvents);
            }
        }
        return $aScore;
    }
}
