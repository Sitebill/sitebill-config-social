/**
 * Загрузка картинки на сервер — единственное место, где конфиг знает адрес REST и CSRF.
 * При сборке пакета приложения файл целиком заменяется оверлеем (tools/pack-community/overlay/web): там запрос идёт
 * через `host.rest('upload')` (адрес /api/v1/apps/rumantic.social/upload, сессия и CSRF — у хоста).
 */
export async function socialUpload(file: File): Promise<{ path: string, url: string }> {
  const body = new FormData()
  body.append('image', file)
  let token = useCookie('XSRF-TOKEN').value
  if (!token) {
    // У гостя публичной страницы куки может ещё не быть — POST без неё получит 419.
    await $fetch('/sanctum/csrf-cookie', { credentials: 'include' }).catch(() => null)
    token = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? null
  }
  return await $fetch<{ path: string, url: string }>('/api/v1/social/upload', {
    method: 'POST',
    body,
    credentials: 'include',
    headers: { Accept: 'application/json', ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}) },
  })
}
