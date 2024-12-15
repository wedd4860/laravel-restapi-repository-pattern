<?php

namespace App\Models\Triumph;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brackets extends Model
{
    use HasFactory;
    protected $primaryKey = 'bracket_id';
    protected $table = 'brackets';
    public $incrementing = true;
    public $timestamps = false;

    public function bracketEntries()
    {
        return $this->hasMany(BracketEntries::class, 'bracket_id', 'bracket_id');
    }

    public function bracketSets()
    {
        return $this->hasMany(BracketSets::class, 'bracket_id', 'bracket_id');
    }
    
    public function events()
    {
        return $this->belongsTo(Events::class, 'event_id', 'event_id');
    }
}
