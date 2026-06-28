import { createApp, h } from 'vue'
import AdminSettings from './components/AdminSettings.vue'

function mount() {
  const el = document.getElementById('ncaclmanager-settings-root')
  if (!el) {
    // Элемент ещё не в DOM — пробуем через MutationObserver
    const observer = new MutationObserver(() => {
      const target = document.getElementById('ncaclmanager-settings-root')
      if (target) {
        observer.disconnect()
        mountTo(target)
      }
    })
    observer.observe(document.body, { childList: true, subtree: true })
    // Таймаут на случай если NC рендерит страницу с задержкой
    setTimeout(() => {
      observer.disconnect()
      const target = document.getElementById('ncaclmanager-settings-root')
      if (target && !target._vueApp) mountTo(target)
    }, 2000)
    return
  }
  mountTo(el)
}

function mountTo(el) {
  if (el._vueApp) return // уже смонтировано
  const app = createApp({ render: () => h(AdminSettings) })
  app.mount(el)
  el._vueApp = app
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount)
} else {
  mount()
}
