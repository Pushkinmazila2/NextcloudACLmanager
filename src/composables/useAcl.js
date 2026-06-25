import { ref, computed } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import {
  getFolderGroups, createFolderGroups, deleteFolderGroups,
  getGroupMembers, addGroupMember, removeGroupMember,
  searchUsers,
} from '../api/agent.js'

export function useAcl(folderPath) {
  // ── Состояние ─────────────────────────────────────────────────────
  const loading       = ref(false)
  const groupSet      = ref(null)   // { folderPath, RO, RX, RW }
  const expandedGroup = ref(null)   // sAMAccountName открытой группы
  const membersCache  = ref({})     // groupName → { members, loading }
  const searchQuery   = ref('')
  const searchResults = ref([])
  const searchLoading = ref(false)
  const searchTimer   = ref(null)

  // ── Вычисляемые ───────────────────────────────────────────────────

  const hasGroups = computed(() => groupSet.value?.hasAny ?? false)

  const groupList = computed(() => {
    if (!groupSet.value) return []
    const suffixLabel = { RO: 'Только чтение', RX: 'Чтение + выполнение', RW: 'Чтение и запись' }
    const permIcon    = { RO: '👁', RX: '▶', RW: '✏️' }
    return ['RO', 'RX', 'RW']
      .map(s => groupSet.value[s])
      .filter(Boolean)
      .map(g => ({
        ...g,
        label:       suffixLabel[g.suffix] ?? g.suffix,
        icon:        permIcon[g.suffix]    ?? '🔒',
        isExpanded:  expandedGroup.value === g.samAccountName,
        members:     membersCache.value[g.samAccountName]?.members ?? [],
        membersLoading: membersCache.value[g.samAccountName]?.loading ?? false,
      }))
  })

  // ── Загрузка групп ────────────────────────────────────────────────

  async function loadGroups() {
    if (!folderPath.value) return
    loading.value = true
    try {
      groupSet.value = await getFolderGroups(folderPath.value)
    } catch (e) {
      showError(t('ncaclmanager', 'Не удалось загрузить группы: ') + e.message)
    } finally {
      loading.value = false
    }
  }

  // ── Создание групп для папки ──────────────────────────────────────

  async function initGroups() {
    loading.value = true
    try {
      const result = await createFolderGroups(folderPath.value)
      if (result.success) {
        groupSet.value = result.groups
        showSuccess(t('ncaclmanager', 'Группы успешно созданы'))
      } else {
        showError(result.errorMessage ?? t('ncaclmanager', 'Ошибка создания групп'))
      }
    } catch (e) {
      showError(t('ncaclmanager', 'Ошибка: ') + e.message)
    } finally {
      loading.value = false
    }
  }

  // ── Удаление всех групп папки ─────────────────────────────────────

  async function removeAllGroups() {
    loading.value = true
    try {
      const result = await deleteFolderGroups(folderPath.value)
      if (result.success) {
        groupSet.value    = null
        expandedGroup.value = null
        membersCache.value  = {}
        showSuccess(t('ncaclmanager', 'Группы удалены'))
      } else {
        showError(result.errorMessage ?? t('ncaclmanager', 'Ошибка удаления'))
      }
    } catch (e) {
      showError(t('ncaclmanager', 'Ошибка: ') + e.message)
    } finally {
      loading.value = false
    }
  }

  // ── Раскрытие группы / загрузка членов ───────────────────────────

  async function toggleGroup(groupName) {
    if (expandedGroup.value === groupName) {
      expandedGroup.value = null
      return
    }
    expandedGroup.value = groupName
    await loadMembers(groupName)
  }

  async function loadMembers(groupName) {
    if (membersCache.value[groupName]?.members?.length) return // уже загружены

    membersCache.value[groupName] = { members: [], loading: true }
    try {
      const result = await getGroupMembers(groupName)
      membersCache.value[groupName] = { members: result.members ?? [], loading: false }
    } catch (e) {
      membersCache.value[groupName] = { members: [], loading: false }
      showError(t('ncaclmanager', 'Не удалось загрузить членов группы'))
    }
  }

  // ── Добавление члена группы ───────────────────────────────────────

  async function addMember(groupName, user) {
    // Предупреждение если пользователь уже имеет доступ через другую группу
    const warning = checkDuplicateAccess(user.samAccountName)
    if (warning) {
      // Показываем предупреждение но не блокируем
      showError(warning, { timeout: 5000 })
    }

    try {
      const result = await addGroupMember(groupName, user.samAccountName)
      if (result.success) {
        // Обновляем кэш
        if (!membersCache.value[groupName]) {
          membersCache.value[groupName] = { members: [], loading: false }
        }
        membersCache.value[groupName].members.push(user)
        searchQuery.value   = ''
        searchResults.value = []
        showSuccess(t('ncaclmanager', '{user} добавлен в группу', { user: user.displayName }))
      } else {
        showError(result.errorMessage ?? t('ncaclmanager', 'Ошибка добавления'))
      }
    } catch (e) {
      showError(t('ncaclmanager', 'Ошибка: ') + e.message)
    }
  }

  // ── Удаление члена группы ─────────────────────────────────────────

  async function removeMember(groupName, userSam) {
    try {
      const result = await removeGroupMember(groupName, userSam)
      if (result.success) {
        if (membersCache.value[groupName]) {
          membersCache.value[groupName].members =
            membersCache.value[groupName].members.filter(m => m.samAccountName !== userSam)
        }
        showSuccess(t('ncaclmanager', 'Пользователь удалён из группы'))
      } else {
        showError(result.errorMessage ?? t('ncaclmanager', 'Ошибка удаления'))
      }
    } catch (e) {
      showError(t('ncaclmanager', 'Ошибка: ') + e.message)
    }
  }

  // ── Поиск пользователей (debounce 300ms) ──────────────────────────

  function onSearchInput(query) {
    searchQuery.value = query
    clearTimeout(searchTimer.value)

    if (query.length < 3) {
      searchResults.value = []
      return
    }

    searchTimer.value = setTimeout(() => doSearch(query), 300)
  }

  async function doSearch(query) {
    searchLoading.value = true
    try {
      const result    = await searchUsers(query)
      searchResults.value = result.users ?? []
    } catch (e) {
      searchResults.value = []
    } finally {
      searchLoading.value = false
    }
  }

  // ── Проверка дублирующего доступа ────────────────────────────────

  function checkDuplicateAccess(userSam) {
    for (const [gName, cache] of Object.entries(membersCache.value)) {
      if (cache.members?.some(m => m.samAccountName === userSam)) {
        return t('ncaclmanager',
          'Внимание: пользователь уже имеет доступ через группу {group}',
          { group: gName })
      }
    }
    return null
  }

  return {
    // Состояние
    loading, groupSet, groupList, hasGroups,
    expandedGroup, membersCache,
    searchQuery, searchResults, searchLoading,

    // Действия
    loadGroups, initGroups, removeAllGroups,
    toggleGroup, addMember, removeMember,
    onSearchInput,
    checkDuplicateAccess,
  }
}
