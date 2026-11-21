<?php

namespace SitebillApps\Rumantic\Social\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Sitebill\AppSdk\Host;
use SitebillApps\Rumantic\Social\Models\Comment;
use SitebillApps\Rumantic\Social\Models\Like;
use SitebillApps\Rumantic\Social\Models\Post;
use SitebillApps\Rumantic\Social\Models\User;
use SitebillApps\Rumantic\Social\Support\SocialService;

/**
 * Пример данных из консоли для установщика (`install.sh --demo`) и ИИ-агентов. Приложение не создаёт пользователей
 * платформы, поэтому авторы — уже существующие пользователи сайта (до 4): в Community это администратор и те, кто
 * зарегистрировался. Если пост с таким текстом уже есть, он не дублируется.
 */
final class SeedDemoCommand extends Command
{
    protected $signature = 'sc:seed-demo {--tenant= : id сайта (по умолчанию — первый)} {--json : один JSON в ответ}';

    protected $description = 'Социальная сеть: загрузить пример постов, лайков и комментариев';

    private const POSTS = [
        'ru' => ['Всем привет! Это наше новое сообщество. Рассказывайте, чем увлекаетесь.', 'Сегодня на закате во дворе было удивительно красиво. Кто ещё снимал?', 'В субботу встреча клуба в 18:00. Приходите, будет чай и настолки!', 'Собираем велопрогулку в воскресенье утром, маршрут около 20 км, темп спокойный.'],
        'en' => ['Hello everyone! This is our new community. Tell us what you are into.', 'The sunset in our yard was amazing today. Did anyone else take photos?', 'Club meetup on Saturday at 6 pm. Come over, there will be tea and board games!', 'A bike ride on Sunday morning, about 20 km at an easy pace. Join us!'],
    ];

    public function handle(): int
    {
        $tenantId = (int) ($this->option('tenant') ?: DB::table('tenants')->orderBy('id')->value('id'));
        if ($tenantId === 0) {
            return $this->finish(['status' => 'error', 'message' => 'Сайт не найден'], self::FAILURE);
        }
        $result = Host::site()->runAs($tenantId, function () {
            $ids = array_column(Host::users()->search('', 4), 'id');
            if ($ids === []) {
                return ['status' => 'error', 'message' => 'На сайте нет пользователей'];
            }
            $users = User::query()->whereIn('id', $ids)->get()->values();
            $lang = str_starts_with((string) Host::site()->current()?->locale, 'ru') ? 'ru' : 'en';
            $created = 0;
            foreach (self::POSTS[$lang] as $i => $body) {
                $author = $users[$i % $users->count()];
                if (Post::query()->where('user_id', $author->id)->where('body', $body)->exists()) {
                    continue;
                }
                $post = SocialService::createPost($author, $body, null);
                $created++;
                foreach ($users as $fan) {
                    if ($fan->id !== $author->id) {
                        Like::query()->firstOrCreate(['post_id' => $post->id, 'user_id' => $fan->id]);
                        Comment::query()->create(['post_id' => $post->id, 'user_id' => $fan->id, 'body' => $lang === 'ru' ? 'Классная идея, я с вами!' : 'Great idea, count me in!']);
                    }
                }
                $post->forceFill([
                    'likes_count' => Like::query()->where('post_id', $post->id)->count(),
                    'comments_count' => Comment::query()->where('post_id', $post->id)->count(),
                ])->save();
            }
            foreach ($users as $a) {
                foreach ($users as $b) {
                    if ($a->id !== $b->id) {
                        SocialService::toggleFollowIfNot($a, $b);
                    }
                }
            }

            return ['status' => 'ok', 'posts' => $created, 'users' => $users->count()];
        });

        return $this->finish($result, self::SUCCESS);
    }

    private function finish(array $data, int $code): int
    {
        $this->line($this->option('json') ? json_encode($data, JSON_UNESCAPED_UNICODE) : ($data['message'] ?? 'Пример загружен: постов '.($data['posts'] ?? 0).', авторов '.($data['users'] ?? 0)));

        return $code;
    }
}
