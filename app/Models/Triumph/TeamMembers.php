<?php

namespace App\Models\Triumph;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamMembers extends Model
{
    use HasFactory;
    protected $primaryKey = 'team_member_id';
    protected $table = 'team_members';
    protected $hidden = [];

    public function teams()
    {
        return $this->belongsTo(Teams::class, 'team_id', 'team_id');
    }

    public function members()
    {
        return $this->belongsTo(Members::class, 'member_id', 'member_id');
    }
}
