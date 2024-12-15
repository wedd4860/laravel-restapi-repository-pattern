<?php

namespace App\Models\Triumph;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use stdClass;

class Members extends Model
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    protected $primaryKey = 'member_id';
    protected $table = 'members';

    protected $fillable = [
        'email',
        'name',
        'service',
        'password',
        'password_temp',
        'auth_code',
        'auth_code_date',
        'oauth_id',
        'image_url',
        'grade',
        'status',
        'login_failures',
        'agreement_date',
        'created_dt',
        'updated_dt',
    ];

    protected $hidden = [
        'password', 'password_temp', 'last_login_date', 'agreement_date', 'created_dt', 'updated_dt', 'auth_code', 'auth_code_date', 'oauth_id', 'grade', 'status', 'login_failures'
    ];

    public function events()
    {
        return $this->hasMany(Events::class, 'member_id', 'member_id');
    }

    public function teamMembers()
    {
        return $this->hasMany(TeamMembers::class, 'member_id', 'member_id');
    }
}
