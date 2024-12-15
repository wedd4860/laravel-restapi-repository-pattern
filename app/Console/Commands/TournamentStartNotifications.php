<?php

namespace App\Console\Commands;

use App\Jobs\NotificationsJob;
use App\Models\Triumph\Events;
use App\Models\Triumph\Members;
use App\Models\Triumph\Participants;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class TournamentStartNotifications extends Command
{
    // php artisan notifications:reminder-event-start
    protected $signature = 'notifications:reminder-event-start';
    protected $description = '1분마다 한번식 돌면서 30분 또는 1시간 이내면 리마인더 알림을 보냅니다.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $nowDate = Carbon::now();
        $nowRangeDate = $nowDate->copy()->format('Y-m-d H:i:00');
        $laterRangeDate = $nowDate->copy()->addHour()->addMinutes(10)->format('Y-m-d H:i:00'); //1시간 10분뒤 시간
        $laterDate1 = $nowDate->copy()->addMinutes(10)->format('Y-m-d H:i:00'); //30분뒤 시간
        $laterDate2 = $nowDate->copy()->addHour()->format('Y-m-d H:i:00'); //1시간뒤 시간
        $aEvent = Events::whereBetween('event_start_dt', [$nowRangeDate, $laterRangeDate])->whereIn('status', [0, 1])->get();

        foreach ($aEvent as $event) {
            // 참여자 목록 확인
            $participants = Participants::with('participant_members')->where('event_id', $event->event_id)->get();

            $aNotificationConfig = [
                'members' => [],
                'template' => '',
            ];

            $tmpMember = [];
            foreach ($participants as $participant) {
                $tmpMember[] = $participant->participant_members->pluck('member_id');
            }
            $tmpMember = collect($tmpMember)->collapse()->filter(function ($value) {
                return $value !== 0; // 0을 제외한 값만 필터링
            })->unique()->all();
            if (count($tmpMember) < 1) {
                continue;
            }

            $eventStartDate = Carbon::parse($event->event_start_dt)->format('Y-m-d H:i:00');
            // 1시간 후 이벤트
            // var_dump($eventStartDate, $laterDate1,$laterDate2);
            if ($eventStartDate === $laterDate1) {
                $aNotificationConfig['template'] = '30minutes_before_match_starts';
                Log::info("reminder : 30분전 ('{$event->title}')");
            } elseif ($eventStartDate === $laterDate2) {
                $aNotificationConfig['template'] = '1hour_before_match_starts';
                Log::info("reminder : 1시간전 ('{$event->title}')");
            } else {
                continue;
            }
            $aNotificationConfig['members'] = $tmpMember;

            foreach ($aNotificationConfig['members'] as $notificationMemberId) {
                NotificationsJob::dispatch(
                    'createNotification',
                    [
                        'lang' => Members::find($notificationMemberId)->language ?? 'en',
                        'template' => $aNotificationConfig['template'],
                        'bind' => [
                            '[name]' => $event->title,
                        ],
                    ],
                    [
                        'member_id' => $notificationMemberId,
                        'scheduled_time' => Carbon::now()->timestamp,  // 현재 시간 또는 특정 시간
                        'link' => "/event/{$event->event_id}/overview",
                        'tag' => "event-{$event->event_id}",
                        'type' => 'event',
                        'profile_img' => '',
                    ]
                );
            }
        }
        return true;
    }
}
