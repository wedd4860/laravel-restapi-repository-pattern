<?php

namespace App\Models\Triumph;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Participants extends Model
{
    use HasFactory;
    protected $primaryKey = 'participant_id';
    protected $table = 'participants';

    public function events()
    {
        return $this->belongsTo(Events::class, 'event_id', 'event_id');
    }

    public function participant_members()
    {
        return $this->hasMany(ParticipantMembers::class, 'participant_id', 'participant_id');
    }

    public function bracket_entries()
    {
        return $this->hasMany(BracketEntries::class, 'participant_id', 'participant_id');
    }
}
