<template>
  <div class="group-row" :class="{ 'group-row--expanded': group.isExpanded }">

    <!-- Заголовок группы — кликабельный -->
    <div class="group-row__header" @click="$emit('toggle')">
      <!-- Иконка типа прав -->
      <span class="group-row__icon">{{ group.icon }}</span>

      <!-- Название и тип прав -->
      <div class="group-row__info">
        <span class="group-row__label">{{ group.label }}</span>
        <span class="group-row__sam" :title="group.samAccountName">
          {{ group.samAccountName }}
        </span>
      </div>

      <!-- Счётчик членов -->
      <NcCounterBubble v-if="group.memberCount > 0">
        {{ group.memberCount }}
      </NcCounterBubble>

      <!-- Стрелка раскрытия -->
      <ChevronDownIcon v-if="!group.isExpanded" :size="16" class="group-row__chevron" />
      <ChevronUpIcon   v-else                   :size="16" class="group-row__chevron" />
    </div>

    <!-- Раскрытое содержимое — члены группы -->
    <Transition name="expand">
      <div v-if="group.isExpanded" class="group-row__body">

        <!-- Загрузка членов -->
        <div v-if="group.membersLoading" class="group-row__members-loading">
          <NcLoadingIcon :size="16" />
          <span>{{ t('ncaclmanager', 'Загрузка...') }}</span>
        </div>

        <!-- Список членов -->
        <ul v-else-if="group.members.length" class="group-row__members">
          <li v-for="member in group.members"
              :key="member.samAccountName"
              class="group-row__member">

            <NcAvatar :user="member.samAccountName"
                      :display-name="member.displayName"
                      :size="28"
                      :show-user-status="false" />

            <div class="group-row__member-info">
              <span class="group-row__member-name">{{ member.displayName }}</span>
              <span v-if="member.title || member.department"
                    class="group-row__member-sub">
                {{ [member.title, member.department].filter(Boolean).join(' · ') }}
              </span>
            </div>

            <!-- Удалить из группы (только admin) -->
            <NcButton v-if="isAdmin"
                      type="tertiary-no-background"
                      :aria-label="t('ncaclmanager', 'Удалить из группы')"
                      @click.stop="$emit('remove-member', member.samAccountName)">
              <template #icon><CloseIcon :size="16" /></template>
            </NcButton>
          </li>
        </ul>

        <!-- Пустая группа -->
        <div v-else class="group-row__empty">
          {{ t('ncaclmanager', 'Группа пуста') }}
        </div>

        <!-- Добавить пользователя (admin или owner) -->
        <div v-if="isAdmin || isOwner" class="group-row__add">
          <UserSearchInput
            :search-query="searchQuery"
            :search-results="searchResults"
            :search-loading="searchLoading"
            :group-members="group.members"
            :placeholder="t('ncaclmanager', 'Добавить пользователя...')"
            @search="$emit('search', $event)"
            @select="$emit('add-member', $event)"
          />
        </div>

      </div>
    </Transition>
  </div>
</template>

<script setup>
import { translate as t } from '@nextcloud/l10n'
import NcButton       from '@nextcloud/vue/dist/Components/NcButton.js'
import NcAvatar       from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcLoadingIcon  from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUpIcon   from 'vue-material-design-icons/ChevronUp.vue'
import CloseIcon       from 'vue-material-design-icons/Close.vue'
import UserSearchInput from './UserSearchInput.vue'

defineProps({
  group:         { type: Object,  required: true },
  isAdmin:       { type: Boolean, default: false },
  isOwner:       { type: Boolean, default: false },
  searchQuery:   { type: String,  default: '' },
  searchResults: { type: Array,   default: () => [] },
  searchLoading: { type: Boolean, default: false },
})

defineEmits(['toggle', 'add-member', 'remove-member', 'search'])
</script>

<style scoped>
.group-row {
  border-radius: 8px;
  overflow: hidden;
  margin: 0 8px;
  border: 1px solid transparent;
  transition: border-color 0.15s;
}

.group-row--expanded {
  border-color: var(--color-border);
  background: var(--color-background-hover);
}

.group-row__header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  cursor: pointer;
  border-radius: 8px;
  user-select: none;
  transition: background 0.1s;
}

.group-row__header:hover {
  background: var(--color-background-hover);
}

.group-row__icon {
  font-size: 16px;
  width: 20px;
  text-align: center;
  flex-shrink: 0;
}

.group-row__info {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}

.group-row__label {
  font-size: 13px;
  font-weight: 500;
  color: var(--color-main-text);
}

.group-row__sam {
  font-size: 11px;
  color: var(--color-text-maxcontrast);
  font-family: monospace;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.group-row__chevron {
  color: var(--color-text-maxcontrast);
  flex-shrink: 0;
}

/* ── Тело группы ─────────────────────────────────────────────────── */

.group-row__body {
  padding: 0 12px 12px;
}

.group-row__members-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 0;
  color: var(--color-text-maxcontrast);
  font-size: 13px;
}

.group-row__members {
  list-style: none;
  padding: 0;
  margin: 0 0 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.group-row__member {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 8px;
  border-radius: 6px;
  transition: background 0.1s;
}

.group-row__member:hover {
  background: var(--color-background-dark);
}

.group-row__member-info {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}

.group-row__member-name {
  font-size: 13px;
  color: var(--color-main-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.group-row__member-sub {
  font-size: 11px;
  color: var(--color-text-maxcontrast);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.group-row__empty {
  font-size: 12px;
  color: var(--color-text-maxcontrast);
  padding: 8px 0;
  text-align: center;
}

.group-row__add {
  margin-top: 8px;
}

/* ── Анимация раскрытия ───────────────────────────────────────────── */

.expand-enter-active,
.expand-leave-active {
  transition: opacity 0.2s, transform 0.2s;
  transform-origin: top;
}

.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  transform: scaleY(0.95);
}
</style>
