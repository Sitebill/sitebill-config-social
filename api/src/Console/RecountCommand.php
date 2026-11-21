<?php

namespace App\Configs\Social\Console;

use App\Configs\Social\Models\Comment;
use App\Configs\Social\Models\Follow;
use App\Configs\Social\Models\Like;
use App\Configs\Social\Models\Post;
use App\Configs\Social\Models\Profile;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Sitebill\AppSdk\Host;

/** Пересчёт денормализованных счётчиков (лайки, комментарии, подписки, посты) по всем сайтам. */
class RecountCommand extends Command
{
    protected $signature = 'rumantic-social:recount';

    protected $description = 'Пересчитать счётчики соцсети';

    public function handle(): int
    {
        foreach (Tenant::withoutGlobalScopes()->pluck('id') as $tenantId) {
            // В консоли сайт не привязан: без runAs модели не отфильтруют по tenant_id.
            Host::site()->runAs((int) $tenantId, function () {
                foreach (Post::query()->get() as $p) {
                    $p->forceFill([
                        'likes_count' => Like::query()->where('post_id', $p->id)->count(),
                        'comments_count' => Comment::query()->where('post_id', $p->id)->where('status', 'visible')->count(),
                    ])->save();
                }
                foreach (Profile::query()->get() as $pr) {
                    $pr->forceFill([
                        'posts_count' => Post::query()->where('user_id', $pr->user_id)->count(),
                        'followers_count' => Follow::query()->where('followee_id', $pr->user_id)->count(),
                        'following_count' => Follow::query()->where('follower_id', $pr->user_id)->count(),
                    ])->save();
                }
            });
        }
        $this->info('Готово.');

        return self::SUCCESS;
    }
}
