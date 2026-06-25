import { createApp, defineComponent, h, ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'

import AclPanel from './components/AclPanel.vue'

// ── Регистрация боковой панели (Details sidebar tab) ──────────────────

const AclSidebarTab = {
  id:   'acl',
  name: t('ncaclmanager', 'ACL'),
  icon: 'icon-lock',
  order: 10,

  enabled(fileInfo) {
    // Показываем только для папок
    return fileInfo.type === 'dir'
  },

  async mount(el, fileInfo, context) {
    const app = createApp({
      render: () => h(AclPanel, {
        folderPath: fileInfo.path + '/' + fileInfo.name,
        isAdmin:    window._ncAclIsAdmin ?? false,
      }),
    })

    app.mount(el)
    el._aclApp = app
  },

  async update(el, fileInfo) {
    // NC вызывает update при смене папки в sidebar
    // Передаём новый путь через custom event
    el.dispatchEvent(new CustomEvent('acl-folder-change', {
      detail: { path: fileInfo.path + '/' + fileInfo.name },
    }))
  },

  async unmount(el) {
    el._aclApp?.unmount()
  },
}

// ── Регистрация пункта контекстного меню ─────────────────────────────

const AclContextMenuAction = {
  name:        'acl',
  displayName: t('ncaclmanager', 'ACL / Права доступа'),
  iconClass:   'icon-lock',
  order:       50,

  enabled(context) {
    // Показываем только для папок и только пользователям с правами
    return context.fileInfos.length === 1
      && context.fileInfos[0].type === 'dir'
      && (window._ncAclIsAdmin || window._ncAclOwnerMode)
  },

  async exec(context) {
    const fileInfo = context.fileInfos[0]
    // Открываем sidebar на вкладке ACL
    OCA.Files.Sidebar.open(fileInfo.path + '/' + fileInfo.name)
    OCA.Files.Sidebar.setActiveTab('acl')
  },
}

// ── Инициализация ─────────────────────────────────────────────────────

async function init() {
  // Проверяем роль текущего пользователя
  try {
    const res = await axios.get(generateUrl('/apps/ncaclmanager/api/settings'))
    window._ncAclIsAdmin   = res.data.is_admin   ?? false
    window._ncAclOwnerMode = res.data.owner_mode_enabled ?? false
  } catch (e) {
    window._ncAclIsAdmin   = false
    window._ncAclOwnerMode = false
  }

  // Регистрируем боковую панель
  if (OCA?.Files?.Sidebar) {
    OCA.Files.Sidebar.registerTab(AclSidebarTab)
  }

  // Регистрируем пункт контекстного меню (NC 28+ Files API)
  if (OCA?.Files?.fileActions) {
    OCA.Files.fileActions.registerAction({
      name:        AclContextMenuAction.name,
      displayName: AclContextMenuAction.displayName,
      iconClass:   AclContextMenuAction.iconClass,
      permissions: OC.PERMISSION_READ,
      type:        OCA.Files.FileActions.TYPE_DROPDOWN,
      mime:        'dir',
      actionHandler(filename, context) {
        AclContextMenuAction.exec({
          fileInfos: [context.fileInfo || context.dir],
        })
      },
    })
  }

  // NC 29+ новый Files API (registerFileAction)
  if (OCA?.Files?.registerFileAction) {
    OCA.Files.registerFileAction({
      id:          'acl-manager',
      displayName: () => t('ncaclmanager', 'ACL / Права доступа'),
      iconSvgInline: () => `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 1C9.24 1 7 3.24 7 6v1H6c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2h-1V6c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v1H9V6c0-1.66 1.34-3 3-3zm0 9c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/>
      </svg>`,
      enabled(nodes) {
        return nodes.length === 1 && nodes[0].type === 'folder'
      },
      async exec(node) {
        OCA.Files.Sidebar.open(node.path)
        OCA.Files.Sidebar.setActiveTab('acl')
        return null
      },
      order: 50,
    })
  }
}

document.addEventListener('DOMContentLoaded', init)
