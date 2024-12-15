<?php

namespace App\Models\Triumph;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Platforms extends Model
{
    use HasFactory;
    protected $primaryKey = 'platform_id';
    protected $table = 'platforms';
    protected $hidden = [
        'created_dt', 'updated_dt'
    ];

    public function platformGames()
    {
        return $this->hasMany(PlatformGames::class, 'platform_id', 'platform_id');
    }
}
