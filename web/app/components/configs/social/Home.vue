<script setup lang="ts">
import type { ScProfile, ScPost } from '../../../composables/useSocial'
import { SC_CONFIG, SC_FEED, SC_PEOPLE } from '../../../graphql/social/documents'

// Главная соцсети — одностраничное приложение: лента, пост, профиль, люди, настройки.
// Состояние в адресе (?v=post&id=3, ?u=anna), чтобы работали ссылки из уведомлений и кнопка «назад».
const { t } = useI18n()
const localePath = useLocalePath()
const toast = useToast()
const sc = useSocial()

type View = 'feed' | 'post' | 'profile' | 'people' | 'settings'
const cfg = ref<{ public_feed: boolean, can_post: boolean, is_moderator: boolean, me: ScProfile | null } | null>(null)
const view = ref<View>('feed')
const postId = ref('')
const handle = ref('')
const scope = ref<'ALL' | 'FOLLOWING'>('ALL')
const posts = ref<ScPost[]>([])
const people = ref<ScProfile[]>([])
const search = ref('')
const loading = ref(true)
const more = ref(true)
const closed = ref(false)

const canAct = computed(() => !!cfg.value?.can_post)

function readUrl() {
  const q = new URLSearchParams(globalThis.location?.search ?? '')
  if (q.get('v') === 'post' && q.get('id')) { view.value = 'post'; postId.value = q.get('id')! }
  else if (q.get('u')) { view.value = 'profile'; handle.value = q.get('u')! }
  else if (q.get('v') === 'people') view.value = 'people'
  else view.value = 'feed'
}

function go(v: View, params: { id?: string, u?: string } = {}) {
  view.value = v
  if (params.id) postId.value = params.id
  if (params.u) handle.value = params.u
  const q = v === 'post' ? `?v=post&id=${postId.value}` : v === 'profile' ? `?u=${handle.value}` : v === 'people' ? '?v=people' : ''
  globalThis.history?.pushState({}, '', (globalThis.location?.pathname ?? '/') + q)
  if (v === 'feed') loadFeed(true)
  if (v === 'people') loadPeople()
}

async function loadFeed(reset = false) {
  if (reset) { posts.value = []; more.value = true }
  loading.value = true
  try {
    const last = posts.value[posts.value.length - 1]
    const rows: ScPost[] = (await sc.query(SC_FEED, { scope: scope.value, after_id: last?.id ?? null, limit: 20 })).socialFeed
    posts.value.push(...rows)
    more.value = rows.length === 20
  } catch (e: any) {
    // Лента закрыта для гостей: показываем приглашение войти, а не ошибку.
    if (!cfg.value?.public_feed && !cfg.value?.can_post) closed.value = true
    else toast.add({ title: sc.errorText(e), color: 'error' })
  } finally { loading.value = false }
}

async function loadPeople() {
  try { people.value = (await sc.query(SC_PEOPLE, { query: search.value || null, limit: 30 })).socialPeople } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) }
}

async function loadConfig() { cfg.value = (await sc.query(SC_CONFIG)).socialConfig }

onMounted(async () => {
  readUrl()
  globalThis.addEventListener?.('popstate', () => { readUrl(); if (view.value === 'feed') loadFeed(true) })
  try { await loadConfig() } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) }
  await loadFeed(true)
  if (view.value === 'people') loadPeople()
})
watch(scope, () => loadFeed(true))
let timer: ReturnType<typeof setTimeout> | null = null
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(loadPeople, 300) })

function posted(p: ScPost) { posts.value.unshift(p) }
function profileSaved(p: ScProfile) { if (cfg.value) cfg.value.me = p; go('profile', { u: p.handle }) }
</script>

