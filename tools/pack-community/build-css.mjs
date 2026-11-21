// Свой CSS приложения (docs/roadmap/CONTENT_FACTORY.md §8). Хост собирает стили под классы, которые встречаются в его
// собственных исходниках, поэтому классов, нужных только странице приложения, в его CSS может не оказаться.
// Здесь Tailwind (тот же, что у apps/web, с темой Nuxt UI) просматривает SFC конфига и отдаёт только слой utilities
// и @property: токены темы (--ui-*) и базовые стили приложение берёт у хоста.
//
// Запуск: node tools/pack-community/build-css.mjs [--out <файл>]
import { createRequire } from 'node:module'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const here = path.dirname(fileURLToPath(import.meta.url))
const configRoot = path.resolve(here, '../..')
const arg = (name, def) => { const i = process.argv.indexOf('--' + name); return i > -1 ? process.argv[i + 1] : def }
const outFile = path.resolve(arg('out', path.join(here, '.build/app.css')))
const nm = path.resolve(configRoot, '../../apps/web/node_modules')
const req = createRequire(path.join(nm, 'x.js'))
const load = async name => import(pathToFileURL(req.resolve(name)).href)

const postcss = (await load('postcss')).default
const tailwind = (await load('@tailwindcss/postcss')).default

const slash = p => p.replace(/\\/g, '/')
const entry = path.join(nm, '.pack-community-entry.css') // внутри node_modules веба, чтобы импорты разрешались оттуда
// Тот же вход, что у apps/web/app/assets/css/main.css, но слои подключены раздельно, чтобы вынуть utilities.
fs.writeFileSync(entry, [
  '@layer theme, base, components, utilities;',
  '@import "tailwindcss/theme.css" layer(theme) theme(static);',
  '@import "@nuxt/ui";',
  '@import "tailwindcss/utilities.css" layer(utilities) source(none);',
  `@source "${slash(path.join(configRoot, 'web/app'))}";`,
  '',
].join('\n'))

const css = fs.readFileSync(entry, 'utf8')
// Разрешение импортов — из node_modules веб-приложения.
process.chdir(path.resolve(configRoot, '../../apps/web'))
const result = await postcss([tailwind({ base: path.resolve(configRoot, '../../apps/web') })]).process(css, { from: entry })

// Вынимаем из результата только `@layer utilities {…}` и `@property …{…}` (скобочный разбор, строки и комментарии учтены).
function blocks(src) {
  const out = []
  let i = 0
  while (i < src.length) {
    const open = src.indexOf('{', i)
    if (open < 0) break
    const head = src.slice(i, open).trim()
    let depth = 1, j = open + 1, quote = null
    for (; j < src.length && depth > 0; j++) {
      const c = src[j]
      if (quote) { if (c === '\\') j++; else if (c === quote) quote = null; continue }
      if (c === '"' || c === "'") quote = c
      else if (c === '{') depth++
      else if (c === '}') depth--
    }
    out.push({ head: head.replace(/^(?:\/\*[\s\S]*?\*\/\s*)+/, ''), text: src.slice(i, j) })
    i = j
  }
  return out
}
const keep = blocks(result.css).filter(b => /^@layer utilities$/.test(b.head) || /^@property /.test(b.head))
const outCss = keep.map(b => b.text.trim()).join('\n') + '\n'
if (!/@layer utilities/.test(outCss)) throw new Error('build-css: слой utilities пуст — Tailwind не нашёл классов')
fs.mkdirSync(path.dirname(outFile), { recursive: true })
fs.writeFileSync(outFile, outCss)
console.log(`css собран: ${outFile}\n  ${(outCss.length / 1024).toFixed(1)} КБ, блоков: ${keep.length}`)
