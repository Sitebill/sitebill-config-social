// Сборка пакета .sbapp приложения rumantic.social из конфигурации `social` (docs/SOCIAL_APP.md).
// Шаги: api (бэкенд конфига + оверлеи), web (SFC → ESM), css (utilities под SFC), локали (плоские ключи), manifest.
// Скриншотов в пакете нет: портал принимает запрос до ~1 МБ.
//
// Запуск: node tools/pack-community/pack.mjs [--version 0.1.0] [--out <каталог>]
import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const here = path.dirname(fileURLToPath(import.meta.url))
const configRoot = path.resolve(here, '../..')
const arg = (name, def) => { const i = process.argv.indexOf('--' + name); return i > -1 ? process.argv[i + 1] : def }
const version = arg('version', '0.1.0')
const outRoot = path.resolve(arg('out', path.join(here, '.build')))
const APP_ID = 'rumantic.social'
const pkgDir = path.join(outRoot, 'app')
const run = script => execFileSync(process.execPath, [path.join(here, script), '--out', path.join(outRoot, script === 'build-css.mjs' ? 'app.css' : script === 'build-web.mjs' ? 'web' : 'api')], { stdio: 'inherit' })

run('build-api.mjs') // первым: даёт gql-names.json для web
run('build-web.mjs')
run('build-css.mjs')

fs.rmSync(pkgDir, { recursive: true, force: true })

// ---- api
fs.cpSync(path.join(outRoot, 'api'), path.join(pkgDir, 'api'), { recursive: true })

// ---- web: бандл + стили, вставляемые при загрузке модуля (один раз на страницу)
const css = fs.readFileSync(path.join(outRoot, 'app.css'), 'utf8')
let js = fs.readFileSync(path.join(outRoot, 'web/app.mjs'), 'utf8')
js += `\n;(() => {\n  if (typeof document === 'undefined' || document.getElementById('app-${APP_ID}-css')) return\n  const s = document.createElement('style')\n  s.id = 'app-${APP_ID}-css'\n  s.textContent = ${JSON.stringify(css)}\n  document.head.appendChild(s)\n})()\n`
fs.mkdirSync(path.join(pkgDir, 'web'), { recursive: true })
fs.writeFileSync(path.join(pkgDir, 'web/app.mjs'), js)

// ---- локали: в конфиге вложенные `social.*`, приложение ищет по полному ключу
const flat = (o, p = '') => Object.entries(o).flatMap(([k, v]) => v && typeof v === 'object' ? flat(v, p + k + '.') : [[p + k, v]])
fs.mkdirSync(path.join(pkgDir, 'locales'), { recursive: true })
for (const loc of ['ru', 'en', 'sr', 'tg']) {
  const src = JSON.parse(fs.readFileSync(path.join(configRoot, `web/locales/${loc}.json`), 'utf8'))
  fs.writeFileSync(path.join(pkgDir, `locales/${loc}.json`), JSON.stringify(Object.fromEntries(flat(src)), null, 2) + '\n')
}

// ---- manifest
const L = (ru, en, sr, tg) => ({ ru, en, sr, tg })
const manifest = {
  id: APP_ID,
  name: L('Социальная сеть', 'Social Network', 'Društvena mreža', 'Шабакаи иҷтимоӣ'),
  version,
  vendor: { name: 'Lumen', url: 'https://sitebillcms.com/' },
  license: 'MIT',
  repository: 'https://github.com/Sitebill/sitebill-config-social',
  description: {
    ru: 'Сообщество на вашем сайте: профили, лента и подписки, посты с фото, лайки и комментарии, уведомления, жалобы и модерация. Приложение целиком заменяет сайт.',
    en: 'A community on your own site: profiles, a feed and follows, posts with photos, likes and comments, notifications, reports and moderation. The app takes over the whole site.',
  },
  sdk: { php: '1', web: '1' },
  requires: { host: '>=1.4' },
  provider: 'SitebillApps\\Rumantic\\Social\\SocialServiceProvider',
  permissions: [],
  profiles: ['blank'],
  shell: {
    mode: 'owner',
    home: 'SocialHome',
    adminDashboard: 'SocialDashboard',
    adminMenu: 'replace',
    accountMenu: 'replace',
    publicNav: [{ label: L('Лента', 'Feed', 'Tok', 'Лента'), to: '/' }],
  },
  admin: {
    menu: [{ label: L('Модерация', 'Moderation', 'Moderacija', 'Назорат'), icon: 'i-lucide-shield-check', path: '' }],
    pages: [''],
    widgets: { 'admin.dashboard': 'SocialDashboard' },
  },
  web: { admin: 'web/app.mjs', site: 'web/app.mjs' },
}
fs.writeFileSync(path.join(pkgDir, 'manifest.json'), JSON.stringify(manifest, null, 2) + '\n')

// ---- прочее
fs.writeFileSync(path.join(pkgDir, 'composer.json'), JSON.stringify({
  name: 'rumantic/sitebill-app-social',
  description: 'Social network: an installable Sitebill app (profiles, feed, follows, likes, comments, moderation).',
  type: 'project', license: 'MIT', require: { php: '>=8.4' },
  autoload: { 'psr-4': { 'SitebillApps\\Rumantic\\Social\\': 'api/src/' } },
}, null, 4) + '\n')
const license = path.join(configRoot, 'LICENSE')
fs.writeFileSync(path.join(pkgDir, 'LICENSE'), fs.existsSync(license) ? fs.readFileSync(license) : 'MIT License\n')
fs.writeFileSync(path.join(pkgDir, 'README.md'), `# Social Network (rumantic.social ${version})\n\nСобрано из конфигурации \`social\` (Sitebill/sitebill-config-social) инструментом tools/pack-community.\nТребует Host API >= 1.4 (Host::files()). Модерация — администратор сайта.\n`)

console.log(`пакет собран: ${pkgDir}`)
