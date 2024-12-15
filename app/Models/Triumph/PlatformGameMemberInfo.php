<?php

namespace App\Models\Triumph;

use App\Models\Triumph\Events;
use App\Models\Triumph\Platforms;
use App\Models\Triumph\Games;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class PlatformGameMemberInfo extends Model
{
    use Searchable;
    use HasFactory;
    protected $primaryKey = 'platform_game_member_id';
    protected $table = 'platform_game_member_info';
    protected $hidden = [
        'created_dt', 'updated_dt'
    ];

    public function platformGameMember()
    {
        return $this->hasOne(PlatformGameMembers::class, 'platform_game_member_id', 'platform_game_member_id');
    }
}
