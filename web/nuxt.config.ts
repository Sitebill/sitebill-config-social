// Nuxt Layer конфигурации `social` (социальная сеть). Подключается через `extends` в apps/web/nuxt.config.ts.
// Локали перечисляются явно и мёржатся с корневым списком по `code`; langDir у layer'а свой (см. configs/content/web).
export default defineNuxtConfig({
  i18n: {
    langDir: '../locales/',
    locales: [
      { code: 'ru', file: 'ru.json' },
      { code: 'en', file: 'en.json' },
      { code: 'sr', file: 'sr.json' },
      { code: 'tg', file: 'tg.json' },
    ],
  },
})
