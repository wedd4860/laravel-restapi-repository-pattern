<?php

namespace App\Models\Triumph;

use App\Models\Triumph\PlatformGames;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Games extends Model
{
    use Searchable;
    use HasFactory;
    protected $primaryKey = 'game_id';
    protected $table = 'games';
    public $incrementing = true;
    public $timestamps = false;
    protected $hidden = [
        'created_dt', 'updated_dt'
    ];

    public function platformGames()
    {
        return $this->hasMany(PlatformGames::class, 'game_id', 'game_id');
    }

    public function gameBanners()
    {
        return $this->hasMany(GameBanners::class, 'game_id', 'game_id');
    }

    public function teams()
    {
        return $this->hasMany(Teams::class, 'game_id', 'game_id');
    }

    public function events()
    {
        return $this->hasMany(Events::class, 'game_id', 'game_id');
    }

    public function searchableAs(): string
    {
        return 'tp_games';
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        // 게임 정보
        $aResult = [
            'id' => (string) $this->game_id,
            'game_id' => (int) $this->game_id,
            'game_name' => (string) $this->name,
        ];
        return $aResult;
    }
}
