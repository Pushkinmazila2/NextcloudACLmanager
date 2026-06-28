import { createApp, h } from 'vue'
import AclPanel from './components/AclPanel.vue'
import { generateUrl, axios } from './api/nc.js'

// ── Состояние пользователя ────────────────────────────────────────────
let isAdmin   = false
let ownerMode = false

async function fetchUserRole() {
  try {
    const res = await axios.get(generateUrl('/apps/ncaclmanager/api/settings'))
    isAdmin   = res.data?.is_admin          ?? false
    ownerMode = res.data?.owner_mode_enabled ?? false
  } catch (e) {
    console.warn('[NcAclManager] fetchUserRole:', e.message)
  }
}

// ── Маппинг NC пути → UNC путь ────────────────────────────────────────
// NC хранит внешние хранилища как /mountpoint/...
// Агент ожидает \\SERVER\Share\...
// Маппинги хранятся в window._ncAclMounts (заполняется из PHP через data-атрибут или API)
function resolveUncPath(ncPath) {
  const mounts = window._ncAclMounts || []
  for (const m of mounts) {
    if (ncPath.startsWith(m.ncPath)) {
      const relative = ncPath.slice(m.ncPath.length)
      return m.uncPath.replace(/\/$/, '') + relative.replace(/\//g, '\\')
    }
  }
  // Нет маппинга — возвращаем как есть (для локальных папок)
  return ncPath
}

// ── Монтирование Vue панели ───────────────────────────────────────────
function mountPanel(el, ncPath) {
  if (el._aclApp) {
    try { el._aclApp.unmount() } catch (_) {}
    el._aclApp = null
  }
  const uncPath = resolveUncPath(ncPath)
  const app = createApp({
    render: () => h(AclPanel, {
      folderPath: uncPath,
      ncPath,
      isAdmin,
    }),
  })
  app.mount(el)
  el._aclApp = app
}

function unmountPanel(el) {
  if (el._aclApp) {
    try { el._aclApp.unmount() } catch (_) {}
    el._aclApp = null
  }
}

function getNodePath(node) {
  // NC 34 Files API — node это FileInfo объект
  if (!node) return ''
  // Новый API (NC 29+): node.path
  if (typeof node.path === 'string') return node.path
  // Старый API: node.get('path') + '/' + node.get('name')
  if (typeof node.get === 'function') {
    const path = node.get('path') || '/'
    const name = node.get('name') || ''
    return path.replace(/\/$/, '') + '/' + name
  }
  return ''
}

// ── Инициализация ─────────────────────────────────────────────────────
async function init() {
  await fetchUserRole()
  const canManage = isAdmin || ownerMode

  // Загружаем маппинги внешних хранилищ
  try {
    const res = await axios.get(generateUrl('/apps/ncaclmanager/api/mounts'))
    window._ncAclMounts = res.data?.mounts ?? []
    console.info('[NcAclManager] Маппинги загружены:', window._ncAclMounts)
  } catch (e) {
    console.warn('[NcAclManager] Не удалось загрузить маппинги:', e.message)
    window._ncAclMounts = []
  }

  // ── NC 29-34: registerFileAction ──────────────────────────────────
  // Это основной способ добавить действие в контекстное меню в NC 29+
  if (canManage && window.OCA?.Files?.registerFileAction) {
    window.OCA.Files.registerFileAction({
      id:          'acl-manager',
      displayName: () => 'ACL / Права доступа',
      iconSvgInline: () => `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path fill="currentColor" d="M12 1C9.24 1 7 3.24 7 6v1H6c-1.1 0-2 .9-2
          2v11c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2h-1V6c0-2.76-2.24-5-5-5zm0
          2c1.66 0 3 1.34 3 3v1H9V6c0-1.66 1.34-3 3-3zm0 9c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2
          .9-2 2-2z"/>
      </svg>`,
      enabled: (nodes) => {
        if (!canManage || !nodes?.length) return false
        return nodes[0].type === 'folder' || nodes[0].type === 'dir'
      },
      async exec(node) {
        // Открываем sidebar и переходим на вкладку ACL
        if (window.OCA?.Files?.Sidebar) {
          const path = getNodePath(node)
          window.OCA.Files.Sidebar.open(path)
          // В NC 34 setActiveTab может называться иначе
          setTimeout(() => {
            const sb = window.OCA.Files.Sidebar
            if (typeof sb.setActiveTab === 'function') sb.setActiveTab('acl')
            else if (typeof sb.open === 'function') sb.open(path)
          }, 200)
        }
        return null
      },
      order: 50,
    })
    console.info('[NcAclManager] registerFileAction зарегистрирован')
  }

  // ── NC 34 Sidebar Tab ─────────────────────────────────────────────
  // В NC 29+ registerTab принимает экземпляр Tab класса
  const Sidebar = window.OCA?.Files?.Sidebar

  if (Sidebar) {
    // Способ 1: NC 29-34 — new Sidebar.Tab(...)
    if (typeof Sidebar.Tab === 'function') {
      const tab = new Sidebar.Tab({
        id:   'acl',
        name: 'ACL',
        icon: 'icon-lock',
        component: {
          // Vue компонент передаётся напрямую
          render() {
            return h('div', { id: 'acl-tab-inner' })
          },
          mounted() {
            // монтируем AclPanel в наш div
          },
        },
      })
      Sidebar.registerTab(tab)
      console.info('[NcAclManager] Sidebar.Tab зарегистрирован (NC 29+ API)')
    }
    // Способ 2: NC 28 — объект с методами
    else if (typeof Sidebar.registerTab === 'function') {
      Sidebar.registerTab({
        id:    'acl',
        name:  'ACL',
        icon:  'icon-lock',
        order: 10,

        enabled(fileInfo) {
          if (!canManage) return false
          const type = fileInfo?.type ?? fileInfo?.get?.('type')
          return type === 'dir' || type === 'folder'
        },
        mount(el, fileInfo) {
          const path = getNodePath(fileInfo)
          mountPanel(el, path)
        },
        update(el, fileInfo) {
          const path = getNodePath(fileInfo)
          mountPanel(el, path)
        },
        unmount(el) {
          unmountPanel(el)
        },
      })
      console.info('[NcAclManager] Sidebar.registerTab зарегистрирован (NC 28 API)')
    } else {
      console.warn('[NcAclManager] Sidebar API не найден')
    }
  }

  // ── NC 28 legacy fileActions ──────────────────────────────────────
  if (canManage
    && window.OCA?.Files?.fileActions
    && typeof window.OCA.Files.fileActions.registerAction === 'function') {

    window.OCA.Files.fileActions.registerAction({
      name:        'acl-manager',
      displayName: 'ACL / Права доступа',
      iconClass:   'icon-lock',
      permissions: window.OC?.PERMISSION_READ ?? 1,
      type:        window.OCA.Files.FileActions?.TYPE_DROPDOWN,
      mime:        'dir',
      order:       50,
      actionHandler(filename, ctx) {
        const dir  = ctx?.$file?.data('path') ?? ctx?.dir ?? '/'
        const path = dir.replace(/\/$/, '') + '/' + filename
        if (Sidebar) {
          Sidebar.open(path)
          setTimeout(() => Sidebar.setActiveTab?.('acl'), 200)
        }
      },
    })
    console.info('[NcAclManager] fileActions.registerAction зарегистрирован (legacy)')
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init)
} else {
  init()
}