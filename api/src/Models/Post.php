<?php

namespace App\Configs\Social\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Таблица social_posts; счётчики ведёт код (SocialService), поэтому в $fillable их нет. */
class Post extends Model
{
    use BelongsToTenant;

    protected $table = 'social_posts';

    protected $fillable = ['user_id', 'body', 'image_path', 'status'];
}
