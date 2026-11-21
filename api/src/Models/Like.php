<?php

namespace App\Configs\Social\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Таблица social_likes; счётчики ведёт код (SocialService), поэтому в $fillable их нет. */
class Like extends Model
{
    use BelongsToTenant;

    protected $table = 'social_likes';

    protected $fillable = ['post_id', 'user_id'];
}
