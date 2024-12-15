<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // 라라벨 11부터는 routes/console.php 이동
        $schedule->command('scout:import "App\Models\Triumph\Events"')
            ->dailyAt('01:10')->timezone('Asia/Seoul')
            ->before(function () {
                Log::info('start : scout App\Models\Triumph\Events');
            })
            ->after(function () {
                Log::info('completed : scout App\Models\Triumph\Events');
            });
        $schedule->command('scout:import "App\Models\Triumph\PlatformGames"')
            ->dailyAt('01:20')->timezone('Asia/Seoul')
            ->before(function () {
                Log::info('start : scout App\Models\Triumph\PlatformGames');
            })
            ->after(function () {
                Log::info('completed : scout App\Models\Triumph\PlatformGames');
            });
        $schedule->command('weight:event-score')
            ->hourly()->timezone('Asia/Seoul')
            ->after(function () {
                Log::info('completed : 가중치를 업데이트 하였습니다.');
            });
        $schedule->command('notifications:reminder-event-start')
            ->everyMinute()->timezone('Asia/Seoul')
            ->after(function () {
                Log::info('completed : 리마인더 알림 체크를 하였습니다.');
            });

        // 구글 시트 업데이트 제거
        // if (config('app.env') === 'production') {
        //     $schedule->command('google:sheet-update')
        //     ->hourly()->timezone('Asia/Seoul')
        //     ->after(function () {
        //         Log::info('completed : 구글 시트를 업데이트 하였습니다.');
        //     });
        // }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
