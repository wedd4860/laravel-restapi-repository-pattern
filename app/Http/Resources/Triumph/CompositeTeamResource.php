<?php

namespace App\Http\Resources\Triumph;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompositeTeamResource extends JsonResource
{
    public static $wrap = false;
    protected $teamMember;
    protected $myTeam;
    protected $shortenUrl;

    public function __construct($resource, $teamMember, $myTeam, $shortenUrl)
    {
        parent::__construct($resource);
        $this->teamMember = $teamMember;
        $this->myTeam = $myTeam;
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
            'team_id' => $this->team_id,
            'team_name' => $this->name,
            'team_cover_img_url' => $this->image_url,
            'team_created_at' => $this->created_dt,
            'team_members' => TeamMemberResource::collection($this->teamMember),
            'game_info' => [
                'game_id' => $this->games?->game_id,
                'game_name' => $this->games?->name,
                'game_logo_img_url' => $this->games?->logo_image_url,
                'game_img_url' => $this->games?->logo_image_url,
                'game_box_img_url' => $this->games?->box_art_image_url,
                'game_bg_img_url' => $this->games?->main_banner_bg_image_url,
            ],
            'team_my_info' => [
                'grade' => $this->myTeam?->grade,
                'status' => $this->myTeam?->status,
            ],
            'url_key' => $this->shortenUrl,
        ];
    }
}
