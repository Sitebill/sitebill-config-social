<?php

namespace App\Configs\Social\GraphQL\Resolvers;

use App\Configs\Social\Models\Comment;
use App\Configs\Social\Models\Follow;
use App\Configs\Social\Models\Like;
use App\Configs\Social\Models\Post;
use App\Configs\Social\Models\Profile;
use App\Configs\Social\Models\Report;
use App\Configs\Social\Support\SocialAccess;
use App\Configs\Social\Support\SocialService;
use App\Configs\Social\Support\SocialSettings;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Sitebill\AppSdk\Host;

/**
 * GraphQL соцсети. Публичные запросы (лента, профили, комментарии) отдают только `visible` и не требуют входа, если
 * включена настройка «публичная лента»; права на запись и модерацию — в схеме (`social.member`, `social.moderate`).
 * Каждый ответ собирается в массив (viewer-зависимые поля liked_by_me/followed_by_me), а не в модель.
 */
final class SocialResolver
{
    private const MAX = 50;

    // ── чтение (публичное) ────────────────────────────────────────────────

    public function config(): array
    {
        $me = Auth::user();

        return [
            'public_feed' => SocialSettings::publicFeed(),
            'can_post' => SocialAccess::isMember($me),
            'is_moderator' => SocialAccess::isModerator($me),
            'me' => $me && SocialAccess::isMember($me) ? $this->profile(SocialService::ensureProfile($me), $me) : null,
        ];
    }

    public function feed(mixed $root, array $args): array
    {
        $viewer = $this->viewer();
        $q = Post::query()->where('status', 'visible')->whereNotIn('user_id', $this->bannedIds())->orderByDesc('id');
        if (($args['scope'] ?? 'ALL') === 'FOLLOWING') {
            if (! $viewer) {
                throw new AuthenticationException('Unauthenticated.');
            }
            $q->whereIn('user_id', Follow::query()->where('follower_id', $viewer->id)->pluck('followee_id')->push($viewer->id));
        }

        return $this->posts($q, $args, $viewer);
    }

    public function post(mixed $root, array $args): ?array
    {
        $viewer = $this->viewer();
        $post = Post::query()->where('status', 'visible')->whereNotIn('user_id', $this->bannedIds())->find($args['id']);

        return $post ? $this->present([$post], $viewer)[0] : null;
    }

    public function userPosts(mixed $root, array $args): array
    {
        $viewer = $this->viewer();
        if (in_array((int) $args['user_id'], $this->bannedIds(), true)) {
            return [];
        }
        $q = Post::query()->where('status', 'visible')->where('user_id', $args['user_id'])->orderByDesc('id');

        return $this->posts($q, $args, $viewer);
    }

    public function comments(mixed $root, array $args): array
    {
        $viewer = $this->viewer();
        $rows = Comment::query()->where('post_id', $args['post_id'])->where('status', 'visible')
            ->whereNotIn('user_id', $this->bannedIds())->orderBy('id')->limit(self::MAX * 4)->get();
        $profiles = $this->profilesOf($rows->pluck('user_id')->all(), $viewer);

        return $rows->map(fn (Comment $c) => [
            'id' => $c->id, 'post_id' => $c->post_id, 'body' => $c->body, 'created_at' => (string) $c->created_at,
            'author' => $profiles[$c->user_id] ?? null,
            'can_delete' => $viewer && ((int) $viewer->getAuthIdentifier() === (int) $c->user_id || SocialAccess::isModerator($viewer)),
        ])->filter(fn ($c) => $c['author'] !== null)->values()->all();
    }

    public function profileByHandle(mixed $root, array $args): ?array
    {
        $viewer = $this->viewer();
        $p = Profile::query()->where('handle', strtolower((string) $args['handle']))->where('is_banned', false)->first();

        return $p ? $this->profile($p, $viewer) : null;
    }

