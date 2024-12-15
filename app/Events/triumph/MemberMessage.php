<?php

namespace App\Events\triumph;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $bracketId = '';
    public $memberId = '';
    public $message = '';

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(int $bracketId, int $memberId, string $message)
    {
        $this->bracketId = $bracketId;
        $this->memberId = $memberId;
        $this->message = $message;
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
        return 'member.message';
    }
}
