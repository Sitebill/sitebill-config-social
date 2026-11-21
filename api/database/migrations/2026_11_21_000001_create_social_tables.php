<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Соцсеть (docs/SOCIAL_APP.md). Внешних ключей на таблицы ядра нет намеренно: та же миграция уходит в пакет
 * приложения (`app_rumantic_social_*`), где таблиц `users`/`tenants` как «чужих» трогать нельзя. Целостность — в коде.
 * Имена индексов заданы явно: в MySQL они ≤ 64 символов, а у пакета префикс таблицы длиннее.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_profiles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('user_id');
            $t->string('handle', 30);
            $t->string('bio', 500)->nullable();
            $t->string('website', 255)->nullable();
            $t->string('avatar_path', 255)->nullable();
            $t->unsignedInteger('followers_count')->default(0);
            $t->unsignedInteger('following_count')->default(0);
            $t->unsignedInteger('posts_count')->default(0);
            $t->boolean('is_banned')->default(false);
            $t->timestamps();
            $t->unique(['tenant_id', 'user_id'], 'sc_prof_user');
            $t->unique(['tenant_id', 'handle'], 'sc_prof_handle');
        });

        Schema::create('social_follows', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('follower_id');
            $t->unsignedBigInteger('followee_id');
            $t->timestamps();
            $t->unique(['tenant_id', 'follower_id', 'followee_id'], 'sc_fol_pair');
            $t->index(['tenant_id', 'followee_id'], 'sc_fol_followee');
        });

        Schema::create('social_posts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('user_id');
            $t->text('body');
            $t->string('image_path', 255)->nullable();
            $t->unsignedInteger('likes_count')->default(0);
            $t->unsignedInteger('comments_count')->default(0);
            // visible | hidden
            $t->string('status', 10)->default('visible');
            $t->timestamps();
            $t->index(['tenant_id', 'status', 'id'], 'sc_post_feed');
            $t->index(['tenant_id', 'user_id', 'id'], 'sc_post_author');
        });

        Schema::create('social_likes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('post_id');
            $t->unsignedBigInteger('user_id');
            $t->timestamps();
            $t->unique(['tenant_id', 'post_id', 'user_id'], 'sc_like_pair');
        });

        Schema::create('social_comments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('post_id');
            $t->unsignedBigInteger('user_id');
            $t->string('body', 1000);
            $t->string('status', 10)->default('visible');
            $t->timestamps();
            $t->index(['tenant_id', 'post_id', 'id'], 'sc_com_post');
        });

        Schema::create('social_reports', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            // post | comment | profile
            $t->string('target_type', 10);
            $t->unsignedBigInteger('target_id');
            $t->unsignedBigInteger('reporter_id');
            $t->string('reason', 500)->nullable();
            // open | resolved
            $t->string('status', 10)->default('open');
            $t->unsignedBigInteger('resolved_by')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'target_type', 'target_id', 'reporter_id'], 'sc_rep_once');
            $t->index(['tenant_id', 'status', 'id'], 'sc_rep_queue');
        });
    }

    public function down(): void
    {
        foreach (['reports', 'comments', 'likes', 'posts', 'follows', 'profiles'] as $name) {
            Schema::dropIfExists('social_'.$name);
        }
    }
};
