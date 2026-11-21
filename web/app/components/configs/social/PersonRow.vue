<script setup lang="ts">
import type { ScProfile } from '../../../composables/useSocial'
import { SC_TOGGLE_FOLLOW } from '../../../graphql/social/documents'

// Строка человека: аватар, имя, @ник и кнопка подписки (если вошедший участник и это не он сам).
const props = defineProps<{ person: ScProfile, canFollow: boolean }>()
const emit = defineEmits<{ open: [handle: string], changed: [] }>()
const { t } = useI18n()
const toast = useToast()
const sc = useSocial()
const following = ref(props.person.followed_by_me)
const busy = ref(false)
watch(() => props.person.followed_by_me, v => { following.value = v })

async function toggle() {
  busy.value = true
  try {
    following.value = (await sc.mutate(SC_TOGGLE_FOLLOW, { user_id: props.person.user_id })).socialToggleFollow.following
    emit('changed')
  } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) } finally { busy.value = false }
}
</script>

<template>
  <div class="flex items-center gap-3 py-2">
    <ConfigsSocialAvatar :name="person.name" :handle="person.handle" :url="person.avatar_url" />
    <button type="button" class="min-w-0 flex-1 text-left" @click="emit('open', person.handle)">
      <div class="font-medium truncate">{{ person.name }}</div>
      <div class="text-sm text-muted truncate">@{{ person.handle }} · {{ t('social.followers_n', { n: person.followers_count }) }}</div>
    </button>
    <UButton
      v-if="canFollow && !person.is_me"
      size="sm"
      :variant="following ? 'outline' : 'solid'"
      :loading="busy"
      @click="toggle"
    >{{ following ? t('social.unfollow') : t('social.follow') }}</UButton>
  </div>
</template>
