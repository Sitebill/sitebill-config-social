<script setup lang="ts">
import type { ScPost } from '../../../composables/useSocial'
import { SC_TOGGLE_LIKE, SC_DELETE_POST, SC_REPORT } from '../../../graphql/social/documents'

// Карточка поста: автор, текст (как текст — без v-html), картинка, лайк, комментарии, жалоба, удаление.
const props = defineProps<{ post: ScPost, canAct: boolean }>()
const emit = defineEmits<{ openProfile: [handle: string], openPost: [id: string], deleted: [id: string] }>()
const { t } = useI18n()
const toast = useToast()
const { confirmDialog } = useConfirmDialog()
const sc = useSocial()
const liked = ref(props.post.liked_by_me)
const likes = ref(props.post.likes_count)
watch(() => props.post, p => { liked.value = p.liked_by_me; likes.value = p.likes_count })

async function like() {
  if (!props.canAct) return
  try {
    const r = (await sc.mutate(SC_TOGGLE_LIKE, { post_id: Number(props.post.id) })).socialToggleLike
    liked.value = r.liked
    likes.value = r.likes_count
  } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) }
}

async function remove() {
  if (!await confirmDialog({ title: t('social.delete_post_confirm') })) return
  try {
    await sc.mutate(SC_DELETE_POST, { id: props.post.id })
    emit('deleted', props.post.id)
  } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) }
}

async function report() {
  const reason = globalThis.prompt(t('social.report_prompt')) ?? null
  if (reason === null) return
  try {
    await sc.mutate(SC_REPORT, { target_type: 'post', target_id: Number(props.post.id), reason })
    toast.add({ title: t('social.reported'), color: 'success' })
  } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) }
}
</script>

<template>
  <UCard>
    <div class="flex items-center gap-3 mb-2">
      <ConfigsSocialAvatar :name="post.author.name" :handle="post.author.handle" :url="post.author.avatar_url" />
      <button type="button" class="min-w-0 flex-1 text-left" @click="emit('openProfile', post.author.handle)">
        <div class="font-medium truncate">{{ post.author.name }}</div>
        <div class="text-xs text-muted">@{{ post.author.handle }} · {{ sc.ago(post.created_at) }}</div>
      </button>
      <UButton v-if="post.can_delete" size="xs" variant="ghost" color="neutral" icon="i-lucide-trash-2" :aria-label="t('social.delete')" @click="remove" />
    </div>
    <p class="whitespace-pre-wrap break-words">{{ post.body }}</p>
    <img v-if="post.image_url" :src="post.image_url" alt="" class="mt-3 max-h-96 rounded-lg" loading="lazy">
    <div class="mt-3 flex items-center gap-1">
      <UButton size="sm" variant="ghost" :color="liked ? 'error' : 'neutral'" :icon="liked ? 'i-lucide-heart' : 'i-lucide-heart'" :disabled="!canAct" @click="like">{{ likes }}</UButton>
      <UButton size="sm" variant="ghost" color="neutral" icon="i-lucide-message-circle" @click="emit('openPost', post.id)">{{ post.comments_count }}</UButton>
      <div class="flex-1" />
      <UButton v-if="canAct && !post.author.is_me" size="xs" variant="ghost" color="neutral" icon="i-lucide-flag" :aria-label="t('social.report')" @click="report" />
    </div>
  </UCard>
</template>
