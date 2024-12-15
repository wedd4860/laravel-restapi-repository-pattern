<?php

namespace App\Http\Resources\Triumph;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class NotificationTokenCheckResource extends JsonResource
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
            'token' => Arr::get($this->resource, 'token', null),
            'member_id' => Arr::get($this->resource, 'member_id', null),
            'created_at' => Arr::get($this->resource, 'created_at', null),
        ];
    }
}
