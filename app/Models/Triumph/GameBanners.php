<?php

namespace App\Models\Triumph;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameBanners extends Model
{
    use HasFactory;
    protected $primaryKey = ['type', 'game_id', 'order']; // 지원안함 명시적처리
    protected $table = 'game_banners';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['type', 'game_id', 'order', 'title', 'desc', 'thumbnail_image_url', 'url', 'target'];

    protected $hidden = [];

    public function games()
    {
        return $this->belongsTo(Games::class, 'game_id', 'game_id');
    }

    public function platformGames()
    {
        return $this->belongsToMany(PlatformGames::class, 'game_id', 'game_id');
    }
}
