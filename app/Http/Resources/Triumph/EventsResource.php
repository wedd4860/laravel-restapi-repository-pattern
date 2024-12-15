<?php

namespace App\Http\Resources\Triumph;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class EventsResource extends JsonResource
{
    public static $wrap = false;
    protected $shortenUrl;

    public function __construct($resource, $shortenUrl)
    {
        parent::__construct($resource);
        $this->shortenUrl = $shortenUrl;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this->event_id,
            'game_id' => $this->game_id,
            'member_id' => $this->member_id,
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'event_start_dt' => (new Carbon($this->event_start_dt))->format('Y-m-d H:i:s'),
            'entry_start_dt' => (new Carbon($this->entry_start_dt))->format('Y-m-d H:i:s'),
            'entry_end_dt' => (new Carbon($this->entry_end_dt))->format('Y-m-d H:i:s'),
            'status' => $this->status,
            'format' => $this->format,
            'team_size' => $this->team_size,
            'match34' => $this->match34,
            'participant_capacity' => $this->participant_capacity,
            'r_game_name' => $this->games->name,
            'r_game_logo_image' => $this->games->logo_image_url,
            'r_game_image' => $this->games->profile_image_url,
            'r_game_box_image' => $this->games->box_art_image_url,
            'r_game_bg_image' => $this->games->main_banner_bg_image_url,
            'r_member_name' => $this->members?->name,
            'r_member_image_url' => $this->members?->image_url,
            'cnt_participant' => $this->participants_count,
            'url_key' => $this->shortenUrl,
        ];
    }
}
