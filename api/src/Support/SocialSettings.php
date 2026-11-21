<?php

namespace App\Configs\Social\Support;

use Sitebill\AppSdk\Host;

/** Настройки сайта (Host::settings, на тенанта). Значения хранятся строками, поэтому приводим типы здесь. */
final class SocialSettings
{
    public const APP_ID = 'rumantic.social';

    private const DEFAULTS = ['public_feed' => true, 'posts_per_day' => 50, 'comments_per_day' => 200];

    public static function publicFeed(): bool
    {
        return filter_var(Host::settings()->get(self::APP_ID, 'public_feed', self::DEFAULTS['public_feed']), FILTER_VALIDATE_BOOL);
    }

    public static function postsPerDay(): int
    {
        return max(1, (int) Host::settings()->get(self::APP_ID, 'posts_per_day', self::DEFAULTS['posts_per_day']));
    }

    public static function commentsPerDay(): int
    {
        return max(1, (int) Host::settings()->get(self::APP_ID, 'comments_per_day', self::DEFAULTS['comments_per_day']));
    }

    /** @return array{public_feed: bool, posts_per_day: int, comments_per_day: int} */
    public static function all(): array
    {
        return ['public_feed' => self::publicFeed(), 'posts_per_day' => self::postsPerDay(), 'comments_per_day' => self::commentsPerDay()];
    }

    public static function save(array $v): void
    {
        foreach (array_intersect_key($v, self::DEFAULTS) as $key => $value) {
            Host::settings()->set(self::APP_ID, $key, is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }
    }
}
