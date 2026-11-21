import type { DocumentNode } from '@apollo/client/core'

export interface ScProfile {
  user_id: number; handle: string; name: string; bio?: string | null; website?: string | null; avatar_url?: string | null
  followers_count: number; following_count: number; posts_count: number; is_banned: boolean; followed_by_me: boolean; is_me: boolean
}
export interface ScPost {
  id: string; body: string; image_url?: string | null; likes_count: number; comments_count: number; created_at: string
  liked_by_me: boolean; can_delete: boolean; author: ScProfile
}

/** Помощники соцсети: GraphQL, текст ошибки, загрузка картинки. Права здесь не решаются — их держит бэкенд. */
export function useSocial() {
  const apollo = useGqlClient()
  const { t, locale } = useI18n()

  /** Текст ошибки для пользователя: сначала валидация полей, затем общее сообщение. */
  function errorText(e: any): string {
    const validation = e?.graphQLErrors?.[0]?.extensions?.validation as Record<string, string[]> | undefined
    const first = validation ? Object.values(validation)[0]?.[0] : null
    return first || e?.graphQLErrors?.[0]?.message || e?.message || t('social.error')
  }

  async function query<T = any>(q: DocumentNode, variables: Record<string, unknown> = {}): Promise<T> {
    return (await apollo.query({ query: q, variables, fetchPolicy: 'network-only' })).data as T
  }

  async function mutate<T = any>(m: DocumentNode, variables: Record<string, unknown> = {}): Promise<T> {
    return (await apollo.mutate({ mutation: m, variables })).data as T
  }

  /** Загрузка картинки (до 5 МБ): path уходит в мутацию, url — для предпросмотра. */
  const upload = (file: File) => socialUpload(file)

  /** «5 минут назад» или дата: Intl, без dayjs (время на сервере в UTC). */
  function ago(iso: string): string {
    const d = new Date(`${iso.replace(' ', 'T')}Z`)
    const diff = (Date.now() - d.getTime()) / 1000
    const rtf = new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' })
    if (diff < 3600) return rtf.format(-Math.max(1, Math.round(diff / 60)), 'minute')
    if (diff < 86400) return rtf.format(-Math.round(diff / 3600), 'hour')
    if (diff < 7 * 86400) return rtf.format(-Math.round(diff / 86400), 'day')
    return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(d)
  }

  return { query, mutate, upload, errorText, ago }
}
