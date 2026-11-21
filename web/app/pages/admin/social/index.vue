<script setup lang="ts">
// Управление соцсетью: жалобы, участники, настройки (только админ сайта; права проверяет бэкенд).
definePageMeta({ layout: 'admin', middleware: ['auth'] })
const { t } = useI18n()
</script>

<template>
  <div class="max-w-3xl">
    <h1 class="text-xl font-bold mb-4">{{ t('social.nav_admin') }}</h1>
    <ConfigsSocialAdminPanel />
  </div>
</template>
