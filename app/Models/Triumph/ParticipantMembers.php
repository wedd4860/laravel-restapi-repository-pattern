<?php

namespace App\Models\Triumph;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantMembers extends Model
{
    use HasFactory;
    protected $primaryKey = null;
    protected $table = 'participant_members';

    public function participants()
    {
        return $this->belongsTo(Participants::class, 'participant_id', 'participant_id');
    }
}
