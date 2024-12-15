<?php

namespace App\Models\Triumph;

use App\Models\Triumph\Members;
use App\Models\Triumph\PlatformGames;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class Events extends Model
{
    use Searchable;
    use HasFactory;
    protected $primaryKey = 'event_id';
    protected $table = 'events';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [];

    protected $hidden = [
        'password'
    ];

    public function members()
    {
        return $this->belongsTo(Members::class, 'member_id', 'member_id');
    }

    public function participants()
    {
        return $this->hasMany(Participants::class, 'event_id', 'event_id');
    }

    public function games()
    {
        return $this->belongsTo(Games::class, 'game_id', 'game_id');
    }

    public function searchableAs(): string
    {
        return 'tp_events';
    }

    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        // games 관계 모델의 데이터를 인덱싱하는데 사용할수있도록 설정
        return $query->with('games');
    }

    public function brackets()
    {
        return $this->hasMany(Brackets::class, 'event_id', 'event_id');
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        // events 정보
        $aResult = [
            'id' => (string) $this->event_id,
            'event_id' => (int) $this->event_id,
            'title' => $this->title,
            'description' => $this->description,
            'format' => (int) $this->format,
            'team_size' => (int) $this->team_size,
            'status' => (int) $this->status,
            'event_start_dt' => (int) Carbon::parse($this->event_start_dt)->timestamp,
            'games_game_id' => (int) $this->games->game_id,
            'games_name' => $this->games->name,
        ];
        return $aResult;
    }
}
