<?php

use App\Configs\Social\Models\Post;
use App\Configs\Social\Models\Profile;
use App\Configs\Social\Models\Report;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Sitebill\AppSdk\Host;

// Соцсеть: права на бэкенде, лента, лайки/комментарии/подписки, жалобы, бан, изоляция тенантов (docs/SOCIAL_APP.md).

/** GraphQL под пользователем; graphQL() у тестового класса protected, поэтому вызываем в его контексте. */
function socialAs(object $test, ?User $user, string $query, array $vars = [])
{
    // actingAs() «залипает» на весь тест: для гостя пользователя guard'а надо сбросить явно.
    return (function () use ($user, $query, $vars) {
        $user ? $this->actingAs($user, 'web') : $this->app['auth']->guard('web')->forgetUser();

        return $this->graphQL($query, $vars);
    })->call($test);
}

function socialUser(string $name = 'Анна Орлова'): User
{
    return User::factory()->create(['name' => $name]);
}

beforeEach(function () {
    Notification::fake();
    Host::settings()->forget('rumantic.social', 'public_feed');
});

test('guests read the public feed but cannot write', function () {
    $anna = socialUser();
    socialAs($this, $anna, 'mutation { socialCreatePost(body: "Привет") { id } }')->assertJsonPath('data.socialCreatePost.id', fn ($v) => $v !== null);

    socialAs($this, null, '{ socialFeed { body author { handle } } }')->assertJsonPath('data.socialFeed.0.body', 'Привет');
    foreach ([
        'mutation { socialCreatePost(body: "x") { id } }',
        'mutation { socialToggleLike(post_id: 1) { liked } }',
        'mutation { socialAddComment(post_id: 1 body: "x") { id } }',
        'mutation { socialToggleFollow(user_id: 1) { following } }',
        'mutation { socialReport(target_type: "post" target_id: 1) }',
        'mutation { socialDeletePost(id: 1) }',
        'mutation { socialSaveProfile(input: {handle: "abc"}) { handle } }',
    ] as $q) {
        socialAs($this, null, $q)->assertGraphQLErrorMessage('Unauthenticated.');
    }
    // админские поля — только админу
    foreach (['{ socialAdminReports { id } }', '{ socialAdminMembers { handle } }', '{ socialAdminSettings { public_feed } }'] as $q) {
        socialAs($this, null, $q)->assertGraphQLErrorMessage('Unauthenticated.');
        socialAs($this, $anna, $q)->assertGraphQLErrorMessage('This action is unauthorized.');
    }
});

test('a closed feed asks guests to sign in but still tells the page why', function () {
    Host::settings()->set('rumantic.social', 'public_feed', '0');
    socialAs($this, null, '{ socialFeed { id } }')->assertGraphQLErrorMessage('Лента доступна только участникам.');
    socialAs($this, null, '{ socialConfig { public_feed can_post } }')->assertJsonPath('data.socialConfig.public_feed', false);
    socialAs($this, socialUser(), '{ socialFeed { id } }')->assertJsonPath('data.socialFeed', []);
});

test('posts, likes, comments and counters', function () {
    $anna = socialUser('Анна Орлова');
    $max = socialUser('Максим Белов');
    $id = (int) socialAs($this, $anna, 'mutation { socialCreatePost(body: "  Пост  ") { id body } }')->json('data.socialCreatePost.id');

    socialAs($this, $max, 'mutation ($id: Int!) { socialToggleLike(post_id: $id) { liked likes_count } }', ['id' => $id])
        ->assertJsonPath('data.socialToggleLike', ['liked' => true, 'likes_count' => 1]);
    socialAs($this, $max, 'mutation ($id: Int!) { socialToggleLike(post_id: $id) { liked likes_count } }', ['id' => $id])
        ->assertJsonPath('data.socialToggleLike', ['liked' => false, 'likes_count' => 0]);
    socialAs($this, $max, 'mutation ($id: Int!) { socialAddComment(post_id: $id body: "Класс") { body author { name } } }', ['id' => $id])
        ->assertJsonPath('data.socialAddComment.author.name', 'Максим Белов');
    socialAs($this, $max, 'mutation ($id: Int!) { socialAddComment(post_id: $id body: "   ") { id } }', ['id' => $id])->assertGraphQLValidationError('body', 'Пустой комментарий.');

    socialAs($this, $max, '{ socialFeed { comments_count liked_by_me can_delete } }')
        ->assertJsonPath('data.socialFeed.0', ['comments_count' => 1, 'liked_by_me' => false, 'can_delete' => false]);
    expect(Profile::query()->where('user_id', $anna->id)->value('posts_count'))->toBe(1);

    // чужой пост не удалить, свой — можно, счётчик падает
    socialAs($this, $max, 'mutation ($id: ID!) { socialDeletePost(id: $id) }', ['id' => $id])->assertGraphQLValidationError('id', 'Пост не найден.');
    socialAs($this, $anna, 'mutation ($id: ID!) { socialDeletePost(id: $id) }', ['id' => $id])->assertJsonPath('data.socialDeletePost', true);
    expect(Post::query()->count())->toBe(0)->and(Profile::query()->where('user_id', $anna->id)->value('posts_count'))->toBe(0);
});