    public function people(mixed $root, array $args): array
    {
        $viewer = $this->viewer();
        $q = Profile::query()->where('is_banned', false)->orderByDesc('followers_count')->orderBy('id')->limit($this->limit($args));
        $term = trim((string) ($args['query'] ?? ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $q->where(fn ($w) => $w->where('handle', 'like', $like)->orWhereIn('user_id', $this->idsByName($term)));
        }

        return $this->profiles($q->get(), $viewer);
    }

    /** Подписчики (`FOLLOWERS`) или подписки (`FOLLOWING`) пользователя. */
    public function connections(mixed $root, array $args): array
    {
        $viewer = $this->viewer();
        $followers = ($args['kind'] ?? 'FOLLOWERS') === 'FOLLOWERS';
        $ids = Follow::query()->where($followers ? 'followee_id' : 'follower_id', $args['user_id'])->orderByDesc('id')->limit($this->limit($args))
            ->pluck($followers ? 'follower_id' : 'followee_id');

        return $this->profiles(Profile::query()->whereIn('user_id', $ids)->where('is_banned', false)->get(), $viewer);
    }

    // ── запись (social.member) ────────────────────────────────────────────

    public function saveProfile(mixed $root, array $args): array
    {
        $me = $this->me();

        return $this->profile(SocialService::saveProfile($me, $args['input']), $me);
    }

    public function createPost(mixed $root, array $args): array
    {
        $me = $this->me();
        $post = SocialService::createPost($me, (string) $args['body'], $args['image_path'] ?? null);

        return $this->present([$post], $me)[0];
    }

    public function deletePost(mixed $root, array $args): bool
    {
        SocialService::deletePost($this->me(), (int) $args['id']);

        return true;
    }

    public function toggleLike(mixed $root, array $args): array
    {
        return SocialService::toggleLike($this->me(), (int) $args['post_id']);
    }

    public function addComment(mixed $root, array $args): array
    {
        $me = $this->me();
        $c = SocialService::addComment($me, (int) $args['post_id'], (string) $args['body']);
        $author = $this->profilesOf([$me->id], $me)[(int) $me->id] ?? null;

        return ['id' => $c->id, 'post_id' => $c->post_id, 'body' => $c->body, 'created_at' => (string) $c->created_at, 'author' => $author, 'can_delete' => true];
    }

    public function deleteComment(mixed $root, array $args): bool
    {
        SocialService::deleteComment($this->me(), (int) $args['id']);

        return true;
    }

    public function toggleFollow(mixed $root, array $args): array
    {
        $me = $this->me();
        $following = SocialService::toggleFollow($me, (int) $args['user_id']);
        $theirs = Profile::query()->where('user_id', $args['user_id'])->first();

        return ['following' => $following, 'followers_count' => (int) ($theirs?->followers_count ?? 0)];
    }

    public function report(mixed $root, array $args): bool
    {
        SocialService::report($this->me(), (string) $args['target_type'], (int) $args['target_id'], $args['reason'] ?? null);

        return true;
    }

    // ── модерация (social.moderate) ───────────────────────────────────────

    public function adminReports(mixed $root, array $args): array
    {
        $rows = Report::query()->where('status', $args['status'] ?? 'open')->orderByDesc('id')->limit($this->limit($args))->get();
        $names = Host::users()->names($rows->pluck('reporter_id')->all());

        return $rows->map(fn (Report $r) => [
            'id' => $r->id, 'target_type' => $r->target_type, 'target_id' => $r->target_id,
            'reporter_name' => $names[$r->reporter_id] ?? '', 'reason' => $r->reason, 'status' => $r->status,
            'created_at' => (string) $r->created_at, 'excerpt' => $this->excerpt($r),
        ])->all();
    }

    public function resolveReport(mixed $root, array $args): bool
    {
        SocialService::resolveReport($this->me(), (int) $args['id'], (string) $args['action']);

        return true;
    }

    public function adminMembers(mixed $root, array $args): array
    {
        $q = Profile::query()->orderByDesc('id')->limit($this->limit($args));
        $term = trim((string) ($args['query'] ?? ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $q->where(fn ($w) => $w->where('handle', 'like', $like)->orWhereIn('user_id', $this->idsByName($term)));
        }

        return $this->profiles($q->get(), null);
    }

    public function setBanned(mixed $root, array $args): array
    {
        return $this->profile(SocialService::setBanned($this->me(), (int) $args['user_id'], (bool) $args['banned']), null);
    }

    public function adminSettings(): array
    {
        return SocialSettings::all();
    }

    public function saveSettings(mixed $root, array $args): array
    {
        SocialSettings::save($args['input']);

        return SocialSettings::all();
    }

    // ── сборка ответов ────────────────────────────────────────────────────

    /** @return list<int> пользователи сайта по имени или e-mail (Host API, а не прямой запрос к таблице users) */
    private function idsByName(string $term): array
    {
        return array_column(Host::users()->search($term, 100), 'id');
    }

    private function viewer(): ?Authenticatable
    {
        $user = Auth::user();
        if (! $user || ! SocialAccess::isMember($user)) {
            // Гость читает только при включённой публичной ленте; вошедший, но чужой/забаненный — как гость.
            if (! SocialSettings::publicFeed()) {
                throw new AuthenticationException('Лента доступна только участникам.');
            }

            return null;
        }

        return $user;
    }

    private function me(): Authenticatable
    {
        return Auth::user();
    }

    private function limit(array $args): int
    {
        return max(1, min(self::MAX, (int) ($args['limit'] ?? 20)));
    }

    /** @return list<int> */
    private function bannedIds(): array
    {
        return Profile::query()->where('is_banned', true)->pluck('user_id')->map(fn ($i) => (int) $i)->all();
    }

    private function posts($query, array $args, ?Authenticatable $viewer): array
    {
        if (! empty($args['after_id'])) {
            $query->where('id', '<', $args['after_id']);
        }

        return $this->present($query->limit($this->limit($args))->get()->all(), $viewer);
    }

    /** @param  list<Post>  $posts */
    private function present(array $posts, ?Authenticatable $viewer): array
    {
        $ids = array_map(fn ($p) => $p->id, $posts);
        $liked = $viewer && $ids ? Like::query()->where('user_id', $viewer->id)->whereIn('post_id', $ids)->pluck('post_id')->all() : [];
        $profiles = $this->profilesOf(array_map(fn ($p) => $p->user_id, $posts), $viewer);

        return array_values(array_filter(array_map(fn (Post $p) => ! isset($profiles[$p->user_id]) ? null : [
            'id' => $p->id, 'body' => $p->body, 'image_url' => Host::files()->url(SocialService::APP_ID, $p->image_path),
            'likes_count' => (int) $p->likes_count, 'comments_count' => (int) $p->comments_count, 'created_at' => (string) $p->created_at,
            'liked_by_me' => in_array($p->id, $liked, true), 'author' => $profiles[$p->user_id],
            'can_delete' => $viewer && ((int) $viewer->getAuthIdentifier() === (int) $p->user_id || SocialAccess::isModerator($viewer)),
        ], $posts)));
    }

    /** @param  list<int>  $userIds @return array<int, array> user_id → профиль */
    private function profilesOf(array $userIds, ?Authenticatable $viewer): array
    {
        $rows = Profile::query()->whereIn('user_id', array_unique($userIds))->get();

        return collect($this->profiles($rows, $viewer))->keyBy('user_id')->all();
    }

    /** @return list<array> */
    private function profiles($rows, ?Authenticatable $viewer): array
    {
        $names = Host::users()->names($rows->pluck('user_id')->all());
        $following = $viewer && $rows->isNotEmpty()
            ? Follow::query()->where('follower_id', $viewer->id)->whereIn('followee_id', $rows->pluck('user_id'))->pluck('followee_id')->all() : [];

        return $rows->map(fn (Profile $p) => $this->shape($p, $names[$p->user_id] ?? '', $viewer, in_array($p->user_id, $following, true)))->values()->all();
    }

    private function profile(Profile $p, ?Authenticatable $viewer): array
    {
        return $this->profiles(collect([$p]), $viewer)[0];
    }

    private function shape(Profile $p, string $name, ?Authenticatable $viewer, bool $followed): array
    {
        return [
            'user_id' => $p->user_id, 'handle' => $p->handle, 'name' => $name, 'bio' => $p->bio, 'website' => $p->website,
            'avatar_url' => Host::files()->url(SocialService::APP_ID, $p->avatar_path),
            'followers_count' => (int) $p->followers_count, 'following_count' => (int) $p->following_count, 'posts_count' => (int) $p->posts_count,
            'is_banned' => (bool) $p->is_banned, 'followed_by_me' => $followed, 'is_me' => $viewer && (int) $viewer->getAuthIdentifier() === (int) $p->user_id,
        ];
    }

    private function excerpt(Report $r): string
    {
        $text = match ($r->target_type) {
            'post' => Post::query()->find($r->target_id)?->body,
            'comment' => Comment::query()->find($r->target_id)?->body,
            default => '@'.(Profile::query()->where('user_id', $r->target_id)->value('handle') ?? ''),
        };

        return mb_substr((string) $text, 0, 200);
    }
}
