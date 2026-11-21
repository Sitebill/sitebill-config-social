<script setup lang="ts">
// Аватар: картинка или инициалы на цветном фоне (цвет — из ника, чтобы у человека он не менялся).
const props = defineProps<{ name: string, handle: string, url?: string | null, size?: number }>()
const px = computed(() => props.size ?? 40)
const initials = computed(() => (props.name || props.handle).trim().split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join(''))
const hue = computed(() => [...props.handle].reduce((a, c) => (a * 31 + c.charCodeAt(0)) % 360, 7))
</script>

<template>
  <img v-if="url" :src="url" :alt="name" class="rounded-full object-cover shrink-0" :style="{ width: px + 'px', height: px + 'px' }">
  <span
    v-else
    class="rounded-full shrink-0 inline-flex items-center justify-center font-semibold text-white select-none"
    :style="{ width: px + 'px', height: px + 'px', fontSize: px / 2.6 + 'px', background: `hsl(${hue} 55% 45%)` }"
  >{{ initials }}</span>
</template>
