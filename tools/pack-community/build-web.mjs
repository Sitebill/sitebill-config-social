// Прототип сборки web-части приложения Community из SFC конфига `social` (docs/SOCIAL_APP.md).
// Рантайм приложений грузит готовый ESM с общим Vue хоста и не умеет собирать .vue, поэтому компилируем их здесь:
// Vite (lib-режим) + plugin-vue + авто-импорты Nuxt, замещённые слоем shim/ поверх `host`.
//
// Запуск: node tools/pack-community/build-web.mjs [--app rumantic.social] [--out <каталог>]
// Зависимости (vite, plugin-vue, unplugin-*) берутся из apps/web/node_modules монорепозитория.
import { createRequire } from 'node:module'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const here = path.dirname(fileURLToPath(import.meta.url))
const configRoot = path.resolve(here, '../..')
const webApp = path.join(configRoot, 'web/app')
const arg = (name, def) => { const i = process.argv.indexOf('--' + name); return i > -1 ? process.argv[i + 1] : def }
const appId = arg('app', 'rumantic.social')
const outDir = path.resolve(arg('out', path.join(here, '.build/web')))
const nm = path.resolve(configRoot, '../../apps/web/node_modules')
const req = createRequire(path.join(nm, 'x.js'))
const load = async name => import(pathToFileURL(req.resolve(name)).href)

const { build } = await load('vite')
const vue = (await load('@vitejs/plugin-vue')).default
const AutoImport = (await load('unplugin-auto-import/vite')).default
const Components = (await load('unplugin-vue-components/vite')).default

const gqlNamesFile = path.join(path.dirname(outDir), 'gql-names.json')
if (!fs.existsSync(gqlNamesFile)) throw new Error('build-web: нет gql-names.json — сначала build-api.mjs')
const gqlNames = JSON.parse(fs.readFileSync(gqlNamesFile, 'utf8'))

const slash = p => p.replace(/\\/g, '/')
const work = path.join(here, '.build/work')
fs.mkdirSync(work, { recursive: true })

// ---- shim vue: всё, что экспортирует Vue, берём у хоста
const vueNames = Object.keys(req('vue')).filter(k => /^[A-Za-z_$][\w$]*$/.test(k) && k !== 'default')
fs.writeFileSync(path.join(work, 'vue.mjs'), `const V = globalThis.__SB_HOST__.vue\nexport default V\n${vueNames.map(k => `export const ${k} = V.${k}`).join('\n')}\n`)

const shimNuxt = slash(path.join(here, 'shim/nuxt.mjs'))
const shimComponents = slash(path.join(here, 'shim/components.mjs'))
const uiNames = ['UButton', 'UInput', 'UTextarea', 'UBadge', 'UIcon', 'UModal', 'UCard', 'USwitch', 'USelect', 'UFormField', 'UEditor', 'UEditorToolbar', 'NuxtLink', 'ClientOnly']

// ---- точка входа: страница админки и виджеты (главная сайта и дашборд) приложения
const P = slash(path.join(webApp, 'pages/admin/social'))
const C = slash(path.join(webApp, 'components/configs/social'))
fs.writeFileSync(path.join(work, 'entry.mjs'), `
import { defineComponent, h } from 'vue'
import { setHost, syncLocation } from '${shimNuxt}'
import AdminPanel from '${C}/AdminPanel.vue'
import AdminPage from '${P}/index.vue'
import Home from '${C}/Home.vue'

// Компонент получает host пропсом (контракт рантайма приложений); композаблы shim читают его из модуля.
const wrap = (name, component) => defineComponent({
  name,
  props: { host: { type: Object, required: true } },
  setup(props) {
    setHost(props.host, ${JSON.stringify(appId)})
    syncLocation()
    return () => h(component)
  },
})

export const pages = { '': wrap('ScAdminPage', AdminPage) }
export const widgets = { SocialHome: wrap('ScHome', Home), SocialDashboard: wrap('ScDashboard', AdminPanel) }
`)

await build({
  root: work,
  configFile: false,
  logLevel: 'info',
  resolve: {
    alias: [
      { find: /^vue$/, replacement: slash(path.join(work, 'vue.mjs')) },
      { find: '@apollo/client/core', replacement: slash(path.join(here, 'shim/apollo.mjs')) },
    ],
  },
  plugins: [
    // Имена GraphQL приложения свои (Sc*/sc*, см. build-api.mjs): правим запросы интерфейса до компиляции SFC.
    {
      name: 'app-gql-names',
      enforce: 'pre',
      transform(code, id) {
        if (!slash(id).toLowerCase().startsWith(slash(webApp).toLowerCase())) return null
        return code.replace(/\b(?:Social|social)[A-Z]\w*\b/g, m => gqlNames[m] ?? m)
      },
    },
    // Загрузка картинки в пакете идёт через host.rest: файл конфига заменяется оверлеем.
    {
      name: 'app-upload-overlay',
      enforce: 'pre',
      load(id) {
        if (!slash(id).endsWith('/composables/useSocialUpload.ts')) return null
        return fs.readFileSync(path.join(here, 'overlay/web/useSocialUpload.ts'), 'utf8').replace('__SHIM_NUXT__', shimNuxt)
      },
    },
    vue(),
    AutoImport({
      imports: ['vue', { [shimNuxt]: ['useI18n', 'useState', 'useToast', 'useConfirmDialog', 'definePageMeta', 'useSeoMeta', 'useHead', 'useGqlClient', 'useRoute', 'useRouter', 'useLocalePath', 'useAuthStore'] }],
      dirs: [slash(path.join(webApp, 'utils')), slash(path.join(webApp, 'composables'))],
      vueTemplate: true,
      dts: false,
    }),
    Components({
      dirs: [slash(path.join(webApp, 'components'))],
      directoryAsNamespace: true,
      resolvers: [name => (uiNames.includes(name) ? { name, from: shimComponents } : undefined)],
      dts: false,
    }),
  ],
  build: {
    outDir,
    emptyOutDir: true,
    minify: true,
    lib: { entry: slash(path.join(work, 'entry.mjs')), formats: ['es'], fileName: () => 'app.mjs' },
    rollupOptions: { output: { codeSplitting: false } },
  },
})
console.log('OK →', outDir)
