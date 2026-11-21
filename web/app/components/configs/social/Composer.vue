<script setup lang="ts">
import { SC_CREATE_POST } from '../../../graphql/social/documents'

// Форма нового поста: текст до 2000 символов и одна картинка (загружается сразу, путь уходит вместе с постом).
const emit = defineEmits<{ posted: [post: any] }>()
const { t } = useI18n()
const toast = useToast()
const sc = useSocial()
const body = ref('')
const image = ref<{ path: string, url: string } | null>(null)
const busy = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

async function pick(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  busy.value = true
  try { image.value = await sc.upload(file) } catch (err: any) {
    toast.add({ title: err?.data?.message || sc.errorText(err), color: 'error' })
  } finally { busy.value = false; if (fileInput.value) fileInput.value.value = '' }
}

async function submit() {
  busy.value = true
  try {
    const res = await sc.mutate(SC_CREATE_POST, { body: body.value, image_path: image.value?.path ?? null })
    emit('posted', res.socialCreatePost)
    body.value = ''
    image.value = null
  } catch (e) { toast.add({ title: sc.errorText(e), color: 'error' }) } finally { busy.value = false }
}
</script>

<template>
  <UCard>
    <UTextarea v-model="body" :rows="3" :maxlength="2000" :placeholder="t('social.composer_placeholder')" class="w-full" />
    <img v-if="image" :src="image.url" alt="" class="mt-3 max-h-64 rounded-lg">
    <div class="mt-3 flex items-center gap-2">
      <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" @change="pick">
      <UButton variant="outline" size="sm" icon="i-lucide-image" :disabled="busy" @click="fileInput?.click()">{{ t('social.add_photo') }}</UButton>
      <UButton v-if="image" variant="ghost" size="sm" color="neutral" @click="image = null">{{ t('social.remove_photo') }}</UButton>
      <div class="flex-1" />
      <UButton :loading="busy" :disabled="!body.trim() && !image" @click="submit">{{ t('social.publish') }}</UButton>
    </div>
  </UCard>
</template>
