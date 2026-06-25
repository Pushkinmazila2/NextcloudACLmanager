import { ref, computed } from 'vue'
import { showError, showSuccess, t } from '../api/nc.js'
import {
  getFolderGroups, createFolderGroups, deleteFolderGroups,
  getGroupMembers, addGroupMember, removeGroupMember,
  searchUsers,
} from '../api/agent.js'

export function useAcl(folderPath) {
  const loading       = ref(false)
  const groupSet      = ref(null)
  const expandedGroup = ref(null)
  const membersCache  = ref({})
  const searchQuery   = ref('')
  const searchResults = ref([])
  const searchLoading = ref(false)
  const searchTimer   = ref(null)

  const hasGroups = computed(() => {
    const g = groupSet.value
    return g && (g.RO || g.RX || g.RW)
  })

  const groupList = computed(() => {
    if (!groupSet.value) return []
    const labels = { RO: 'Только чтение', RX: 'Чтение + выполнение', RW: 'Чтение и запись' }
    const icons  = { RO: '👁', RX: '▶', RW: '✏️' }
    return ['RO', 'RX', 'RW']
      .map(s => groupSet.value[s])
      .filter(Boolean)
      .map(g => ({
        ...g,
        label:          labels[g.suffix] ?? g.suffix,
        icon:           icons[g.suffix]  ?? '🔒',
        isExpanded:     expandedGroup.value === g.samAccountName,
        members:        membersCache.value[g.samAccountName]?.members  ?? [],
        membersLoading: membersCache.value[g.samAccountName]?.loading  ?? false,
      }))
  })

  async function loadGroups() {
    if (!folderPath.value) return
    loading.value = true
    try {
      groupSet.value = await getFolderGroups(folderPath.value)
    } catch (e) {
      showError('Не удалось загрузить группы: ' + e.message)
    } finally {
      loading.value = false
    }
  }

  async function initGroups() {
    loading.value = true
    try {
      const result = await createFolderGroups(folderPath.value)
      if (result.success) {
        groupSet.value = result.groups
        showSuccess('Группы успешно созданы')
      } else {
        showError(result.errorMessage ?? 'Ошибка создания групп')
      }
    } catch (e) {
      showError('Ошибка: ' + e.message)
    } finally {
      loading.value = false
    }
  }

  async function removeAllGroups() {
    loading.value = true
    try {
      const result = await deleteFolderGroups(folderPath.value)
      if (result.success) {
        groupSet.value      = null
        expandedGroup.value = null
        membersCache.value  = {}
        showSuccess('Группы удалены')
      } else {
        showError(result.errorMessage ?? 'Ошибка удаления')
      }
    } catch (e) {
      showError('Ошибка: ' + e.message)
    } finally {
      loading.value = false
    }
  }

  async function toggleGroup(groupName) {
    if (expandedGroup.value === groupName) {
      expandedGroup.value = null
      return
    }
    expandedGroup.value = groupName
    await loadMembers(groupName)
  }

  async function loadMembers(groupName) {
    if (membersCache.value[groupName]?.members?.length) return
    membersCache.value[groupName] = { members: [], loading: true }
    try {
      const result = await getGroupMembers(groupName)
      membersCache.value[groupName] = { members: result.members ?? [], loading: false }
    } catch (e) {
      membersCache.value[groupName] = { members: [], loading: false }
      showError('Не удалось загрузить членов группы')
    }
  }

  async function addMember(groupName, user) {
    const warn = checkDuplicateAccess(user.samAccountName)
    if (warn) showError(warn)
    try {
      const result = await addGroupMember(groupName, user.samAccountName)
      if (result.success) {
        if (!membersCache.value[groupName])
          membersCache.value[groupName] = { members: [], loading: false }
        membersCache.value[groupName].members.push(user)
        searchQuery.value   = ''
        searchResults.value = []
        showSuccess(user.displayName + ' добавлен в группу')
      } else {
        showError(result.errorMessage ?? 'Ошибка добавления')
      }
    } catch (e) {
      showError('Ошибка: ' + e.message)
    }
  }

  async function removeMember(groupName, userSam) {
    try {
      const result = await removeGroupMember(groupName, userSam)
      if (result.success) {
        if (membersCache.value[groupName]) {
          membersCache.value[groupName].members =
            membersCache.value[groupName].members.filter(m => m.samAccountName !== userSam)
        }
        showSuccess('Пользователь удалён из группы')
      } else {
        showError(result.errorMessage ?? 'Ошибка удаления')
      }
    } catch (e) {
      showError('Ошибка: ' + e.message)
    }
  }

  function onSearchInput(query) {
    searchQuery.value = query
    clearTimeout(searchTimer.value)
    if (query.length < 3) { searchResults.value = []; return }
    searchTimer.value = setTimeout(() => doSearch(query), 300)
  }

  async function doSearch(query) {
    searchLoading.value = true
    try {
      const result = await searchUsers(query)
      searchResults.value = result.users ?? []
    } catch (e) {
      searchResults.value = []
    } finally {
      searchLoading.value = false
    }
  }

  function checkDuplicateAccess(userSam) {
    for (const [gName, cache] of Object.entries(membersCache.value)) {
      if (cache.members?.some(m => m.samAccountName === userSam)) {
        return `Внимание: пользователь уже имеет доступ через группу ${gName}`
      }
    }
    return null
  }

  return {
    loading, groupSet, groupList, hasGroups,
    expandedGroup, membersCache,
    searchQuery, searchResults, searchLoading,
    loadGroups, initGroups, removeAllGroups,
    toggleGroup, addMember, removeMember, onSearchInput,
  }
}
