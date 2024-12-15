<?php

namespace App\Models\Triumph;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BracketSets extends Model
{
    use HasFactory;
    protected $primaryKey = null;
    protected $table = 'bracket_sets';

    public function brackets()
    {
        return $this->belongsTo(Brackets::class, 'bracket_id', 'bracket_id');
    }

    public function bracketEntries()
    {
        return $this->belongsTo(BracketEntries::class, 'participant_id', 'participant_id');
    }

    public function participants()
    {
        return $this->belongsTo(Participants::class, 'participant_id', 'participant_id');
    }
}
