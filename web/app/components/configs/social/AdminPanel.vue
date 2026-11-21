<script setup lang="ts">
import type { ScProfile } from '../../../composables/useSocial'
import {
  SC_ADMIN_REPORTS, SC_ADMIN_MEMBERS, SC_ADMIN_SETTINGS, SC_RESOLVE_REPORT, SC_SET_BANNED, SC_SAVE_SETTINGS,
} from '../../../graphql/social/documents'

// Админка соцсети: жалобы, участники, настройки. Доступ только админу сайта — проверяет бэкенд (@can social.moderate).
const { t } = useI18n()
const toast = useToast()
const sc = useSocial()
const tab = ref<'reports' | 'members' | 'settings'>('reports')
const status = ref<'open' | 'resolved'>('open')
const reports = ref<any[]>([])
const members = ref<ScProfile[]>([])
const search = ref('')
const settings = reactive({ public_feed: true, posts_per_day: 50, comments_per_day: 200 })
const denied = ref(false)

const fail = (e: any) => {
  if (/unauthori|unauthenticated/i.test(e?.message ?? '')) denied.value = true
  else toast.add({ title: sc.errorText(e), color: 'error' })
}

async function loadReports() { try { reports.value = (await sc.query(SC_ADMIN_REPORTS, { status: status.value })).socialAdminReports } catch (e) { fail(e) } }
async function loadMembers() { try { members.value = (await sc.query(SC_ADMIN_MEMBERS, { query: search.value || null })).socialAdminMembers } catch (e) { fail(e) } }
async function loadSettings() { try { Object.assign(settings, (await sc.query(SC_ADMIN_SETTINGS)).socialAdminSettings) } catch (e) { fail(e) } }

onMounted(() => { loadReports(); loadSettings() })
watch(status, loadReports)
watch(tab, (v) => { if (v === 'members') loadMembers() })
let timer: ReturnType<typeof setTimeout> | null = null
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(loadMembers, 300) })

async function resolve(r: any, action: 'dismiss' | 'hide' | 'ban') {
  try { await sc.mutate(SC_RESOLVE_REPORT, { id: r.id, action }); toast.add({ title: t('social.saved'), color: 'success' }); await loadReports() } catch (e) { fail(e) }
}
async function ban(p: ScProfile, banned: boolean) {
  try { await sc.mutate(SC_SET_BANNED, { user_id: p.user_id, banned }); await loadMembers() } catch (e) { fail(e) }
}
async function saveSettings() {
  try {
    await sc.mutate(SC_SAVE_SETTINGS, { input: { public_feed: settings.public_feed, posts_per_day: Number(settings.posts_per_day), comments_per_day: Number(settings.comments_per_day) } })
    toast.add({ title: t('social.saved'), color: 'success' })
  } catch (e) { fail(e) }
}
</script>

<template>
  <div class="space-y-4">
    <p v-if="denied" class="text-muted">{{ t('social.denied') }}</p>
    <template v-else>
      <div class="flex gap-2">
        <UButton size="sm" :variant="tab === 'reports' ? 'soft' : 'ghost'" color="neutral" @click="tab = 'reports'">{{ t('social.admin_reports') }}</UButton>
        <UButton size="sm" :variant="tab === 'members' ? 'soft' : 'ghost'" color="neutral" @click="tab = 'members'">{{ t('social.admin_members') }}</UButton>
        <UButton size="sm" :variant="tab === 'settings' ? 'soft' : 'ghost'" color="neutral" @click="tab = 'settings'">{{ t('social.admin_settings') }}</UButton>
      </div>

      <template v-if="tab === 'reports'">
        <div class="flex gap-2">
          <UButton size="xs" :variant="status === 'open' ? 'soft' : 'ghost'" color="neutral" @click="status = 'open'">{{ t('social.status_open') }}</UButton>
          <UButton size="xs" :variant="status === 'resolved' ? 'soft' : 'ghost'" color="neutral" @click="status = 'resolved'">{{ t('social.status_resolved') }}</UButton>
        </div>
        <p v-if="!reports.length" class="text-sm text-muted">{{ t('social.no_reports') }}</p>
        <UCard v-for="r in reports" :key="r.id">
          <div class="text-xs text-muted mb-1">{{ t('social.target_' + r.target_type) }} · {{ r.reporter_name }} · {{ sc.ago(r.created_at) }}</div>
          <p class="whitespace-pre-wrap break-words">{{ r.excerpt }}</p>
          <p v-if="r.reason" class="text-sm text-muted mt-1">{{ t('social.reason') }}: {{ r.reason }}</p>
          <div v-if="r.status === 'open'" class="mt-3 flex gap-2 flex-wrap">
            <UButton size="xs" variant="outline" @click="resolve(r, 'dismiss')">{{ t('social.act_dismiss') }}</UButton>
            <UButton size="xs" color="warning" @click="resolve(r, 'hide')">{{ t('social.act_hide') }}</UButton>
            <UButton size="xs" color="error" @click="resolve(r, 'ban')">{{ t('social.act_ban') }}</UButton>
          </div>
        </UCard>
      </template>

      <template v-else-if="tab === 'members'">
        <UInput v-model="search" icon="i-lucide-search" :placeholder="t('social.search_people')" class="w-full" />
        <UCard>
          <p v-if="!members.length" class="text-sm text-muted">{{ t('social.nobody') }}</p>
          <div v-for="m in members" :key="m.user_id" class="flex items-center gap-3 py-2">
            <ConfigsSocialAvatar :name="m.name" :handle="m.handle" :url="m.avatar_url" :size="32" />
            <div class="min-w-0 flex-1">
              <div class="truncate">{{ m.name }} <span class="text-muted">@{{ m.handle }}</span></div>
              <div class="text-xs text-muted">{{ t('social.posts_n', { n: m.posts_count }) }}</div>
            </div>
            <UBadge v-if="m.is_banned" color="error">{{ t('social.banned') }}</UBadge>
            <UButton size="xs" variant="outline" :color="m.is_banned ? 'neutral' : 'error'" @click="ban(m, !m.is_banned)">{{ m.is_banned ? t('social.unban') : t('social.ban') }}</UButton>
          </div>
        </UCard>
      </template>

      <UCard v-else>
        <div class="space-y-4">
          <div class="flex items-start gap-3">
            <USwitch v-model="settings.public_feed" />
            <div><div class="font-medium">{{ t('social.set_public_feed') }}</div><div class="text-sm text-muted">{{ t('social.set_public_feed_hint') }}</div></div>
          </div>
          <UFormField :label="t('social.set_posts_per_day')"><UInput v-model="settings.posts_per_day" type="number" min="1" max="1000" /></UFormField>
          <UFormField :label="t('social.set_comments_per_day')"><UInput v-model="settings.comments_per_day" type="number" min="1" max="5000" /></UFormField>
          <UButton @click="saveSettings">{{ t('social.save') }}</UButton>
        </div>
      </UCard>
    </template>
  </div>
</template>
