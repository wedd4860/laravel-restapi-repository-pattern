<?php

namespace App\Events\triumph;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BracketsStatus implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $bracketId = '';
    public $status = '';

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(int $bracketId, int $status)
    {
        $this->bracketId = $bracketId;
        $this->status = $status;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // 비공개 채널
        // return new PrivateChannel('chat.' . $this->bracketId . '.member');
        // 공개채널
        return new Channel('chat.triumph.' . $this->bracketId);
    }

    public function broadcastAs()
    {
        return 'bracket.status';
    }
}
