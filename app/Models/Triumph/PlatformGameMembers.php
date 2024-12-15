<?php

namespace App\Models\Triumph;

use App\Models\Triumph\Events;
use App\Models\Triumph\Platforms;
use App\Models\Triumph\Games;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class PlatformGameMembers extends Model
{
    use Searchable;
    use HasFactory;
    protected $primaryKey = 'platform_game_member_id';
    protected $table = 'platform_game_members';
    protected $hidden = [
        'created_dt', 'updated_dt'
    ];

    public function platformGames()
    {
        return $this->belongsTo(PlatformGames::class, 'platform_game_id', 'platform_game_id');
    }

    public function platformGameMemberInfo()
    {
        return $this->hasOne(PlatformGameMemberInfo::class, 'platform_game_member_id', 'platform_game_member_id');
    }
}
