<?php

namespace App\Models\Triumph;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BracketEntries extends Model
{
    use HasFactory;
    protected $primaryKey = null;
    protected $table = 'bracket_entries';

    public function brackets()
    {
        return $this->belongsTo(Brackets::class, 'bracket_id', 'bracket_id');
    }

    public function bracketSets()
    {
        return $this->hasMany(BracketSets::class, 'participant_id', 'participant_id');
    }

    public function participants()
    {
        return $this->belongsTo(Participants::class, 'participant_id', 'participant_id');
    }
}
