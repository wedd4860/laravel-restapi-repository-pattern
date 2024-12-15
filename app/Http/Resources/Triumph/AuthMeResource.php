<?php

namespace App\Http\Resources\Triumph;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthMeResource extends JsonResource
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
            'email'=>$this->email,
            'name'=>$this->name,
            'image_url'=>$this->image_url,
            'member_id'=>$this->member_id,
            'oauth_type'=>$this->oauth_type,
            'language'=>$this->language,
            'push'=>$this->push,
        ];
    }
}
