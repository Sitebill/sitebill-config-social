<?php

namespace App\Configs\Social\Demo;

use App\Configs\Social\Models\Comment;
use App\Configs\Social\Models\Like;
use App\Configs\Social\Models\Post;
use App\Configs\Social\Models\Profile;
use App\Configs\Social\Support\SocialService;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Демо-данные: несколько участников с постами, лайками, комментариями и подписками. Вызывается и кнопкой в UI,
 * и командой `social:seed-demo` (установщик Community: --demo). Идемпотентно: повторный вызов ничего не дублирует.
 * Демо-пользователи без возможности входа (случайный пароль), email на .invalid.
 */
final class SocialDemo
{
    private const PEOPLE = [
        ['Анна Орлова', 'anna', 'Люблю горы и кофе. Пишу о путешествиях.'],
        ['Максим Белов', 'maxim', 'Фотограф-любитель, гуляю по району с камерой.'],
        ['Ирина Лунина', 'irina', 'Организую встречи нашего клуба.'],
        ['Тимур Рахимов', 'timur', 'Собираю велопрогулки по выходным.'],
    ];

    private const POSTS = [
        ['anna', 'Всем привет! Это наше новое сообщество. Рассказывайте, чем увлекаетесь.'],
        ['maxim', 'Сегодня на закате во дворе было удивительно красиво. Кто ещё снимал?'],
        ['irina', 'В субботу встреча клуба в 18:00. Приходите, будет чай и настолки!'],
        ['timur', 'Собираем велопрогулку в воскресенье утром, маршрут около 20 км, темп спокойный.'],
        ['anna', 'Нашла отличный маршрут для прогулки на природе, поделюсь фото на выходных.'],
    ];

    /** @return array{users: int, posts: int} сколько создано */
    public static function seed(): array
    {
        $tenantId = (int) app('currentTenant')->id;
        $users = [];
        $created = ['users' => 0, 'posts' => 0];

        foreach (self::PEOPLE as [$name, $handle, $bio]) {
            $email = "demo-{$handle}-{$tenantId}@social.invalid";
            $user = User::query()->where('email', $email)->first();
            if (! $user) {
                $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => Hash::make(Str::random(40)), 'tenant_id' => $tenantId]);
                $created['users']++;
            }
            $profile = SocialService::ensureProfile($user);
            $profile->update(['handle' => Profile::query()->where('handle', $handle)->where('id', '!=', $profile->id)->exists() ? $profile->handle : $handle, 'bio' => $bio]);
            $users[$handle] = $user;
        }

        foreach (self::POSTS as [$handle, $body]) {
            $author = $users[$handle];
            if (Post::query()->where('user_id', $author->id)->where('body', $body)->exists()) {
                continue;
            }
            $post = SocialService::createPost($author, $body, null);
            $created['posts']++;
            // Лайки и комментарий от соседей — чтобы счётчики и лента выглядели живыми.
            foreach (collect($users)->except($handle)->take(2) as $fan) {
                Like::query()->firstOrCreate(['post_id' => $post->id, 'user_id' => $fan->id]);
            }
            $post->forceFill(['likes_count' => Like::query()->where('post_id', $post->id)->count()])->save();
            $commenter = collect($users)->except($handle)->first();
            Comment::query()->create(['post_id' => $post->id, 'user_id' => $commenter->id, 'body' => 'Классная идея, я с вами!']);
            $post->forceFill(['comments_count' => Comment::query()->where('post_id', $post->id)->count()])->save();
        }

        // Подписки: все на Анну, Анна на Ирину.
        foreach ($users as $handle => $user) {
            if ($handle !== 'anna') {
                SocialService::toggleFollowIfNot($user, $users['anna']);
            }
        }
        SocialService::toggleFollowIfNot($users['anna'], $users['irina']);

        return $created;
    }
}
