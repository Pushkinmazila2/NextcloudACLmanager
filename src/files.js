import { createApp, h } from 'vue'
import AclPanel from './components/AclPanel.vue'
import { generateUrl, axios } from './api/nc.js'

async function fetchUserRole() {
  try {
    const res = await axios.get(generateUrl('/apps/ncaclmanager/api/settings'))
    window._ncAclIsAdmin   = res.data?.is_admin          ?? false
    window._ncAclOwnerMode = res.data?.owner_mode_enabled ?? false
  } catch (e) {
    console.warn('[NcAclManager] Не удалось получить роль пользователя:', e.message)
    window._ncAclIsAdmin   = false
    window._ncAclOwnerMode = false
  }
}

function mountAclPanel(el, folderPath) {
  // Размонтируем предыдущий инстанс если есть
  if (el._aclApp) {
    el._aclApp.unmount()
    el._aclApp = null
  }

  const app = createApp({
    render: () => h(AclPanel, {
      folderPath,
      isAdmin: window._ncAclIsAdmin ?? false,
    }),
  })
  app.mount(el)
  el._aclApp = app
}

function openAclSidebar(filePath) {
  if (window.OCA?.Files?.Sidebar) {
    OCA.Files.Sidebar.open(filePath)
    // Небольшая задержка чтобы sidebar успел открыться
    setTimeout(() => {
      OCA.Files.Sidebar.setActiveTab?.('acl')
    }, 100)
  }
}

async function init() {
  await fetchUserRole()

  // ── NC 28+ Sidebar Tab API ──────────────────────────────────────────
  if (window.OCA?.Files?.Sidebar) {

    // NC 28 использует registerTab с объектом
    OCA.Files.Sidebar.registerTab({
      id:    'acl',
      name:  'ACL',
      icon:  'icon-lock',
      order: 10,

      enabled(fileInfo) {
        // Показываем вкладку только для папок
        return fileInfo?.type === 'dir'
          && (window._ncAclIsAdmin || window._ncAclOwnerMode)
      },

      mount(el, fileInfo, context) {
        const path = String(fileInfo?.path ?? '') + '/' + String(fileInfo?.name ?? '')
        mountAclPanel(el, path)
      },

      update(el, fileInfo) {
        const path = String(fileInfo?.path ?? '') + '/' + String(fileInfo?.name ?? '')
        // Если панель уже смонтирована — пересоздаём с новым путём
        mountAclPanel(el, path)
      },

      unmount(el) {
        if (el._aclApp) {
          el._aclApp.unmount()
          el._aclApp = null
        }
      },
    })
  }

  // ── NC 29+ Files registerFileAction ────────────────────────────────
  if (window.OCA?.Files?.registerFileAction && typeof OCA.Files.registerFileAction === 'function') {
    OCA.Files.registerFileAction({
      id:          'acl-manager',
      displayName: () => 'ACL / Права доступа',
      iconSvgInline: () => `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path fill="currentColor" d="M12 1C9.24 1 7 3.24 7 6v1H6c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2h-1V6c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v1H9V6c0-1.66 1.34-3 3-3zm0 9c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/>
      </svg>`,

      enabled(nodes) {
        if (!nodes?.length) return false
        const node = nodes[0]
        return node.type === 'folder'
          && (window._ncAclIsAdmin || window._ncAclOwnerMode)
      },

      async exec(node) {
        openAclSidebar(node.path)
        return null
      },
      order: 50,
    })
  }

  // ── NC 28 legacy fileActions ────────────────────────────────────────
  if (window.OCA?.Files?.fileActions
      && typeof OCA.Files.fileActions.registerAction === 'function') {

    OCA.Files.fileActions.registerAction({
      name:        'acl-manager',
      displayName: 'ACL / Права доступа',
      iconClass:   'icon-lock',
      permissions: OC.PERMISSION_READ,
      type:        OCA.Files.FileActions.TYPE_DROPDOWN,
      mime:        'dir',
      order:       50,

      actionHandler(filename, context) {
        const dir  = context?.$file?.data('path') ?? context?.dir ?? '/'
        const path = dir.replace(/\/$/, '') + '/' + filename
        openAclSidebar(path)
      },
    })
  }
}

// Ждём полной загрузки страницы NC
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init)
} else {
  init()
}
