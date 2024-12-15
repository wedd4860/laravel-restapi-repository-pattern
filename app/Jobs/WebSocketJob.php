<?php

namespace App\Jobs;

use App\Services\WebsocketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WebSocketJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $token;
    protected $channel;
    protected $flow;

    /**
     * Create a new job instance.
     */
    public function __construct(string $token = '', string $channel = '', string $flow = '')
    {
        $this->token = $token;
        $this->channel = $channel;
        $this->flow = $flow;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        if (in_array('', [$this->token, $this->channel, $this->flow])) {
            return false;
        }
        $webSocketService = new WebsocketService($this->token, $this->channel, $this->flow);
        // 웹소켓 전송
        return $webSocketService->flow();
    }
}
