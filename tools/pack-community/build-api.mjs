// Сборка api-части приложения Community из бэкенда конфига `social` (docs/SOCIAL_APP.md).
// Исходник конфига не правится: здесь он копируется в пространство имён приложения, а то, что завязано на ядро
// (провайдер, демо, консольные команды по всем сайтам), заменяется оверлеями из overlay/api/.
//
// Запуск: node tools/pack-community/build-api.mjs [--out <каталог>]
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const here = path.dirname(fileURLToPath(import.meta.url))
const configRoot = path.resolve(here, '../..')
const arg = (name, def) => { const i = process.argv.indexOf('--' + name); return i > -1 ? process.argv[i + 1] : def }
const outDir = path.resolve(arg('out', path.join(here, '.build/api')))

const NS = 'SitebillApps\\Rumantic\\Social'
const NS_GQL = NS.replaceAll('\\', '\\\\') // в SDL обратные слэши удвоены
const PREFIX = 'app_rumantic_social_'
const TABLES = 'profiles|follows|posts|likes|comments|reports'

// Заменяются оверлеями: провайдер (Gate, команды), демо (создаёт пользователей — приложению нельзя), команды по всем сайтам.
const SKIP = [/^Demo\//, /^SocialConfigServiceProvider\.php$/, /^Console\//]

const walk = (dir, base = dir) => fs.readdirSync(dir, { withFileTypes: true }).flatMap(e =>
  e.isDirectory() ? walk(path.join(dir, e.name), base) : [path.relative(base, path.join(dir, e.name)).replaceAll('\\', '/')])

// Правило должно сработать хотя бы раз во всём пакете, иначе исходник конфига изменился и правило устарело.
const hits = new Map()
const rule = (name, re, to) => ({ name, re, to })
function apply(text, rules) {
  for (const r of rules) {
    const next = text.replace(r.re, r.to)
    if (next !== text) hits.set(r.name, (hits.get(r.name) ?? 0) + 1)
    text = next
  }
  return text
}

const phpRules = [
  rule('ns', /App\\Configs\\Social/g, () => NS),
  rule('tenant-trait-use', /^use App\\Models\\Concerns\\BelongsToTenant;\n/m, ''),
  rule('tenant-trait', /^[ \t]*use BelongsToTenant;\n\n?/m, ''),
  rule('model-import', /^use Illuminate\\Database\\Eloquent\\Model;/m, 'use Sitebill\\AppSdk\\AppModel;'),
  rule('model-extends', /\bextends Model\b/, 'extends AppModel'),
  // Способности Gate приложения свои (`sc.*`): конфиг `social` платформы определяет `social.*`, и они не должны пересекаться.
  rule('abilities', /'social\.(member|moderate)'/g, "'sc.$1'"),
  rule('tables', new RegExp(`'social_(${TABLES})\\b`, 'g'), `'${PREFIX}$1`),
  rule('tables-down', /'social_'\.\$name/g, `'${PREFIX}'.$name`),
]
const gqlRules = [
  rule('gql-ns', /App\\\\Configs\\\\Social\\\\/g, () => NS_GQL + '\\\\'),
]

fs.rmSync(outDir, { recursive: true, force: true })
const srcRoot = path.join(configRoot, 'api/src')
const put = (rel, text) => { const dest = path.join(outDir, rel); fs.mkdirSync(path.dirname(dest), { recursive: true }); fs.writeFileSync(dest, text) }
const read = f => fs.readFileSync(f, 'utf8').replaceAll('\r\n', '\n')

for (const rel of walk(srcRoot)) {
  if (SKIP.some(re => re.test(rel))) continue
  put(path.join('src', rel), apply(read(path.join(srcRoot, rel)), phpRules))
}
// Миграции и REST — те же файлы конфига (код один), с переименованием таблиц.
for (const dir of ['database/migrations', 'routes']) {
  const root = path.join(configRoot, 'api', dir)
  // Имя миграции у приложения своё: у конфига платформы то же имя, и мигратор счёл бы миграцию уже выполненной (таблиц app_* не будет).
  for (const rel of walk(root)) put(path.join(dir, rel.replace('create_social_tables', 'create_rumantic_social_app_tables')), apply(read(path.join(root, rel)), phpRules))
}

// Оверлеи ложатся поверх: заменяют исходные файлы и добавляют новые.
const overlay = path.join(here, 'overlay/api')
for (const rel of walk(overlay)) {
  const dest = path.join(outDir, rel)
  fs.mkdirSync(path.dirname(dest), { recursive: true })
  fs.copyFileSync(path.join(overlay, rel), dest)
}

// GraphQL-схема.
let schema = apply(read(path.join(configRoot, 'api/graphql/social.graphql')), gqlRules)
schema = schema.replace(/"social\.(member|moderate)"/g, '"sc.$1"')
// Конфиг `social` всегда загружен в той же схеме (платформа и образ Community), поэтому имена приложения свои:
// Social* → Sc*, social* → sc*. Строки с классами резолверов (после обратного слэша) не трогаем.
const names = {}
schema = schema.replace(/(?<![\\\w])(Social|social)([A-Z]\w*)\b/g, (m, head, tail) => (names[m] = (head === 'Social' ? 'Sc' : 'sc') + tail))
put('graphql/schema.graphql', schema)
// build-web переименовывает те же идентификаторы в запросах интерфейса.
fs.writeFileSync(path.join(path.dirname(outDir), 'gql-names.json'), JSON.stringify(names, null, 2))

// Проверки: каждое правило сработало, ядро в коде не осталось.
const missed = [...phpRules, ...gqlRules].map(r => r.name).filter(n => !hits.has(n))
const leftovers = []
for (const rel of walk(outDir)) {
  const text = fs.readFileSync(path.join(outDir, rel), 'utf8')
  const code = text.replace(/\/\/[^\n]*|\/\*[\s\S]*?\*\//g, '')
  if (/\bApp\\(?!Configs)|\bTenant::|McpToolRegistry|EntitlementCatalog/.test(code)) leftovers.push(rel)
  if (/App\\\\Configs|'social_|"social\./.test(text.replace(/\/\/[^\n]*/g, ''))) leftovers.push(rel)
}
if (missed.length || leftovers.length) {
  console.error('build-api: проблемы\n  правила не сработали: ' + (missed.join(', ') || '—') + '\n  остатки ядра: ' + ([...new Set(leftovers)].join(', ') || '—'))
  process.exit(1)
}
console.log(`api собран: ${outDir}\n  файлов: ${walk(outDir).length}`)