test('following feeds, counters and no self follow', function () {
    $anna = socialUser('Анна Орлова');
    $max = socialUser('Максим Белов');
    $tim = socialUser('Тимур Рахимов');
    foreach ([[$anna, 'От Анны'], [$tim, 'От Тимура']] as [$u, $body]) {
        socialAs($this, $u, 'mutation ($b: String!) { socialCreatePost(body: $b) { id } }', ['b' => $body]);
    }

    socialAs($this, $max, 'mutation ($u: Int!) { socialToggleFollow(user_id: $u) { following followers_count } }', ['u' => $anna->id])
        ->assertJsonPath('data.socialToggleFollow', ['following' => true, 'followers_count' => 1]);
    socialAs($this, $max, '{ socialFeed(scope: FOLLOWING) { body } }')->assertJsonCount(1, 'data.socialFeed')->assertJsonPath('data.socialFeed.0.body', 'От Анны');
    socialAs($this, $max, '{ socialFeed(scope: ALL) { body } }')->assertJsonCount(2, 'data.socialFeed');
    socialAs($this, $max, 'mutation ($u: Int!) { socialToggleFollow(user_id: $u) { following } }', ['u' => $max->id])->assertGraphQLValidationError('user_id', 'Нельзя подписаться на себя.');
    // «Подписки» без входа — отказ, а не пустая лента
    socialAs($this, null, '{ socialFeed(scope: FOLLOWING) { id } }')->assertGraphQLErrorMessage('Unauthenticated.');
});

test('reports: one per member, moderator hides or bans, banned author disappears', function () {
    $admin = createAdminUser();
    $bad = socialUser('Спамер');
    $max = socialUser('Максим Белов');
    $id = (int) socialAs($this, $bad, 'mutation { socialCreatePost(body: "Купите!") { id } }')->json('data.socialCreatePost.id');

    $q = 'mutation ($id: Int!) { socialReport(target_type: "post" target_id: $id reason: "спам") }';
    socialAs($this, $max, $q, ['id' => $id])->assertJsonPath('data.socialReport', true);
    socialAs($this, $max, $q, ['id' => $id])->assertJsonPath('data.socialReport', true);
    expect(Report::query()->count())->toBe(1);

    socialAs($this, $max, '{ socialAdminReports { id } }')->assertGraphQLErrorMessage('This action is unauthorized.');
    $rid = socialAs($this, $admin, '{ socialAdminReports { id excerpt reporter_name } }')->assertJsonPath('data.socialAdminReports.0.excerpt', 'Купите!')->json('data.socialAdminReports.0.id');

    socialAs($this, $max, 'mutation ($id: ID!) { socialResolveReport(id: $id action: "ban") }', ['id' => $rid])->assertGraphQLErrorMessage('This action is unauthorized.');
    socialAs($this, $admin, 'mutation ($id: ID!) { socialResolveReport(id: $id action: "ban") }', ['id' => $rid])->assertJsonPath('data.socialResolveReport', true);

    expect(Post::query()->find($id)->status)->toBe('hidden')->and(Report::query()->first()->status)->toBe('resolved');
    socialAs($this, null, '{ socialFeed { id } }')->assertJsonPath('data.socialFeed', []);
    // забаненный не пишет
    socialAs($this, $bad, 'mutation { socialCreatePost(body: "ещё") { id } }')->assertGraphQLErrorMessage('This action is unauthorized.');
    // себя забанить нельзя
    socialAs($this, $admin, 'mutation ($u: Int!) { socialSetBanned(user_id: $u banned: true) { is_banned } }', ['u' => $admin->id])
        ->assertGraphQLValidationError('user_id', 'Себя забанить нельзя.');
});

test('images only from the app directory and a safe website', function () {
    $anna = socialUser();
    socialAs($this, $anna, 'mutation { socialCreatePost(body: "x" image_path: "avatars/foreign.png") { id } }')->assertGraphQLValidationError('image', 'Неверный путь картинки.');
    socialAs($this, $anna, 'mutation { socialSaveProfile(input: {handle: "anna_o" website: "javascript:alert(1)"}) { handle } }')
        ->assertGraphQLValidationError('website', 'Адрес сайта должен начинаться с http:// или https://');
    socialAs($this, $anna, 'mutation { socialSaveProfile(input: {handle: "anna_o" bio: "Привет" website: "https://anna.example"}) { handle website } }')
        ->assertJsonPath('data.socialSaveProfile', ['handle' => 'anna_o', 'website' => 'https://anna.example']);
    socialAs($this, socialUser('Другая'), 'mutation { socialSaveProfile(input: {handle: "anna_o"}) { handle } }')->assertGraphQLValidationError('handle', 'Этот ник занят.');
});

test('tenant isolation: another site sees nothing and its user is not a member', function () {
    $anna = socialUser();
    socialAs($this, $anna, 'mutation { socialCreatePost(body: "Только у нас") { id } }');

    $other = Tenant::create(['name' => 'Чужой', 'slug' => 'other-'.uniqid()]);
    $foreign = User::factory()->create(['tenant_id' => $other->id]);
    // пользователь другого сайта на этом сайте — не участник
    socialAs($this, $foreign, 'mutation { socialCreatePost(body: "x") { id } }')->assertGraphQLErrorMessage('This action is unauthorized.');

    $mine = app('currentTenant');
    app()->instance('currentTenant', $other);
    expect(Post::query()->count())->toBe(0)->and(Profile::query()->count())->toBe(0);
    app()->instance('currentTenant', $mine);
    expect(Post::query()->count())->toBe(1);
});

test('demo data is idempotent and the recount command repairs counters', function () {
    \App\Configs\Social\Demo\SocialDemo::seed();
    $posts = Post::query()->count();
    \App\Configs\Social\Demo\SocialDemo::seed();
    expect(Post::query()->count())->toBe($posts)->and($posts)->toBeGreaterThan(0);

    Post::query()->update(['likes_count' => 99]);
    $this->artisan('rumantic-social:recount')->assertSuccessful();
    expect(Post::query()->where('likes_count', 99)->count())->toBe(0);
});
