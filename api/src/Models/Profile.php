<?php

namespace App\Configs\Social\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Таблица social_profiles; счётчики ведёт код (SocialService), поэтому в $fillable их нет. */
class Profile extends Model
{
    use BelongsToTenant;

    protected $table = 'social_profiles';

    protected $fillable = ['user_id', 'handle', 'bio', 'website', 'avatar_path', 'is_banned'];
}
