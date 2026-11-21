# Social Network for Sitebill (`rumantic.social`)

A community on your own site: profiles, a feed with follows, posts with a photo, likes and one-level comments,
notifications through the platform channels, reports and moderation. The app **is** the site (owner mode), so it
installs on a clean (`blank`) Sitebill Community.

One code, two ways to ship it:

- **Sitebill cloud** — this repository is the tenant configuration `configs/social` (Nuxt layer in `web/`, Laravel module in `api/`).
- **Community marketplace** — `tools/pack-community` compiles the same code into the installable package `rumantic.social-<version>.sbapp`
  (tables `app_rumantic_social_*`, GraphQL names `Sc*`, abilities `sc.*`).

Design and rules: `docs/SOCIAL_APP.md` in the Sitebill monorepo. Requires Host API >= 1.4 (`Host::files()`).

## Install (Community)

```bash
curl -fsSL https://sitebillcms.com/install.sh | sh -s -- --profile blank --apps rumantic.social
```

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

MIT licensed.
