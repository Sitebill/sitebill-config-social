# Social Network for Sitebill — profiles, feed, follows, likes, comments and moderation

**EN** · An open-source **social network / community engine** for the [Sitebill](https://sitebillcms.com/?utm_source=github&utm_medium=readme&utm_campaign=social) platform. Run a club, school, neighbourhood or fan community on **your own domain**: sign-up and login, **member profiles** (@handle, bio, avatar, website), a **feed** with «Following» and «Everyone» tabs, **posts with a photo**, **likes**, **one-level comments**, **follow / unfollow**, people search, notifications through the platform channels (bell, Telegram, e-mail), **reports and moderation** (hide a post, ban a member, per-user daily limits). The app **is** the site (owner mode) and installs on a clean Sitebill Community in one command. Self-hosted, no ads, no tracking, your data stays on your server.

**RU** · Открытая **социальная сеть для своего сайта** на платформе [Sitebill](https://www.sitebill.ru/?utm_source=github&utm_medium=readme&utm_campaign=social): клуб, школа, район, фан-сообщество — на **вашем домене**. Регистрация и вход, **профили участников** (@ник, о себе, аватар, сайт), **лента** с вкладками «Подписки» и «Все», **посты с фотографией**, **лайки**, **комментарии**, **подписки**, поиск людей, уведомления через каналы платформы (колокольчик, Telegram, e-mail), **жалобы и модерация** (скрыть пост, забанить участника, суточные лимиты). Приложение — это сам сайт (режим owner), ставится на чистый Sitebill Community одной командой. Своя установка, без рекламы и слежки, данные остаются на вашем сервере.

[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Sitebill](https://img.shields.io/badge/platform-Sitebill-1abc9c.svg)](https://sitebillcms.com/?utm_source=github&utm_medium=readme&utm_campaign=social)
![Laravel](https://img.shields.io/badge/Laravel-GraphQL-ff2d20.svg)
![Nuxt](https://img.shields.io/badge/Nuxt-4-00dc82.svg)
![Host API](https://img.shields.io/badge/Host%20API-%E2%89%A5%201.4-orange.svg)
![i18n](https://img.shields.io/badge/i18n-ru%20%C2%B7%20en%20%C2%B7%20sr%20%C2%B7%20tg-blue.svg)

## Who is it for / Для кого

- **Community owners** — hobby clubs, schools and courses, residential complexes, local groups, fan clubs, alumni — who want their own social network instead of a chat or a closed platform.
- **Developers** — a complete, readable example of a Sitebill app: GraphQL, multi-tenant data, moderation rights, image upload through the Host API.
- **Владельцы сообществ**: клубы по интересам, школы и курсы, жилые комплексы, районные и тематические группы, фан-клубы — кому нужна своя соцсеть вместо чата или чужой закрытой платформы.
- **Разработчики**: законченный и понятный пример приложения Sitebill — GraphQL, мультитенантные данные, права модерации, загрузка картинок через Host API.

## Features / Возможности

| | |
|---|---|
| 👤 **Profiles** | unique @handle, name, bio, website (http/https only), avatar, counters of posts / followers / following |
| 📰 **Feed** | «Following» and «Everyone» tabs, cursor pagination, guests can read the public feed (switchable) |
| ✍️ **Posts** | text up to 2000 characters + one photo (jpg / png / webp / gif, up to 5 MB); delete your own post |
| ❤️ **Likes & 💬 comments** | one like per member, one-level comments, counters kept in sync (`rumantic-social:recount` repairs them) |
| ➕ **Follows & people search** | follow / unfollow, followers and following lists, search by name and @handle |
| 🔔 **Notifications** | like, comment, new follower — through the platform channels; admins are notified about new reports |
| 🛡️ **Moderation** | report a post, comment or profile once per member; queue in the admin: dismiss, hide the post, ban the author; ban / unban members; daily limits for posts and comments |
| 🌍 **4 languages** | ru · en · sr · tg |
| 🔒 **Safe by design** | every query is scoped to the site (`tenant_id`), all writes require a signed-in non-banned member, admin actions are checked on the backend, text is rendered as text (no HTML), uploaded images are sniffed by content |

## Install (Community) / Установка

```bash
curl -fsSL https://sitebillcms.com/install.sh | sh -s -- --profile blank --apps rumantic.social
```

Needs Sitebill Community **1.6.4 or newer** (Host API 1.4). Demo posts: `php artisan sc:seed-demo`.
Нужен Sitebill Community **1.6.4 и новее** (Host API 1.4). Демо-данные: `php artisan sc:seed-demo`.

## Open source, two ways to ship it

One code, two ways to ship it:

- **Sitebill cloud** — this repository is the tenant configuration `configs/social` (Nuxt layer in `web/`, Laravel module in `api/`).
- **Community marketplace** — `tools/pack-community` compiles the same code into the installable package `rumantic.social-<version>.sbapp`
  (tables `app_rumantic_social_*`, GraphQL names `Sc*`, abilities `sc.*`).

Design and rules: `docs/SOCIAL_APP.md` in the [Sitebill monorepo](https://github.com/Sitebill). Requires Host API ≥ 1.4 (`Host::files()`).

## Build the package

```bash
node tools/pack-community/pack.mjs --version 0.1.0        # -> tools/pack-community/.build/app
cd tools/pack-community/.build/app
npx --package @sitebill/app-sdk sitebill-app check        # no errors, no warnings
npx --package @sitebill/app-sdk sitebill-app pack --out ../dist
```

## Console

| Command | What |
|---|---|
| `social:seed-demo` (cloud) / `sc:seed-demo` (app) | sample posts, likes, comments, follows |
| `rumantic-social:recount` | recount denormalised counters |

## Limits of v0.1

No direct messages, groups, hashtags, reposts, video, replies to comments or e-mail digests. In the package a moderator
cannot ban another administrator (the app does not see other users' roles) — only themselves are protected.
Нет личных сообщений, групп, хэштегов, репостов, видео, ответов на комментарии и e-mail-дайджеста.

## Links / Ссылки

- Platform / платформа: [sitebillcms.com](https://sitebillcms.com/?utm_source=github&utm_medium=readme&utm_campaign=social) · [sitebill.ru](https://www.sitebill.ru/?utm_source=github&utm_medium=readme&utm_campaign=social)
- Other open Sitebill apps: [Content Factory](https://github.com/Sitebill/sitebill-config-content)

## License

[MIT](LICENSE)
