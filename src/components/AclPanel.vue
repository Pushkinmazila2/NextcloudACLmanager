<template>
  <div class="acl-panel">
    <!-- Заголовок -->
    <div class="acl-panel__header">
      <LockIcon :size="20" />
      <span class="acl-panel__title">{{ t('ncaclmanager', 'ACL / Права доступа') }}</span>
      <NcLoadingIcon v-if="loading" :size="16" />
    </div>

    <!-- Путь текущей папки -->
    <div class="acl-panel__path">
      <FolderIcon :size="14" />
      <span class="acl-panel__path-text" :title="folderPath">{{ shortPath }}</span>
    </div>

    <!-- Нет групп — предлагаем создать (только admin) -->
    <template v-if="!loading && !hasGroups">
      <div class="acl-panel__empty">
        <p>{{ t('ncaclmanager', 'Для этой папки не настроены группы доступа') }}</p>
        <NcButton v-if="isAdmin"
                  type="primary"
                  :loading="loading"
                  @click="initGroups">
          <template #icon><PlusIcon :size="16" /></template>
          {{ t('ncaclmanager', 'Создать группы доступа') }}
        </NcButton>
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
          :search-query="searchQuery"
          :search-results="searchResults"
          :search-loading="searchLoading"
          @toggle="toggleGroup(group.samAccountName)"
          @add-member="addMember(group.samAccountName, $event)"
          @remove-member="removeMember(group.samAccountName, $event)"
          @search="onSearchInput"
        />
      </div>

      <!-- Удалить все группы (только admin) -->
      <div v-if="isAdmin" class="acl-panel__footer">
        <NcButton type="error"
                  :loading="loading"
                  @click="confirmDelete">
          <template #icon><TrashIcon :size="16" /></template>
          {{ t('ncaclmanager', 'Удалить все группы') }}
        </NcButton>
      </div>
    </template>

    <!-- Скелетон при загрузке -->
    <template v-else>
      <div class="acl-panel__skeleton">
        <div v-for="i in 3" :key="i" class="acl-panel__skeleton-row" />
      </div>
    </template>

    <!-- Диалог подтверждения удаления -->
    <NcDialog v-if="showDeleteDialog"
              :name="t('ncaclmanager', 'Удалить группы')"
              :message="t('ncaclmanager', 'Все группы доступа для этой папки будут удалены. Это также уберёт права из NTFS ACL. Продолжить?')"
              @closing="showDeleteDialog = false">
      <template #actions>
        <NcButton type="error" @click="doDelete">
          {{ t('ncaclmanager', 'Удалить') }}
        </NcButton>
        <NcButton @click="showDeleteDialog = false">
          {{ t('ncaclmanager', 'Отмена') }}
        </NcButton>
      </template>
    </NcDialog>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import NcButton      from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog      from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import LockIcon   from 'vue-material-design-icons/Lock.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import PlusIcon   from 'vue-material-design-icons/Plus.vue'
import TrashIcon  from 'vue-material-design-icons/Delete.vue'

import AclGroupRow from './AclGroupRow.vue'
import { useAcl }  from '../composables/useAcl.js'

// ── Props ─────────────────────────────────────────────────────────────
const props = defineProps({
  /** Полный путь к текущей папке */
  folderPath: { type: String, required: true },
  /** Является ли текущий NC пользователь ACL-admin */
  isAdmin:    { type: Boolean, default: false },
})

// ── Composable ────────────────────────────────────────────────────────
const folderPathRef = computed(() => props.folderPath)
const {
  loading, groupList, hasGroups,
  searchQuery, searchResults, searchLoading,
  loadGroups, initGroups, removeAllGroups,
  toggleGroup, addMember, removeMember,
  onSearchInput,
} = useAcl(folderPathRef)

// ── Локальное состояние ───────────────────────────────────────────────
const showDeleteDialog = ref(false)

// ── Вычисляемые ───────────────────────────────────────────────────────
const shortPath = computed(() => {
  const parts = props.folderPath.split('/')
  return parts.length > 3
    ? '.../' + parts.slice(-2).join('/')
    : props.folderPath
})

// ── Жизненный цикл ───────────────────────────────────────────────────
onMounted(() => loadGroups())
watch(() => props.folderPath, () => loadGroups())

// ── Методы ───────────────────────────────────────────────────────────
function confirmDelete() {
  showDeleteDialog.value = true
}
async function doDelete() {
  showDeleteDialog.value = false
  await removeAllGroups()
}
</script>

<style scoped>
.acl-panel {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 0;
}

.acl-panel__header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  font-size: 14px;
  color: var(--color-main-text);
  padding: 0 12px;
}

.acl-panel__title {
  flex: 1;
}

.acl-panel__path {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 12px;
  background: var(--color-background-hover);
  border-radius: 6px;
  margin: 0 8px;
}

.acl-panel__path-text {
  font-size: 11px;
  color: var(--color-text-maxcontrast);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-family: monospace;
}

.acl-panel__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 24px 12px;
  color: var(--color-text-maxcontrast);
  text-align: center;
}

.acl-panel__groups {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.acl-panel__footer {
  padding: 8px 12px 0;
  border-top: 1px solid var(--color-border);
  margin-top: 4px;
}

.acl-panel__skeleton {
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.acl-panel__skeleton-row {
  height: 44px;
  background: var(--color-background-hover);
  border-radius: 6px;
  animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.4; }
}
</style>
