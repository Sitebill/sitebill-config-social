// Дымовой тест собранного web-бандла без браузера: монтирует каждую страницу в jsdom с поддельным host.
// Запуск: node tools/pack-community/smoke.mjs [путь к app.mjs]
import { createRequire } from 'node:module'
import path from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const here = path.dirname(fileURLToPath(import.meta.url))
const bundle = path.resolve(process.argv[2] ?? path.join(here, '.build/web/app.mjs'))
const req = createRequire(path.resolve(here, '../../../../apps/web/node_modules/x.js'))
const { JSDOM } = req('jsdom')
const dom = new JSDOM('<!doctype html><body><div id="app"></div></body>', { pretendToBeVisual: true, url: 'http://localhost/admin/x/rumantic.content-manager/plan' })
for (const k of ['window', 'document', 'navigator', 'Node', 'Element', 'HTMLElement', 'SVGElement', 'Event', 'location', 'history', 'localStorage']) {
  Object.defineProperty(globalThis, k, { value: k === 'window' ? dom.window : dom.window[k], configurable: true, writable: true })
}
globalThis.confirm = () => true
const Vue = req('vue')
globalThis.__SB_HOST__ = { vue: Vue }

const iso = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const day = n => { const d = new Date(); d.setDate(d.getDate() + n); return iso(d) }
const pains = JSON.stringify([{ id: 's1', title: 'Боли', pains: [{ id: 'p1', title: 'Нет клиентов', description: 'Ждут рекомендаций' }], questions: [{ id: 'q1', question: 'Цена?', answer: 'От 990' }] }])
const data = {
  contentProjects: [{ id: '1', name: 'Студия «Лён»', description: null, topic: 'интерьеры', audience: 'владельцы', tone: 'спокойный', keywords: 'дизайн', competitors: null, manager: 'Анна', color: '#10b981' }],
  contentPlatforms: [{ id: '1', project_id: 1, name: 'Telegram', kind: 'telegram', url: null, audience: null, formats: null, max_length: 1500, per_week: 4, api_status: 'manual' }],
  contentPublications: [{ id: '1', project_id: 1, parent_id: null, title: 'Пост про свет', kind: 'post', rubric: 'Советы', sphere: 'work', status: 'draft', priority: 'normal', planned_date: day(0), planned_time: '10:00', body: 'Текст', hashtags: '#a', link: null, assignee: 'Анна', comment: null, audience_id: 7, pain_ids: ['p1'], placements: [{ id: '1', platform_id: 1, status: 'todo', published_at: null, url: null, comment: null }], placement_state: 'pending' }],
  contentMaterials: [{ id: '7', project_id: null, kind: 'audience', entry_date: day(-1), state: null, sphere: 'work', topic: null, title: 'Владельцы квартир', body: 'Описание', tags: null, pains }],
  contentEvents: [],
  contentMyRole: 'owner',
  contentReview: [],
  contentMaterial: { id: '7', project_id: null, kind: 'audience', entry_date: day(-1), state: null, sphere: 'work', topic: null, title: 'Владельцы квартир', body: 'Описание', tags: null, pains },
}
const calls = []
const host = {
  locale: Vue.ref('ru'),
  t: k => k,
  toast: () => {},
  localePath: p => p,
  user: { id: 'u1', name: 'Админ' },
  components: {},
  async gql(query, vars) {
    const field = (query.match(/\{\s*(\w+)/) ?? [])[1]
    calls.push(field)
    if (!(field in data)) throw new Error('нет фикстуры для ' + field)
    return { [field]: data[field] }
  },
}

const mod = await import(pathToFileURL(bundle).href)
let failed = 0
const errors = []
for (const [name, Page] of [...Object.entries(mod.pages).map(([k, v]) => ['page ' + (k || '(dashboard)'), v]), ...Object.entries(mod.widgets).map(([k, v]) => ['widget ' + k, v])]) {
  document.getElementById('app').innerHTML = ''
  const app = Vue.createApp(Page, { host })
  app.config.errorHandler = (e) => errors.push(e)
  app.config.warnHandler = (m) => { if (!/Failed to resolve component|extraneous non-props/.test(m)) errors.push(new Error('warn: ' + m)) }
  app.mount('#app')
  await new Promise(r => setTimeout(r, 400))
  const html = document.getElementById('app').innerHTML
  const ok = html.length > 200 && !errors.length
  if (!ok) failed++
  console.log(ok ? 'ok  ' : 'FAIL', name, html.length + ' байт', errors.length ? errors.map(e => e.message).slice(0, 3).join(' | ') : '')
  errors.length = 0
  app.unmount()
}
// страница аудитории с ?id=: форма и конструктор болей (id идёт в query, а не в путь)
dom.window.history.pushState({}, '', '/admin/x/rumantic.content-manager/audiences?id=7')
document.getElementById('app').innerHTML = ''
const aud = Vue.createApp(mod.pages.audiences, { host })
aud.config.errorHandler = e => errors.push(e)
aud.mount('#app')
await new Promise(r => setTimeout(r, 600))
const rows = [...document.querySelectorAll('textarea, input')].map(e => e.value)
const okAud = !errors.length && rows.includes('Нет клиентов') && rows.includes('Цена?')
if (!okAud) failed++
console.log(okAud ? 'ok  ' : 'FAIL', 'audiences?id=7: боли и вопросы загружены в конструктор', errors.map(e => e.message).join(' | '))
console.log('GraphQL-операции:', [...new Set(calls)].join(', '))
process.exit(failed ? 1 : 0)
