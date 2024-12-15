<?php

namespace App\Http\Resources\Triumph;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GamesResource extends JsonResource
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
            'game_id' => $this->game_id,
            'game_name' => $this->name,
            'game_image' => $this->profile_image_url,
            'game_logo_image' => $this->logo_image_url,
            'game_box_image' => $this->box_art_image_url,
            'game_bg_image' => $this->main_banner_bg_image_url,
            'game_site' => $this->game_site_url,
            'game_connect' => $this->game_connect_url,
            'banner_list' => $this->gameBanners->map(function ($banner) {
                return [
                    'type' => $banner->type,
                    'sort' => $banner->order,
                    'title' => $banner->title,
                    'desc' => $banner->desc,
                    'thumb_image' => $banner->thumbnail_image_url,
                    'link' => $banner->url,
                    'target' => $banner->target,
                ];
            }),
        ];
    }
}
