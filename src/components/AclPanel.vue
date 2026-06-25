<template>
  <div class="acl-panel">
    <div class="acl-panel__header">
      <span class="icon-lock"></span>
      <span class="acl-panel__title">ACL / Права доступа</span>
      <span v-if="loading" class="icon-loading-small"></span>
    </div>

    <div class="acl-panel__path" :title="folderPath">
      <span class="icon-folder"></span>
      <span class="acl-panel__path-text">{{ shortPath }}</span>
    </div>

    <!-- Нет групп -->
    <template v-if="!loading && !hasGroups">
      <div class="acl-panel__empty">
        <p>Для этой папки не настроены группы доступа</p>
        <button v-if="isAdmin" class="button primary acl-panel__init-btn" :disabled="loading" @click="initGroups">
          Создать группы доступа (RO / RX / RW)
        </button>
      </div>
    </template>

    <!-- Список групп -->
    <template v-else-if="!loading">
      <div class="acl-panel__groups">
        <AclGroupRow
          v-for="group in groupList"
          :key="group.samAccountName"
          :group="group"
          :is-admin="isAdmin"
          :search-query="activeSearch === group.samAccountName ? searchQuery : ''"
          :search-results="activeSearch === group.samAccountName ? searchResults : []"
          :search-loading="searchLoading"
          @toggle="toggleGroup(group.samAccountName)"
          @add-member="addMember(group.samAccountName, $event)"
          @remove-member="removeMember(group.samAccountName, $event)"
          @search="q => { activeSearch = group.samAccountName; onSearchInput(q) }"
        />
      </div>

      <div v-if="isAdmin" class="acl-panel__footer">
        <button class="button" @click="confirmingDelete = true">Удалить все группы</button>
      </div>
    </template>

    <!-- Скелетон -->
    <template v-else>
      <div class="acl-panel__skeleton">
        <div v-for="i in 3" :key="i" class="acl-panel__skeleton-row"></div>
      </div>
    </template>

    <!-- Диалог подтверждения удаления -->
    <div v-if="confirmingDelete" class="acl-panel__dialog-overlay" @click.self="confirmingDelete = false">
      <div class="acl-panel__dialog">
        <h3>Удалить группы?</h3>
        <p>Все группы доступа для этой папки будут удалены вместе с NTFS правами. Продолжить?</p>
        <div class="acl-panel__dialog-actions">
          <button class="button primary" @click="doDelete">Удалить</button>
          <button class="button" @click="confirmingDelete = false">Отмена</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue'
import AclGroupRow from './AclGroupRow.vue'
import { useAcl } from '../composables/useAcl.js'

export default {
  name: 'AclPanel',
  components: { AclGroupRow },
  props: {
    folderPath: { type: String, required: true },
    isAdmin:    { type: Boolean, default: false },
  },
  setup(props) {
    const folderPathRef   = computed(() => props.folderPath)
    const confirmingDelete = ref(false)
    const activeSearch     = ref(null)

    const {
      loading, groupList, hasGroups,
      searchQuery, searchResults, searchLoading,
      loadGroups, initGroups, removeAllGroups,
      toggleGroup, addMember, removeMember, onSearchInput,
    } = useAcl(folderPathRef)

    const shortPath = computed(() => {
      const parts = props.folderPath.split('/')
      return parts.length > 3 ? '.../' + parts.slice(-2).join('/') : props.folderPath
    })

    async function doDelete() {
      confirmingDelete.value = false
      await removeAllGroups()
    }

    onMounted(() => loadGroups())
    watch(() => props.folderPath, () => loadGroups())

    return {
      loading, groupList, hasGroups, shortPath,
      searchQuery, searchResults, searchLoading, activeSearch,
      confirmingDelete,
      initGroups, toggleGroup, addMember, removeMember, onSearchInput, doDelete,
    }
  },
}
</script>

<style scoped>
.acl-panel { display: flex; flex-direction: column; gap: 6px; padding: 10px 0; }

.acl-panel__header {
  display: flex; align-items: center; gap: 8px;
  font-weight: 600; font-size: 14px; padding: 0 12px;
}
.acl-panel__title { flex: 1; }

.acl-panel__path {
  display: flex; align-items: center; gap: 4px;
  padding: 4px 10px; background: var(--color-background-hover);
  border-radius: var(--border-radius); margin: 0 8px;
}
.acl-panel__path-text {
  font-size: 11px; color: var(--color-text-maxcontrast);
  font-family: monospace; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

.acl-panel__empty {
  display: flex; flex-direction: column; align-items: center;
  gap: 12px; padding: 24px 12px; color: var(--color-text-maxcontrast); text-align: center;
}
.acl-panel__init-btn { min-width: 180px; }

.acl-panel__groups { display: flex; flex-direction: column; gap: 2px; }

.acl-panel__footer { padding: 8px 12px 0; border-top: 1px solid var(--color-border); margin-top: 4px; }

.acl-panel__skeleton { padding: 8px 12px; display: flex; flex-direction: column; gap: 8px; }
.acl-panel__skeleton-row {
  height: 44px; background: var(--color-background-hover);
  border-radius: var(--border-radius-large);
  animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

.acl-panel__dialog-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,.5);
  display: flex; align-items: center; justify-content: center; z-index: 9999;
}
.acl-panel__dialog {
  background: var(--color-main-background); border-radius: var(--border-radius-large);
  padding: 24px; max-width: 380px; width: 90%; box-shadow: 0 8px 32px rgba(0,0,0,.2);
}
.acl-panel__dialog h3 { margin: 0 0 12px; font-size: 16px; }
.acl-panel__dialog p  { margin: 0 0 20px; color: var(--color-text-maxcontrast); }
.acl-panel__dialog-actions { display: flex; gap: 8px; justify-content: flex-end; }
</style>
