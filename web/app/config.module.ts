import SocialHome from './components/configs/social/Home.vue'
import SocialAdminPanel from './components/configs/social/AdminPanel.vue'
import type { ConfigModuleDefinition } from '~/core/configs/types'

/**
 * Точка входа frontend-части конфигурации `social` — социальная сеть (docs/SOCIAL_APP.md).
 * Регистрируется в apps/web/app/core/configs/registry.ts. Публичный сайт — одна главная (лента), админка — страница admin/social.
 */
export const socialConfigModule: ConfigModuleDefinition = {
  id: 'social',
  label: 'Социальная сеть',
  description: 'Сообщество на вашем сайте: профили, лента, подписки, лайки, комментарии, жалобы и модерация.',
  homeComponent: SocialHome,
  publicLayoutMode: 'default',
  navItems: ({ t, localePath }) => [
    { label: t('social.nav_feed'), icon: 'i-lucide-newspaper', to: localePath('/') },
  ],
  menuRoutes: () => [],
  userMenuItems: () => [],
  // Непустой список заменяет пункты недвижимости в админке (см. useAdminBaseNav.ts).
  adminMenuItems: ({ t }) => [
    { label: t('social.nav_admin'), icon: 'i-lucide-users-round', to: '/admin' },
  ],
  adminDashboardComponent: SocialAdminPanel,
}
