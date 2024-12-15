<?php

namespace App\Http\Resources\Triumph;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberResource extends JsonResource
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
            'member_id' => $this->member_id,
            'status' => $this->status,
            'grade' => $this->grade,
            'member_name' => $this->members->name,
            'member_created_at' => $this->created_dt,
            'member_profile_img_url' => $this->members->image_url,
        ];
    }
}
