<script setup lang="ts">
import { SC_POST, SC_COMMENTS, SC_ADD_COMMENT, SC_DELETE_COMMENT, SC_REPORT } from '../../../graphql/social/documents'

// Пост с комментариями (один уровень). Писать может участник; удалить — автор комментария или модератор (решает бэкенд).
const props = defineProps<{ id: string, canAct: boolean }>()
const emit = defineEmits<{ openProfile: [handle: string], back: [] }>()
const { t } = useI18n()
const toast = useToast()
const sc = useSocial()
const post = ref<any>(null)
const comments = ref<any[]>([])
const text = ref('')
const loading = ref(true)
const busy = ref(false)

async function load() {
  loading.value = true
  try {
    post.value = (await sc.query(SC_POST, { id: props.id })).socialPost
    comments.value = post.value ? (await sc.query(SC_COMMENTS, { post_id: Number(props.id) })).socialComments : []
  } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) } finally { loading.value = false }
}
onMounted(load)
watch(() => props.id, load)

async function send() {
  busy.value = true
  try {
    comments.value.push((await sc.mutate(SC_ADD_COMMENT, { post_id: Number(props.id), body: text.value })).socialAddComment)
    post.value.comments_count++
    text.value = ''
  } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) } finally { busy.value = false }
}

async function remove(c: any) {
  try {
    await sc.mutate(SC_DELETE_COMMENT, { id: c.id })
    comments.value = comments.value.filter(x => x.id !== c.id)
    post.value.comments_count--
  } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) }
}

async function report(c: any) {
  const reason = globalThis.prompt(t('social.report_prompt')) ?? null
  if (reason === null) return
  try {
    await sc.mutate(SC_REPORT, { target_type: 'comment', target_id: Number(c.id), reason })
    toast.add({ title: t('social.reported'), color: 'success' })
  } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) }
}
</script>

<template>
  <div class="space-y-4">
    <UButton variant="ghost" color="neutral" icon="i-lucide-arrow-left" size="sm" @click="emit('back')">{{ t('social.back') }}</UButton>
    <p v-if="loading" class="text-muted">{{ t('social.loading') }}</p>
    <p v-else-if="!post" class="text-muted">{{ t('social.post_not_found') }}</p>
    <template v-else>
      <ConfigsSocialPostCard :post="post" :can-act="canAct" @open-profile="h => emit('openProfile', h)" @open-post="() => {}" @deleted="emit('back')" />
      <UCard>
        <h3 class="font-semibold mb-3">{{ t('social.comments') }}</h3>
        <p v-if="!comments.length" class="text-sm text-muted">{{ t('social.no_comments') }}</p>
        <div v-for="c in comments" :key="c.id" class="flex gap-3 py-2">
          <ConfigsSocialAvatar :name="c.author.name" :handle="c.author.handle" :url="c.author.avatar_url" :size="32" />
          <div class="min-w-0 flex-1">
            <button type="button" class="text-sm font-medium" @click="emit('openProfile', c.author.handle)">{{ c.author.name }}</button>
            <span class="text-xs text-muted"> · {{ sc.ago(c.created_at) }}</span>
            <p class="whitespace-pre-wrap break-words text-sm">{{ c.body }}</p>
          </div>
          <UButton v-if="c.can_delete" size="xs" variant="ghost" color="neutral" icon="i-lucide-trash-2" :aria-label="t('social.delete')" @click="remove(c)" />
          <UButton v-else-if="canAct" size="xs" variant="ghost" color="neutral" icon="i-lucide-flag" :aria-label="t('social.report')" @click="report(c)" />
        </div>
        <div v-if="canAct" class="mt-3 flex gap-2">
          <UTextarea v-model="text" :rows="2" :maxlength="1000" :placeholder="t('social.comment_placeholder')" class="flex-1" />
          <UButton :loading="busy" :disabled="!text.trim()" @click="send">{{ t('social.send') }}</UButton>
        </div>
        <p v-else class="mt-3 text-sm text-muted">{{ t('social.sign_in_to_comment') }}</p>
      </UCard>
    </template>
  </div>
</template>
