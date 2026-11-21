// Слой совместимости: SFC конфига пишутся под Nuxt (авто-импорты useI18n, useToast, useState…), а рантайм приложений Community
// даёт им только `host` (docs/APPS_SDK_PLAN.md §6.3). Здесь — минимальные аналоги нужных композаблов поверх host.
// `vue` в сборке подменяется на общий Vue хоста (shim/vue.mjs), поэтому реактивность общая с сайтом.
import { ref, reactive, computed } from 'vue'

let host = null
let appId = ''
export function setHost(h, id) { host = h; if (id) appId = id }
export const getHost = () => host

// ---------------------------------------------------------------- i18n
// Строки конфига — `social.*`; в пакете файлы локалей плоские (ключ целиком), host.t находит их по полному ключу.
function interpolate(text, params) {
  if (!params) return text
  const forms = text.split(' | ')
  if (forms.length > 1 && typeof params.n === 'number') {
    const n = params.n
    text = forms.length === 2 ? forms[n === 1 ? 0 : 1] : forms[n === 0 ? 0 : n === 1 ? 1 : 2] ?? forms[forms.length - 1]
  }
  return text.replace(/\{(\w+)\}/g, (m, k) => (params[k] !== undefined ? String(params[k]) : m))
}
export function useI18n() {
  return {
    t: (key, params) => interpolate(host.t(key), params),
    te: key => host.t(key) !== key,
    locale: host.locale,
  }
}

// ---------------------------------------------------------------- состояние, тосты, диалоги
const states = new Map()
export function useState(key, init) {
  if (!states.has(key)) states.set(key, ref(init ? init() : undefined))
  return states.get(key)
}
export const useToast = () => ({ add: ({ title, description, color }) => host.toast(title || description || '', color === 'error' ? 'error' : 'success') })
export const useConfirmDialog = () => ({ confirmDialog: async ({ title, description }) => globalThis.confirm([title, description].filter(Boolean).join('\n')) })
export const definePageMeta = () => {}
export const useSeoMeta = () => {}
export const useHead = () => {}

// ---------------------------------------------------------------- GraphQL
// gql-документы конфига приходят строками (shim/apollo.mjs); host.gql сам разбирает и отличает query от mutation.
export function useGqlClient() {
  return {
    query: async ({ query, variables }) => ({ data: await host.gql(String(query), variables) }),
    mutate: async ({ mutation, variables }) => ({ data: await host.gql(String(mutation), variables) }),
  }
}

// ---------------------------------------------------------------- маршрутизация
// Страницы приложения лежат по /admin/x/<id>/<страница>; в конфиге пути вида /admin/social/<страница>[/<id>].
// Динамический сегмент превращается в ?id=, потому что рантайм сопоставляет страницы по точному пути.
const loc = reactive({ search: typeof location === 'undefined' ? '' : location.search })
if (typeof window !== 'undefined') window.addEventListener('popstate', () => { loc.search = location.search })

/** Страница могла открыться через роутер хоста (pushState без popstate): адрес перечитываем при монтировании. */
export const syncLocation = () => { if (typeof location !== 'undefined') loc.search = location.search }

export function toAppUrl(path) {
  const m = String(path).match(/^\/admin\/content\/?([^/?#]*)(?:\/([^/?#]+))?(.*)$/)
  if (!m) return path
  const [, page, id, rest] = m
  const base = `/admin/x/${appId}${page ? '/' + page : ''}`
  return id ? `${base}?id=${encodeURIComponent(id)}${rest}` : base + rest
}
export function go(path, replace = false) {
  const url = toAppUrl(path)
  const samePage = typeof location !== 'undefined' && url.split('?')[0] === location.pathname
  if (samePage) {
    history[replace ? 'replaceState' : 'pushState']({}, '', url)
    loc.search = location.search
  } else if (host?.navigate) host.navigate(url)
  else location.assign(url)
}
export const useRoute = () => reactive({
  params: computed(() => ({ id: new URLSearchParams(loc.search).get('id') ?? '' })),
  query: computed(() => Object.fromEntries(new URLSearchParams(loc.search))),
})
export const useRouter = () => ({ push: p => go(p), replace: p => go(p, true) })
export const useLocalePath = () => p => host.localePath(p)
export const useAuthStore = () => ({ get logged() { return !!host.user }, get user() { return host.user } })
