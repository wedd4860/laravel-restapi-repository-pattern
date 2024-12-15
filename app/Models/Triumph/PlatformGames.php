<?php

namespace App\Models\Triumph;

use App\Models\Triumph\Events;
use App\Models\Triumph\Platforms;
use App\Models\Triumph\Games;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class PlatformGames extends Model
{
    use HasFactory;
    protected $primaryKey = 'platform_game_id';
    protected $table = 'platform_games';
    protected $hidden = [
        'created_dt', 'updated_dt'
    ];

    public function platforms()
    {
        return $this->belongsTo(Platforms::class, 'platform_id', 'platform_id');
    }

    public function games()
    {
        return $this->belongsTo(Games::class, 'game_id', 'game_id');
    }

    public function platformGameMembers()
    {
        return $this->hasMany(PlatformGameMembers::class, 'platform_game_id', 'platform_game_id');
    }

    public function gameBanners()
    {
        return $this->hasMany(GameBanners::class, 'game_id', 'game_id');
    }
}
