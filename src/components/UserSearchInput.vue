<template>
  <div class="user-search" v-click-outside="close">

    <!-- Поле ввода -->
    <NcTextField
      :value="searchQuery"
      :placeholder="placeholder"
      :label="t('ncaclmanager', 'Поиск пользователя')"
      :label-visible="false"
      trailing-button-icon="close"
      :show-trailing-button="searchQuery.length > 0"
      @update:value="$emit('search', $event)"
      @trailing-button-click="$emit('search', '')">
      <template #icon>
        <NcLoadingIcon v-if="searchLoading" :size="16" />
        <AccountSearchIcon v-else :size="16" />
      </template>
    </NcTextField>

    <!-- Подсказка минимум символов -->
    <p v-if="searchQuery.length > 0 && searchQuery.length < 3"
       class="user-search__hint">
      {{ t('ncaclmanager', 'Введите минимум 3 символа') }}
    </p>

    <!-- Дропдаун результатов -->
    <ul v-if="searchResults.length > 0" class="user-search__results">
      <li v-for="user in searchResults"
          :key="user.samAccountName"
          class="user-search__result"
          :class="{ 'user-search__result--warn': hasAccess(user) }"
          @click="select(user)">

        <NcAvatar :user="user.samAccountName"
                  :display-name="user.displayName"
                  :size="32"
                  :show-user-status="false" />

        <div class="user-search__result-info">
          <span class="user-search__result-name">{{ user.displayName }}</span>
          <span class="user-search__result-meta">
            {{ user.samAccountName }}
            <template v-if="user.department"> · {{ user.department }}</template>
          </span>
          <!-- Предупреждение о дублирующемся доступе -->
          <span v-if="hasAccess(user)"
                class="user-search__result-warn">
            ⚠ {{ t('ncaclmanager', 'Уже имеет доступ через другую группу') }}
          </span>
        </div>

        <AddIcon :size="16" class="user-search__result-add" />
      </li>
    </ul>

    <!-- Нет результатов -->
    <div v-else-if="searchQuery.length >= 3 && !searchLoading"
         class="user-search__no-results">
      {{ t('ncaclmanager', 'Пользователи не найдены') }}
    </div>

  </div>
</template>

<script setup>
import { translate as t } from '@nextcloud/l10n'
import NcTextField   from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcAvatar      from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import AccountSearchIcon from 'vue-material-design-icons/AccountSearch.vue'
import AddIcon           from 'vue-material-design-icons/AccountPlus.vue'

const props = defineProps({
  searchQuery:   { type: String,  default: '' },
  searchResults: { type: Array,   default: () => [] },
  searchLoading: { type: Boolean, default: false },
  groupMembers:  { type: Array,   default: () => [] },
  placeholder:   { type: String,  default: 'Поиск...' },
})

const emit = defineEmits(['search', 'select'])

// Есть ли у пользователя уже доступ в текущей группе
function hasAccess(user) {
  return props.groupMembers.some(m => m.samAccountName === user.samAccountName)
}

function select(user) {
  emit('select', user)
}

function close() {
  if (props.searchQuery) emit('search', '')
}

// Директива v-click-outside
const vClickOutside = {
  mounted(el, binding) {
    el._clickOutside = (e) => { if (!el.contains(e.target)) binding.value() }
    document.addEventListener('click', el._clickOutside)
  },
  unmounted(el) {
    document.removeEventListener('click', el._clickOutside)
  },
}
</script>

<style scoped>
.user-search {
  position: relative;
}

.user-search__hint {
  font-size: 11px;
  color: var(--color-text-maxcontrast);
  margin: 4px 0 0;
  padding-left: 4px;
}

.user-search__results {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  box-shadow: 0 4px 16px rgba(0,0,0,0.12);
  z-index: 1000;
  list-style: none;
  padding: 4px;
  margin: 0;
  max-height: 280px;
  overflow-y: auto;
}

.user-search__result {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.1s;
}

.user-search__result:hover {
  background: var(--color-background-hover);
}

.user-search__result--warn {
  background: rgba(var(--color-warning-rgb), 0.08);
}

.user-search__result--warn:hover {
  background: rgba(var(--color-warning-rgb), 0.15);
}

.user-search__result-info {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}

.user-search__result-name {
  font-size: 13px;
  font-weight: 500;
  color: var(--color-main-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-search__result-meta {
  font-size: 11px;
  color: var(--color-text-maxcontrast);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-search__result-warn {
  font-size: 11px;
  color: var(--color-warning);
  margin-top: 2px;
}

.user-search__result-add {
  color: var(--color-primary);
  flex-shrink: 0;
  opacity: 0;
  transition: opacity 0.1s;
}

.user-search__result:hover .user-search__result-add {
  opacity: 1;
}

.user-search__no-results {
  padding: 12px;
  text-align: center;
  font-size: 13px;
  color: var(--color-text-maxcontrast);
}
</style>
