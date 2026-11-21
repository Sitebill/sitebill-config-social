<script setup lang="ts">
import type { ScProfile } from '../../../composables/useSocial'
import { SC_SAVE_PROFILE } from '../../../graphql/social/documents'

// Настройки своего профиля: имя берётся из аккаунта платформы (здесь не меняется), остальное — тут.
const props = defineProps<{ me: ScProfile }>()
const emit = defineEmits<{ saved: [profile: ScProfile], cancel: [] }>()
const { t } = useI18n()
const toast = useToast()
const sc = useSocial()
const form = reactive({ handle: props.me.handle, bio: props.me.bio ?? '', website: props.me.website ?? '' })
const avatar = ref<{ path: string, url: string } | null>(null)
const removeAvatar = ref(false)
const busy = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const preview = computed(() => removeAvatar.value ? null : (avatar.value?.url ?? props.me.avatar_url ?? null))

async function pick(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  try { avatar.value = await sc.upload(file); removeAvatar.value = false } catch (err: any) {
    toast.add({ title: err?.data?.message || sc.errorText(err), color: 'error' })
  }
}

async function save() {
  busy.value = true
  try {
    const input: Record<string, unknown> = { handle: form.handle, bio: form.bio, website: form.website }
    // Без avatar_path аватар не меняется; null — удалить.
    if (avatar.value) input.avatar_path = avatar.value.path
    else if (removeAvatar.value) input.avatar_path = null
    emit('saved', (await sc.mutate(SC_SAVE_PROFILE, { input })).socialSaveProfile)
    toast.add({ title: t('social.saved'), color: 'success' })
  } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) } finally { busy.value = false }
}
</script>

<template>
  <UCard>
    <h2 class="text-lg font-semibold mb-4">{{ t('social.edit_profile') }}</h2>
    <div class="flex items-center gap-4 mb-4">
      <ConfigsSocialAvatar :name="me.name" :handle="form.handle || me.handle" :url="preview" :size="64" />
      <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" @change="pick">
      <UButton variant="outline" size="sm" @click="fileInput?.click()">{{ t('social.change_avatar') }}</UButton>
      <UButton v-if="preview" variant="ghost" size="sm" color="neutral" @click="removeAvatar = true; avatar = null">{{ t('social.remove_photo') }}</UButton>
    </div>
    <div class="space-y-3">
      <UFormField :label="t('social.handle')" :description="t('social.handle_hint')"><UInput v-model="form.handle" class="w-full" :maxlength="30" /></UFormField>
      <UFormField :label="t('social.bio')"><UTextarea v-model="form.bio" :rows="3" :maxlength="500" class="w-full" /></UFormField>
      <UFormField :label="t('social.website')"><UInput v-model="form.website" class="w-full" placeholder="https://" :maxlength="255" /></UFormField>
    </div>
    <div class="mt-4 flex gap-2">
      <UButton :loading="busy" @click="save">{{ t('social.save') }}</UButton>
      <UButton variant="ghost" color="neutral" @click="emit('cancel')">{{ t('social.cancel') }}</UButton>
    </div>
  </UCard>
</template>
