<script setup lang="ts">
import type { ScProfile } from '../../../composables/useSocial'
import { SC_PROFILE, SC_USER_POSTS, SC_CONNECTIONS, SC_TOGGLE_FOLLOW, SC_REPORT } from '../../../graphql/social/documents'

// Профиль: шапка со счётчиками, вкладки «Посты / Подписчики / Подписки».
const props = defineProps<{ handle: string, canAct: boolean }>()
const emit = defineEmits<{ openProfile: [handle: string], openPost: [id: string], edit: [], back: [] }>()
const { t } = useI18n()
const toast = useToast()
const sc = useSocial()
const profile = ref<ScProfile | null>(null)
const tab = ref<'posts' | 'FOLLOWERS' | 'FOLLOWING'>('posts')
const posts = ref<any[]>([])
const people = ref<ScProfile[]>([])
const loading = ref(true)
const busy = ref(false)
const tabLoading = ref(true)

async function loadTab() {
  if (!profile.value) return
  tabLoading.value = true
  try {
    if (tab.value === 'posts') posts.value = (await sc.query(SC_USER_POSTS, { user_id: profile.value.user_id })).socialUserPosts
    else people.value = (await sc.query(SC_CONNECTIONS, { user_id: profile.value.user_id, kind: tab.value })).socialConnections
  } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) } finally { tabLoading.value = false }
}

async function load() {
  loading.value = true
  tab.value = 'posts'
  try { profile.value = (await sc.query(SC_PROFILE, { handle: props.handle })).socialProfile } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) }
  loading.value = false
  await loadTab()
}
onMounted(load)
watch(() => props.handle, load)
watch(tab, loadTab)

async function follow() {
  if (!profile.value) return
  busy.value = true
  try {
    const r = (await sc.mutate(SC_TOGGLE_FOLLOW, { user_id: profile.value.user_id })).socialToggleFollow
    profile.value.followed_by_me = r.following
    profile.value.followers_count = r.followers_count
  } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) } finally { busy.value = false }
}

async function report() {
  if (!profile.value) return
  const reason = globalThis.prompt(t('social.report_prompt')) ?? null
  if (reason === null) return
  try {
    await sc.mutate(SC_REPORT, { target_type: 'profile', target_id: profile.value.user_id, reason })
    toast.add({ title: t('social.reported'), color: 'success' })
  } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) }
}
</script>

<template>
  <div class="space-y-4">
    <UButton variant="ghost" color="neutral" icon="i-lucide-arrow-left" size="sm" @click="emit('back')">{{ t('social.back') }}</UButton>
    <p v-if="loading" class="text-muted">{{ t('social.loading') }}</p>
    <p v-else-if="!profile" class="text-muted">{{ t('social.profile_not_found') }}</p>
    <template v-else>
      <UCard>
        <div class="flex items-start gap-4">
          <ConfigsSocialAvatar :name="profile.name" :handle="profile.handle" :url="profile.avatar_url" :size="72" />
          <div class="min-w-0 flex-1">
            <h2 class="text-xl font-bold truncate">{{ profile.name }}</h2>
            <div class="text-muted">@{{ profile.handle }}</div>
            <p v-if="profile.bio" class="mt-2 whitespace-pre-wrap break-words">{{ profile.bio }}</p>
            <a v-if="profile.website" :href="profile.website" target="_blank" rel="nofollow noopener noreferrer" class="text-sm text-primary break-all">{{ profile.website }}</a>
          </div>
          <div class="flex flex-col gap-2 shrink-0">
            <UButton v-if="profile.is_me" variant="outline" size="sm" @click="emit('edit')">{{ t('social.edit_profile') }}</UButton>
            <UButton v-else-if="canAct" size="sm" :variant="profile.followed_by_me ? 'outline' : 'solid'" :loading="busy" @click="follow">{{ profile.followed_by_me ? t('social.unfollow') : t('social.follow') }}</UButton>
            <UButton v-if="canAct && !profile.is_me" size="xs" variant="ghost" color="neutral" icon="i-lucide-flag" :aria-label="t('social.report')" @click="report" />
          </div>
        </div>
        <div class="mt-4 flex gap-2 text-sm">
          <UButton size="sm" :variant="tab === 'posts' ? 'soft' : 'ghost'" color="neutral" @click="tab = 'posts'">{{ t('social.posts_n', { n: profile.posts_count }) }}</UButton>
          <UButton size="sm" :variant="tab === 'FOLLOWERS' ? 'soft' : 'ghost'" color="neutral" @click="tab = 'FOLLOWERS'">{{ t('social.followers_n', { n: profile.followers_count }) }}</UButton>
          <UButton size="sm" :variant="tab === 'FOLLOWING' ? 'soft' : 'ghost'" color="neutral" @click="tab = 'FOLLOWING'">{{ t('social.following_n', { n: profile.following_count }) }}</UButton>
        </div>
      </UCard>
      <template v-if="tab === 'posts'">
        <p v-if="tabLoading" class="text-muted text-sm">{{ t('social.loading') }}</p>
        <p v-else-if="!posts.length" class="text-muted text-sm">{{ t('social.no_posts') }}</p>
        <ConfigsSocialPostCard
          v-for="p in posts" :key="p.id" :post="p" :can-act="canAct"
          @open-profile="h => emit('openProfile', h)" @open-post="id => emit('openPost', id)"
          @deleted="id => (posts = posts.filter(x => x.id !== id))"
        />
      </template>
      <UCard v-else>
        <p v-if="tabLoading" class="text-muted text-sm">{{ t('social.loading') }}</p>
        <p v-else-if="!people.length" class="text-muted text-sm">{{ t('social.nobody') }}</p>
        <ConfigsSocialPersonRow v-for="p in people" :key="p.user_id" :person="p" :can-follow="canAct" @open="h => emit('openProfile', h)" />
      </UCard>
    </template>
  </div>
</template>
