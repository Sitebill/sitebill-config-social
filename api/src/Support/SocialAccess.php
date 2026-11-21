<?php

namespace App\Configs\Social\Support;

use App\Configs\Social\Models\Profile;
use Illuminate\Contracts\Auth\Authenticatable;
use Sitebill\AppSdk\Host;

/**
 * Права соцсети — единственная точка (docs/SOCIAL_APP.md §Права). Проверяются из Gate-способностей провайдера,
 * поэтому решает бэкенд, а не меню фронта. Всё, что нужно от ядра, берётся через Host API: пакет приложения
 * получает этот класс без правок.
 */
final class SocialAccess
{
    public const MEMBER = 'social.member';

    public const MODERATE = 'social.moderate';

    /** @return list<string> */
    public static function abilities(): array
    {
        return [self::MEMBER, self::MODERATE];
    }

    public static function can(?Authenticatable $user, string $ability): bool
    {
        return match ($ability) {
            self::MODERATE => self::isModerator($user),
            self::MEMBER => self::isMember($user),
            default => false,
        };
    }

    public static function isModerator(?Authenticatable $user): bool
    {
        return Host::users()->isAdmin($user);
    }

    /** Вошедший пользователь ЭТОГО сайта (платформенный юзер и чужой тенант не проходят) и не забаненный. */
    public static function isMember(?Authenticatable $user): bool
    {
        $site = Host::site()->current();
        if (! $user || ! $site || (int) $user->tenant_id !== (int) $site->tenantId) {
            return false;
        }

        return ! self::isBanned((int) $user->getAuthIdentifier());
    }

    public static function isBanned(int $userId): bool
    {
        return Profile::query()->where('user_id', $userId)->where('is_banned', true)->exists();
    }

    /** Пользователь есть на этом сайте (Host::users()->names отдаёт только своих) и не забанен. */
    public static function isMemberId(int $userId): bool
    {
        return isset(Host::users()->names([$userId])[$userId]) && ! self::isBanned($userId);
    }
}
