<?php

namespace App\Configs\Social\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Таблица social_follows; счётчики ведёт код (SocialService), поэтому в $fillable их нет. */
class Follow extends Model
{
    use BelongsToTenant;

    protected $table = 'social_follows';

    protected $fillable = ['follower_id', 'followee_id'];
}
