<?php

namespace App\Configs\Social\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Таблица social_reports; счётчики ведёт код (SocialService), поэтому в $fillable их нет. */
class Report extends Model
{
    use BelongsToTenant;

    protected $table = 'social_reports';

    protected $fillable = ['target_type', 'target_id', 'reporter_id', 'reason', 'status', 'resolved_by'];
}