<template>
  <div class="max-w-2xl mx-auto px-4 py-6 space-y-4">
    <div class="flex items-center gap-2 flex-wrap">
      <h1 class="text-xl font-bold mr-auto">{{ t('social.app_name') }}</h1>
      <UButton size="sm" :variant="view === 'feed' ? 'soft' : 'ghost'" color="neutral" icon="i-lucide-newspaper" @click="go('feed')">{{ t('social.nav_feed') }}</UButton>
      <UButton size="sm" :variant="view === 'people' ? 'soft' : 'ghost'" color="neutral" icon="i-lucide-users" @click="go('people')">{{ t('social.nav_people') }}</UButton>
      <UButton v-if="cfg?.me" size="sm" :variant="view === 'profile' && handle === cfg.me.handle ? 'soft' : 'ghost'" color="neutral" icon="i-lucide-user" @click="go('profile', { u: cfg.me.handle })">{{ t('social.nav_me') }}</UButton>
      <UButton v-if="!cfg?.can_post && !cfg?.me" size="sm" :to="localePath('/auth/login')">{{ t('social.sign_in') }}</UButton>
    </div>

    <UCard v-if="closed">
      <p class="mb-3">{{ t('social.closed') }}</p>
      <UButton :to="localePath('/auth/login')">{{ t('social.sign_in') }}</UButton>
    </UCard>

    <template v-else-if="view === 'feed'">
      <div v-if="canAct" class="flex gap-2">
        <UButton size="sm" :variant="scope === 'ALL' ? 'soft' : 'ghost'" color="neutral" @click="scope = 'ALL'">{{ t('social.scope_all') }}</UButton>
        <UButton size="sm" :variant="scope === 'FOLLOWING' ? 'soft' : 'ghost'" color="neutral" @click="scope = 'FOLLOWING'">{{ t('social.scope_following') }}</UButton>
      </div>
      <ConfigsSocialComposer v-if="canAct" @posted="posted" />
      <UCard v-else-if="cfg && !cfg.me">
        <div class="flex items-center gap-3">
          <p class="flex-1 text-sm text-muted">{{ t('social.guest_prompt') }}</p>
          <UButton size="sm" :to="localePath('/auth/login')">{{ t('social.sign_in') }}</UButton>
        </div>
      </UCard>
      <p v-if="!loading && !posts.length" class="text-muted text-center py-8">{{ scope === 'FOLLOWING' ? t('social.empty_following') : t('social.empty_feed') }}</p>
      <ConfigsSocialPostCard
        v-for="p in posts" :key="p.id" :post="p" :can-act="canAct"
        @open-profile="h => go('profile', { u: h })" @open-post="id => go('post', { id })"
        @deleted="id => (posts = posts.filter(x => x.id !== id))"
      />
      <div v-if="more && posts.length" class="text-center"><UButton variant="outline" :loading="loading" @click="loadFeed()">{{ t('social.more') }}</UButton></div>
    </template>

    <ConfigsSocialPostView v-else-if="view === 'post'" :id="postId" :can-act="canAct" @open-profile="h => go('profile', { u: h })" @back="go('feed')" />

    <ConfigsSocialSettingsForm v-else-if="view === 'settings' && cfg?.me" :me="cfg.me" @saved="profileSaved" @cancel="go('profile', { u: cfg.me.handle })" />

    <ConfigsSocialProfileView
      v-else-if="view === 'profile'" :handle="handle" :can-act="canAct"
      @open-profile="h => go('profile', { u: h })" @open-post="id => go('post', { id })" @edit="view = 'settings'" @back="go('feed')"
    />

    <template v-else-if="view === 'people'">
      <UInput v-model="search" icon="i-lucide-search" :placeholder="t('social.search_people')" class="w-full" />
      <UCard>
        <p v-if="!people.length" class="text-sm text-muted">{{ t('social.nobody') }}</p>
        <ConfigsSocialPersonRow v-for="p in people" :key="p.user_id" :person="p" :can-follow="canAct" @open="h => go('profile', { u: h })" />
      </UCard>
    </template>
  </div>
</template>
