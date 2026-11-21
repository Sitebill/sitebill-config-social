<?php

namespace App\Configs\Social\Support;

use App\Configs\Social\Models\Comment;
use App\Configs\Social\Models\Follow;
use App\Configs\Social\Models\Like;
use App\Configs\Social\Models\Post;
use App\Configs\Social\Models\Profile;
use App\Configs\Social\Models\Report;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Sitebill\AppSdk\Host;

/**
 * Правила соцсети: профили, подписки, посты, лайки, комментарии, жалобы. Резолверы GraphQL тонкие — вся логика и
 * счётчики здесь, в транзакциях (счётчики денормализованы, `rumantic-social:recount` их пересчитывает).
 */
final class SocialService
{
    public const APP_ID = 'rumantic.social';

    private const HANDLE_RE = '/^[a-z0-9_]{3,30}$/';

    // ── профили ────────────────────────────────────────────────────────────

    /** Профиль создаётся лениво, при первом действии пользователя; хэндл — из имени, при конфликте с суффиксом. */
    public static function ensureProfile(Authenticatable $user): Profile
    {
        return self::ensureProfileFor((int) $user->getAuthIdentifier(), (string) ($user->name ?? ''));
    }

    public static function ensureProfileFor(int $userId, string $name): Profile
    {
        $existing = Profile::query()->where('user_id', $userId)->first();
        if ($existing) {
            return $existing;
        }
        $base = preg_replace('/[^a-z0-9_]/', '', Str::slug($name, '_'));
        $base = substr((string) $base, 0, 24);
        $base = strlen($base) >= 3 ? $base : 'user';
        $handle = $base;
        for ($i = 2; Profile::query()->where('handle', $handle)->exists(); $i++) {
            $handle = $base.$i;
        }

        return Profile::query()->create(['user_id' => $userId, 'handle' => $handle]);
    }

    public static function saveProfile(Authenticatable $user, array $in): Profile
    {
        $p = self::ensureProfile($user);
        $handle = strtolower(trim((string) ($in['handle'] ?? $p->handle)));
        if (! preg_match(self::HANDLE_RE, $handle)) {
            throw ValidationException::withMessages(['handle' => 'Ник: 3–30 символов, латиница, цифры и «_».']);
        }
        if (Profile::query()->where('handle', $handle)->where('id', '!=', $p->id)->exists()) {
            throw ValidationException::withMessages(['handle' => 'Этот ник занят.']);
        }
        $website = trim((string) ($in['website'] ?? ''));
        // Только http(s): иначе javascript: в ссылке профиля.
        if ($website !== '' && ! preg_match('#^https?://#i', $website)) {
            throw ValidationException::withMessages(['website' => 'Адрес сайта должен начинаться с http:// или https://']);
        }
        $avatar = array_key_exists('avatar_path', $in) ? self::ownPath($in['avatar_path']) : $p->avatar_path;
        if ($avatar !== $p->avatar_path) {
            Host::files()->delete(self::APP_ID, $p->avatar_path);
        }
        $p->update(['handle' => $handle, 'bio' => trim((string) ($in['bio'] ?? '')) ?: null, 'website' => $website ?: null, 'avatar_path' => $avatar]);

        return $p->refresh();
    }

    /** Путь картинки принимаем только из каталога приложения: иначе можно подсунуть чужой файл хранилища. */
    public static function ownPath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (Host::files()->url(self::APP_ID, $path) === null) {
            throw ValidationException::withMessages(['image' => 'Неверный путь картинки.']);
        }

