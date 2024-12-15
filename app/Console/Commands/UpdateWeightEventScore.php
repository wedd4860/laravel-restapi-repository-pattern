<?php

namespace App\Console\Commands;

use App\Services\ScoreWeightCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class UpdateWeightEventScore extends Command
{
    // php artisan weight:event-score
    protected $signature = 'weight:event-score';
    protected $description = '최근 이벤트의 가중치를 업데이트하고, 변경 사항을 Redis에 추가합니다.';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $redisUserKey = 'triumph.game.event.recommended.weighted.select';
        $scoreWeightCalculator = new ScoreWeightCalculator();
        $aWeightEvents = $scoreWeightCalculator->updateScores();
        Redis::setex($redisUserKey, 3600, json_encode($aWeightEvents)); // 초단위
        $this->info('가중치 업데이트 하였습니다.');
    }
}
