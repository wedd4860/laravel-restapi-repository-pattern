<?php

namespace App\Models\Triumph;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teams extends Model
{
    use HasFactory;
    protected $primaryKey = 'team_id';
    protected $table = 'teams';
    protected $hidden = [];

    public function teamMembers()
    {
        return $this->hasMany(TeamMembers::class, 'team_id', 'team_id');
    }

    public function games()
    {
        return $this->belongsTo(Games::class, 'game_id', 'game_id');
    }
}
