<?php

namespace App\Configs\Social\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Таблица social_comments; счётчики ведёт код (SocialService), поэтому в $fillable их нет. */
class Comment extends Model
{
    use BelongsToTenant;

    protected $table = 'social_comments';

    protected $fillable = ['post_id', 'user_id', 'body', 'status'];
}
