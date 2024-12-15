<?php

namespace App\Jobs;

use App\Repositories\Triumph\NotificationsRepository;
use App\Services\NotificationsService;
use App\Services\NotificationTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotificationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $type;
    protected $template;
    protected $params;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $type,
        array $template,
        array $params
    ) {
        $this->type = $type;
        $this->template = $template;
        $this->params = $params;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationTemplateService $notificationTemplateService, NotificationsService $notificationsService): void
    {
        try {
            switch ($this->type) {
                case 'createNotification':
                    // 1. 알림 템플릿
                    $aTemplate = $notificationTemplateService->createTemplate($this->template);
                    // 2. 알림발송
                    $notificationsService->createNotification($this->params, $aTemplate);
                    break;
                default:
                    throw new \InvalidArgumentException("Invalid type: {$this->type}");
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
