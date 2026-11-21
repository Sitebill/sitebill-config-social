// Компоненты Nuxt UI для SFC конфига. Хост отдаёт приложению ограниченный набор (host.components: UButton, UInput, UTextarea,
// UBadge, UIcon, UModal, UCard, USwitch, USelect). Их берём у хоста в момент отрисовки; недостающие (UFormField, UEditor, NuxtLink)
// заменяем простыми аналогами. Ссылки на страницы конфига (/admin/social/…) переводятся в адреса приложения.
import { defineComponent, h } from 'vue'
import { getHost, go, toAppUrl } from './nuxt.mjs'

const isConfigLink = to => typeof to === 'string' && to.startsWith('/admin/social')

/** Свойства ссылки: ссылки внутри страницы приложения (?id=) обрабатываем сами, остальные отдаём роутеру хоста. */
function linkProps(attrs) {
  const to = attrs.to
  if (!isConfigLink(to)) return attrs
  const url = toAppUrl(to)
  const samePage = typeof location !== 'undefined' && url.split('?')[0] === location.pathname
  const { to: _drop, onClick, ...rest } = attrs
  if (!samePage) return { ...rest, onClick, to: url }
  return { ...rest, to: undefined, onClick: (e) => { onClick?.(e); go(to) } }
}

const viaHost = (name, fallback) => defineComponent({
  name,
  inheritAttrs: false,
  setup(_, { attrs, slots }) {
    return () => {
      const C = getHost()?.components?.[name] ?? fallback
      const props = name === 'UButton' ? linkProps(attrs) : attrs
      return C ? h(C, props, slots) : h('div', props, slots.default?.())
    }
  },
})

export const UButton = viaHost('UButton')
export const UInput = viaHost('UInput', 'input')
export const UTextarea = viaHost('UTextarea', 'textarea')
export const UBadge = viaHost('UBadge', 'span')
export const UIcon = viaHost('UIcon', 'span')
export const UModal = viaHost('UModal')
export const UCard = viaHost('UCard')
export const USwitch = viaHost('USwitch')
export const USelect = viaHost('USelect', 'select')

export const UFormField = defineComponent({
  name: 'UFormField',
  props: { label: String, hint: String, description: String, help: String, error: [String, Boolean], required: Boolean, name: String },
  setup(p, { slots, attrs }) {
    return () => h('div', { class: ['space-y-1', attrs.class] }, [
      p.label && h('label', { class: 'block text-sm font-medium text-highlighted' }, [p.label, p.required ? h('span', { class: 'text-error' }, ' *') : null]),
      p.description && h('p', { class: 'text-xs text-muted' }, p.description),
      slots.default?.(),
      p.hint && h('p', { class: 'text-xs text-muted' }, p.hint),
      p.help && h('p', { class: 'text-xs text-muted' }, p.help),
      typeof p.error === 'string' && p.error && h('p', { class: 'text-xs text-error' }, p.error),
    ])
  },
})

export const NuxtLink = defineComponent({
  name: 'NuxtLink',
  inheritAttrs: false,
  props: { to: String },
  setup(p, { slots, attrs }) {
    return () => {
      const url = isConfigLink(p.to) ? toAppUrl(p.to) : p.to
      return h('a', {
        ...attrs,
        href: url,
        onClick: (e) => {
          if (!isConfigLink(p.to)) return
          e.preventDefault()
          go(p.to)
        },
      }, slots.default?.())
    }
  },
})

// Богатый редактор (tiptap) в приложении недоступен: markdown правится обычным полем, тулбар не рисуется.
export const UEditor = defineComponent({
  name: 'UEditor',
  inheritAttrs: false,
  props: { modelValue: String, editable: { type: Boolean, default: true }, placeholder: String, contentType: String },
  emits: ['update:modelValue'],
  setup(p, { emit, attrs }) {
    return () => h('textarea', {
      class: [attrs.class, 'w-full bg-default px-3 py-2 text-sm font-mono'],
      value: p.modelValue ?? '',
      disabled: !p.editable,
      placeholder: p.placeholder,
      onInput: e => emit('update:modelValue', e.target.value),
    })
  },
})
export const UEditorToolbar = defineComponent({ name: 'UEditorToolbar', setup: () => () => null })
export const ClientOnly = defineComponent({ name: 'ClientOnly', setup: (_, { slots }) => () => slots.default?.() })
