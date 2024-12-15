<?php

namespace App\Http\Resources\Triumph;

use App\Library\Util;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventSearchResource extends JsonResource
{
    public static $wrap = false;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this->event_id,
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'member_id' => $this->member_id,
            'event_start_dt' => Util::getISO8601($this->event_start_dt),
            'entry_start_dt' => Util::getISO8601($this->entry_start_dt),
            'entry_end_dt' => Util::getISO8601($this->entry_end_dt),
            'status' => $this->status,
            'format' => $this->format,
            'team_size' => $this->team_size,
            'participant_capacity' => $this->participant_capacity,
            'r_game_id' => $this->games->game_id,
            'r_game_name' => $this->games->name,
            'r_game_image' => $this->games->profile_image_url,
            'r_game_logo_image' => $this->games->logo_image_url,
            'r_game_box_image' => $this->games->box_art_image_url,
            'r_game_bg_image' => $this->games->main_banner_bg_image_url,
            'r_member_name' => $this->members?->name,
            'r_member_image' => $this->members?->image_url,
            'cnt_participant' => $this->participants_count
        ];
    }
}
