import { defineComponent, h } from 'vue'
import AclPanel from './components/AclPanel.vue'
import { generateUrl, axios } from './api/nc.js'

// ── Состояние ─────────────────────────────────────────────────────────
let isAdmin   = false
let ownerMode = false

async function fetchUserRole() {
  try {
    const res = await axios.get(generateUrl('/apps/ncaclmanager/api/settings'))
    isAdmin   = res.data?.is_admin          ?? false
    ownerMode = res.data?.owner_mode_enabled ?? false
    console.info('[NcAclManager] Роль: isAdmin=' + isAdmin)
  } catch (e) {
    console.warn('[NcAclManager] fetchUserRole:', e.message)
  }
}

async function loadMounts() {
  try {
    const res = await axios.get(generateUrl('/apps/ncaclmanager/api/mounts'))
    window._ncAclMounts = res.data?.mounts ?? []
    console.info('[NcAclManager] Маппинги загружены:', window._ncAclMounts.length)
  } catch (e) {
    window._ncAclMounts = []
  }
}

function resolveUncPath(ncPath) {
  const mounts = window._ncAclMounts || []
  // Сортируем по длине — более специфичные маппинги первыми
  const sorted = [...mounts].sort((a, b) => b.ncPath.length - a.ncPath.length)
  for (const m of sorted) {
    const ncRoot = m.ncPath.replace(/\/$/, '')
    if (ncPath === ncRoot || ncPath.startsWith(ncRoot + '/')) {
      const rel = ncPath.slice(ncRoot.length).replace(/\//g, '\\')
      return m.uncPath.replace(/[\\]+$/, '') + rel
    }
  }
  return ncPath
}

// ── Vue компонент для Sidebar ─────────────────────────────────────────
const AclSidebarTab = defineComponent({
  name: 'AclSidebarTab',
  props: {
    node:     { type: Object, default: null },
    fileInfo: { type: Object, default: null },
  },
  setup(props) {
    return () => {
      const info = props.node || props.fileInfo
      if (!info) return h('div', { style: 'padding:16px;color:var(--color-text-maxcontrast)' }, 'Выберите папку')

      let ncPath = ''
      if (typeof info.path === 'string') {
        ncPath = info.path
      } else if (typeof info.get === 'function') {
        const p = info.get('path') || '/'
        const n = info.get('name') || ''
        ncPath  = p.replace(/\/$/, '') + '/' + n
      } else if (info.attributes?.filename) {
        ncPath = info.attributes.filename.replace(/^\/files\/[^/]+/, '')
      }

      const uncPath = resolveUncPath(ncPath)
      console.debug('[NcAclManager] sidebar render: ncPath=' + ncPath + ' → uncPath=' + uncPath)

      return h(AclPanel, { folderPath: uncPath, ncPath, isAdmin })
    }
  },
})

// ── Регистрация ───────────────────────────────────────────────────────
function register() {
  const canManage = isAdmin || ownerMode

  console.info('[NcAclManager] register(): canManage=' + canManage
    + ' Sidebar=' + !!window.OCA?.Files?.Sidebar
    + ' registerFileAction=' + !!window.OCA?.Files?.registerFileAction)

  // ── registerFileAction (NC 25+) ─────────────────────────────────────
  if (window.OCA?.Files?.registerFileAction) {
    window.OCA.Files.registerFileAction({
      id:            'acl-manager',
      displayName:   () => 'ACL / Права доступа',
      iconSvgInline: () => `<svg viewBox="0 0 24 24"><path fill="currentColor"
        d="M12 1C9.24 1 7 3.24 7 6v1H6c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h12
        c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2h-1V6c0-2.76-2.24-5-5-5zm0 2c1.66 0 3
        1.34 3 3v1H9V6c0-1.66 1.34-3 3-3zm0 9c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2
        .9-2 2-2z"/></svg>`,
      enabled: (nodes) => canManage
        && nodes?.length === 1
        && (nodes[0].type === 'folder' || nodes[0].type === 'dir'),
      async exec(node) {
        const sidebar = window.OCA?.Files?.Sidebar
        if (sidebar) {
          sidebar.open(node.path || '')
          setTimeout(() => sidebar.setActiveTab?.('acl'), 300)
        }
        return null
      },
      order: 50,
    })
    console.info('[NcAclManager] ✓ registerFileAction')
  }

  // ── Sidebar.registerTab ─────────────────────────────────────────────
  const sidebar = window.OCA?.Files?.Sidebar
  if (sidebar?.registerTab) {
    sidebar.registerTab({
      id:        'acl',
      name:      'ACL',
      icon:      'icon-lock',
      order:     10,
      enabled:   (node) => {
        if (!canManage) return false
        const type = node?.type ?? node?.get?.('type')
          ?? (node?.attributes?.resourcetype?.collection !== undefined ? 'dir' : null)
        return type === 'dir' || type === 'folder' || type === 'directory'
      },
      component: AclSidebarTab,
    })
    console.info('[NcAclManager] ✓ Sidebar.registerTab')
  }
}

// ── Ждём готовности NC Files ──────────────────────────────────────────
async function init() {
  await Promise.all([fetchUserRole(), loadMounts()])

  // Если OCA.Files уже готов — регистрируем сразу
  if (window.OCA?.Files?.Sidebar || window.OCA?.Files?.registerFileAction) {
    register()
    return
  }

  console.info('[NcAclManager] OCA.Files не готов — ждём события...')

  // NC 34 генерирует событие когда Files app инициализирован
  // Слушаем несколько вариантов имён событий
  const events = [
    'OCA.Files.App.init',          // старый NC
    'nextcloud:files:init',        // NC 28+
    'files:navigation:changed',    // NC 29+
  ]

  let registered = false

  function onFilesReady() {
    if (registered) return
    registered = true
    console.info('[NcAclManager] Files app готов (событие)')
    register()
  }

  events.forEach(ev => window.addEventListener(ev, onFilesReady, { once: true }))

  // Fallback: polling каждые 200ms до 10 секунд
  let attempts = 0
  const poll = setInterval(() => {
    attempts++
    if (window.OCA?.Files?.Sidebar || window.OCA?.Files?.registerFileAction) {
      clearInterval(poll)
      if (!registered) {
        registered = true
        console.info('[NcAclManager] Files app готов (polling, попытка ' + attempts + ')')
        register()
      }
    } else if (attempts >= 50) {
      clearInterval(poll)
      console.warn('[NcAclManager] Timeout: OCA.Files так и не появился за 10 секунд')
    }
  }, 200)
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init)
} else {
  init()
}