        return $path;
    }

    // ── подписки ───────────────────────────────────────────────────────────

    /** @return bool подписан ли после вызова */
    public static function toggleFollow(Authenticatable $me, int $targetUserId): bool
    {
        if ($targetUserId === (int) $me->id) {
            throw ValidationException::withMessages(['user_id' => 'Нельзя подписаться на себя.']);
        }
        $name = Host::users()->names([$targetUserId])[$targetUserId] ?? null;
        if ($name === null || SocialAccess::isBanned($targetUserId)) {
            throw ValidationException::withMessages(['user_id' => 'Пользователь не найден.']);
        }
        $mine = self::ensureProfile($me);
        $theirs = self::ensureProfileFor($targetUserId, $name);

        $following = DB::transaction(function () use ($me, $targetUserId, $mine, $theirs) {
            $row = Follow::query()->where('follower_id', $me->id)->where('followee_id', $targetUserId)->first();
            if ($row) {
                $row->delete();
                $mine->decrement('following_count');
                $theirs->decrement('followers_count');

                return false;
            }
            Follow::query()->create(['follower_id' => $me->id, 'followee_id' => $targetUserId]);
            $mine->increment('following_count');
            $theirs->increment('followers_count');

            return true;
        });
        if ($following) {
            self::notify($targetUserId, 'follow', '', '?u='.$mine->handle, $me);
        }

        return $following;
    }

    /** Подписка без уведомления и без переключения назад — для демо-данных. */
    public static function toggleFollowIfNot(Authenticatable $me, Authenticatable $target): void
    {
        $mine = self::ensureProfile($me);
        $theirs = self::ensureProfile($target);
        if (Follow::query()->where('follower_id', $me->getAuthIdentifier())->where('followee_id', $target->getAuthIdentifier())->exists()) {
            return;
        }
        Follow::query()->create(['follower_id' => $me->getAuthIdentifier(), 'followee_id' => $target->getAuthIdentifier()]);
        $mine->increment('following_count');
        $theirs->increment('followers_count');
    }

    // ── посты ──────────────────────────────────────────────────────────────

    public static function createPost(Authenticatable $me, string $body, ?string $imagePath): Post
    {
        $body = trim($body);
        if ($body === '' && trim((string) $imagePath) === '') {
            throw ValidationException::withMessages(['body' => 'Пустой пост.']);
        }
        self::guardRate(Post::query()->where('user_id', $me->id), SocialSettings::postsPerDay(), 'Лимит постов на сегодня исчерпан.');
        $image = self::ownPath($imagePath);
        $profile = self::ensureProfile($me);

        return DB::transaction(function () use ($me, $body, $image, $profile) {
            $post = Post::query()->create(['user_id' => $me->id, 'body' => $body, 'image_path' => $image]);
            $profile->increment('posts_count');

            return $post;
        });
    }

    public static function deletePost(Authenticatable $me, int $id): void
    {
        $post = Post::query()->find($id);
        if (! $post || ((int) $post->user_id !== (int) $me->id && ! SocialAccess::isModerator($me))) {
            throw ValidationException::withMessages(['id' => 'Пост не найден.']);
        }
        DB::transaction(function () use ($post) {
            Host::files()->delete(self::APP_ID, $post->image_path);
            Like::query()->where('post_id', $post->id)->delete();
            Comment::query()->where('post_id', $post->id)->delete();
            Report::query()->where('target_type', 'post')->where('target_id', $post->id)->delete();
            Profile::query()->where('user_id', $post->user_id)->where('posts_count', '>', 0)->decrement('posts_count');
            $post->delete();
        });
    }

    /** @return array{liked: bool, likes_count: int} */
    public static function toggleLike(Authenticatable $me, int $postId): array
    {
        $post = self::visiblePost($postId);
        self::ensureProfile($me);
        $liked = DB::transaction(function () use ($me, $post) {
            $row = Like::query()->where('post_id', $post->id)->where('user_id', $me->id)->first();
            if ($row) {
                $row->delete();
                $post->decrement('likes_count');

                return false;
            }
            Like::query()->create(['post_id' => $post->id, 'user_id' => $me->id]);
            $post->increment('likes_count');

            return true;
        });
        if ($liked && (int) $post->user_id !== (int) $me->id) {
            self::notify((int) $post->user_id, 'like', '', '?v=post&id='.$post->id, $me);
        }

        return ['liked' => $liked, 'likes_count' => (int) $post->refresh()->likes_count];
    }

    public static function addComment(Authenticatable $me, int $postId, string $body): Comment
    {
        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Пустой комментарий.']);
        }
        $post = self::visiblePost($postId);
        self::guardRate(Comment::query()->where('user_id', $me->id), SocialSettings::commentsPerDay(), 'Лимит комментариев на сегодня исчерпан.');
        self::ensureProfile($me);
        $comment = DB::transaction(function () use ($me, $post, $body) {
            $c = Comment::query()->create(['post_id' => $post->id, 'user_id' => $me->id, 'body' => $body]);
            $post->increment('comments_count');

            return $c;
        });
        if ((int) $post->user_id !== (int) $me->id) {
            self::notify((int) $post->user_id, 'comment', mb_substr($body, 0, 140), '?v=post&id='.$post->id, $me);
        }

        return $comment;
    }

    public static function deleteComment(Authenticatable $me, int $id): void
    {
        $c = Comment::query()->find($id);
        if (! $c || ((int) $c->user_id !== (int) $me->id && ! SocialAccess::isModerator($me))) {
            throw ValidationException::withMessages(['id' => 'Комментарий не найден.']);
        }
        DB::transaction(function () use ($c) {
            Report::query()->where('target_type', 'comment')->where('target_id', $c->id)->delete();
            Post::query()->where('id', $c->post_id)->where('comments_count', '>', 0)->decrement('comments_count');
            $c->delete();
        });
    }

    // ── жалобы и модерация ─────────────────────────────────────────────────

    public static function report(Authenticatable $me, string $type, int $id, ?string $reason): Report
    {
        $exists = match ($type) {
            'post' => Post::query()->whereKey($id)->exists(),
            'comment' => Comment::query()->whereKey($id)->exists(),
            'profile' => Profile::query()->where('user_id', $id)->exists(),
            default => false,
        };
        if (! $exists) {
            throw ValidationException::withMessages(['target_id' => 'Объект не найден.']);
        }
        // Одна жалоба от пользователя на объект: повтор не плодит записей, а возвращает существующую.
        $report = Report::query()->firstOrCreate(
            ['target_type' => $type, 'target_id' => $id, 'reporter_id' => $me->id],
            ['reason' => $reason ? mb_substr(trim($reason), 0, 500) : null],
        );
        if ($report->wasRecentlyCreated) {
            Host::notifier()->notifyAdmins(self::APP_ID, self::t('report_title'), self::t('report_body', ['type' => $type]), self::url('/admin/x/'.self::APP_ID));
        }

        return $report;
    }

    /** dismiss — отклонить; hide — скрыть объект; ban — скрыть и забанить автора. Жалоба закрывается в любом случае. */
    public static function resolveReport(Authenticatable $moderator, int $id, string $action): Report
    {
        $report = Report::query()->findOrFail($id);
        DB::transaction(function () use ($report, $moderator, $action) {
            if ($action !== 'dismiss') {
                $authorId = self::hideTarget($report);
                if ($action === 'ban' && $authorId) {
                    self::setBanned($moderator, $authorId, true);
                }
            }
            Report::query()->where('target_type', $report->target_type)->where('target_id', $report->target_id)->where('status', 'open')
                ->update(['status' => 'resolved', 'resolved_by' => $moderator->id]);
        });

        return $report->refresh();
    }

    /** @return int|null id автора скрытого объекта */
    private static function hideTarget(Report $r): ?int
    {
        if ($r->target_type === 'profile') {
            return (int) $r->target_id;
        }
        $model = $r->target_type === 'post' ? Post::query()->find($r->target_id) : Comment::query()->find($r->target_id);
        $model?->update(['status' => 'hidden']);

        return $model ? (int) $model->user_id : null;
    }

    /** Себя забанить нельзя: роль другого пользователя пакет приложения не видит, поэтому защищаем хотя бы модератора от самого себя. */
    public static function setBanned(Authenticatable $moderator, int $userId, bool $banned): Profile
    {
        if ($userId === (int) $moderator->getAuthIdentifier()) {
            throw ValidationException::withMessages(['user_id' => 'Себя забанить нельзя.']);
        }
        $name = Host::users()->names([$userId])[$userId] ?? null;
        if ($name === null) {
            throw ValidationException::withMessages(['user_id' => 'Пользователь не найден.']);
        }
        $p = self::ensureProfileFor($userId, $name);
        $p->update(['is_banned' => $banned]);

        return $p->refresh();
    }

    // ── вспомогательное ────────────────────────────────────────────────────

    private static function visiblePost(int $id): Post
    {
        $post = Post::query()->where('status', 'visible')->find($id);
        if (! $post) {
            throw ValidationException::withMessages(['post_id' => 'Пост не найден.']);
        }

        return $post;
    }

    private static function guardRate($query, int $limit, string $message): void
    {
        if ((clone $query)->where('created_at', '>=', now()->subDay())->count() >= $limit) {
            throw ValidationException::withMessages(['body' => $message]);
        }
    }

    /** Уведомление платформы (колокольчик, e-mail, мессенджеры по настройкам получателя); сбой там не ломает действие. */
    private static function notify(int $userId, string $kind, string $text, string $query, Authenticatable $actor): void
    {
        $aid = (int) $actor->getAuthIdentifier();
        $who = (string) (Host::users()->names([$aid])[$aid] ?? '');
        Host::notifier()->notifyUsers(self::APP_ID, [$userId], self::t($kind.'_title', ['who' => $who]), $text, self::url('/'.$query));
    }

    public static function url(string $path): ?string
    {
        $base = Host::site()->current()?->url;

        return $base ? rtrim($base, '/').$path : null;
    }

    /** Тексты серверных уведомлений на языке сайта. */
    public static function t(string $key, array $vars = []): string
    {
        $locale = Host::site()->current()?->locale ?? 'ru';
        $dict = [
            'ru' => ['like_title' => ':who оценил(а) ваш пост', 'comment_title' => ':who прокомментировал(а) ваш пост', 'follow_title' => ':who подписался(лась) на вас', 'report_title' => 'Новая жалоба', 'report_body' => 'Жалоба на объект: :type'],
            'en' => ['like_title' => ':who liked your post', 'comment_title' => ':who commented on your post', 'follow_title' => ':who started following you', 'report_title' => 'New report', 'report_body' => 'A report on: :type'],
            'sr' => ['like_title' => ':who je lajkovao/la vašu objavu', 'comment_title' => ':who je komentarisao/la vašu objavu', 'follow_title' => ':who vas je zapratio/la', 'report_title' => 'Nova prijava', 'report_body' => 'Prijava na: :type'],
            'tg' => ['like_title' => ':who паёми шуморо писанд кард', 'comment_title' => ':who ба паёми шумо шарҳ навишт', 'follow_title' => ':who ба шумо обуна шуд', 'report_title' => 'Шикояти нав', 'report_body' => 'Шикоят дар бораи: :type'],
        ];
        $text = ($dict[$locale] ?? $dict['ru'])[$key] ?? $key;
        foreach ($vars as $k => $v) {
            $text = str_replace(':'.$k, (string) $v, $text);
        }

        return trim($text);
    }
}
