// Пакет приложения: загрузка картинки через host.rest (адрес /api/v1/apps/rumantic.social/upload, сессия и CSRF — у хоста).
import { getHost } from '__SHIM_NUXT__'

export async function socialUpload(file: File): Promise<{ path: string, url: string }> {
  const body = new FormData()
  body.append('image', file)
  return await getHost().rest('upload', { method: 'POST', body })
}
