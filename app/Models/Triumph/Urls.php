<?php

namespace App\Models\Triumph;

use App\Models\Triumph\Members;
use App\Models\Triumph\PlatformGames;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Urls extends Model
{
    use HasFactory;
    protected $primaryKey = 'url_id';
    protected $table = 'urls';

    const UPDATED_AT = 'updated_dt';
}
