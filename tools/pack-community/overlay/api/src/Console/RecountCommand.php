<?php

namespace SitebillApps\Rumantic\Social\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Sitebill\AppSdk\Host;
use SitebillApps\Rumantic\Social\Models\Comment;
use SitebillApps\Rumantic\Social\Models\Follow;
use SitebillApps\Rumantic\Social\Models\Like;
use SitebillApps\Rumantic\Social\Models\Post;
use SitebillApps\Rumantic\Social\Models\Profile;

/** Пересчёт денормализованных счётчиков (лайки, комментарии, подписки, посты) по сайтам, где есть данные приложения. */
final class RecountCommand extends Command
{
    protected $signature = 'rumantic-social:recount';

    protected $description = 'Социальная сеть: пересчитать счётчики';

    public function handle(): int
    {
        $tenantIds = DB::table('app_rumantic_social_profiles')->distinct()->pluck('tenant_id');
        foreach ($tenantIds as $tenantId) {
            // В консоли сайт не привязан: без runAs модели приложения не работают с данными.
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
