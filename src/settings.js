import { createApp, h } from 'vue'
import AdminSettings from './components/AdminSettings.vue'

document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('ncaclmanager-settings-root')
  if (!el) return
  createApp({ render: () => h(AdminSettings) }).mount(el)
})
