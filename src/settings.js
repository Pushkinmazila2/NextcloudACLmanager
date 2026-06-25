import { createApp, h } from 'vue'
import AdminSettings from './components/AdminSettings.vue'

function mount() {
  const el = document.getElementById('ncaclmanager-settings-root')
  if (!el) return
  createApp({ render: () => h(AdminSettings) }).mount(el)
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount)
} else {
  mount()
}
