<template>
  <div class="user-search" ref="rootEl">
    <div class="user-search__field">
      <span class="user-search__icon">
        <span v-if="searchLoading" class="icon-loading-small"></span>
        <span v-else class="icon-search"></span>
      </span>
      <input
        class="user-search__input"
        type="text"
        :value="searchQuery"
        :placeholder="placeholder"
        @input="$emit('search', $event.target.value)"
        @keydown.escape="$emit('search', '')"
      />
      <button v-if="searchQuery" class="user-search__clear" @click="$emit('search', '')">
        <span class="icon-close"></span>
      </button>
    </div>

    <p v-if="searchQuery.length > 0 && searchQuery.length < 3" class="user-search__hint">
      Введите минимум 3 символа
    </p>

    <ul v-if="searchResults.length > 0" class="user-search__results">
      <li v-for="user in searchResults"
          :key="user.samAccountName"
          class="user-search__result"
          :class="{ 'user-search__result--warn': hasAccess(user) }"
          @click="$emit('select', user)">
        <div class="user-search__avatar">{{ initials(user.displayName) }}</div>
        <div class="user-search__result-info">
          <span class="user-search__result-name">{{ user.displayName }}</span>
          <span class="user-search__result-meta">
            {{ user.samAccountName }}
            <template v-if="user.department"> · {{ user.department }}</template>
          </span>
          <span v-if="hasAccess(user)" class="user-search__result-warn">
            ⚠ Уже имеет доступ через другую группу
          </span>
        </div>
        <span class="icon-add user-search__add-icon"></span>
      </li>
    </ul>

    <div v-else-if="searchQuery.length >= 3 && !searchLoading" class="user-search__empty">
      Пользователи не найдены
    </div>
  </div>
</template>

<script>
export default {
  name: 'UserSearchInput',
  props: {
    searchQuery:   { type: String,  default: '' },
    searchResults: { type: Array,   default: () => [] },
    searchLoading: { type: Boolean, default: false },
    groupMembers:  { type: Array,   default: () => [] },
    placeholder:   { type: String,  default: 'Добавить пользователя...' },
  },
  emits: ['search', 'select'],
  mounted() {
    document.addEventListener('click', this.onOutsideClick)
  },
  beforeUnmount() {
    document.removeEventListener('click', this.onOutsideClick)
  },
  methods: {
    hasAccess(user) {
      return this.groupMembers.some(m => m.samAccountName === user.samAccountName)
    },
    initials(name) {
      return (name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
    },
    onOutsideClick(e) {
      if (!this.$refs.rootEl?.contains(e.target)) {
        this.$emit('search', '')
      }
    },
  },
}
</script>

<style scoped>
.user-search { position: relative; }

.user-search__field {
  display: flex;
  align-items: center;
  gap: 6px;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius);
  background: var(--color-main-background);
  padding: 4px 8px;
}

.user-search__input {
  flex: 1;
  border: none;
  background: transparent;
  color: var(--color-main-text);
  font-size: 13px;
  outline: none;
}

.user-search__clear {
  background: none;
  border: none;
  cursor: pointer;
  padding: 2px;
  color: var(--color-text-maxcontrast);
}

.user-search__hint {
  font-size: 11px;
  color: var(--color-text-maxcontrast);
  margin: 4px 0 0;
}

.user-search__results {
  position: absolute;
  top: calc(100% + 4px);
  left: 0; right: 0;
  background: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
  box-shadow: 0 4px 16px rgba(0,0,0,.12);
  z-index: 9999;
  list-style: none;
  padding: 4px;
  margin: 0;
  max-height: 260px;
  overflow-y: auto;
}

.user-search__result {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 10px;
  border-radius: var(--border-radius);
  cursor: pointer;
}
.user-search__result:hover { background: var(--color-background-hover); }
.user-search__result--warn { background: rgba(255,165,0,.08); }

.user-search__avatar {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: var(--color-primary);
  color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 600;
  flex-shrink: 0;
}

.user-search__result-info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.user-search__result-name { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-search__result-meta { font-size: 11px; color: var(--color-text-maxcontrast); }
.user-search__result-warn { font-size: 11px; color: var(--color-warning, orange); }

.user-search__add-icon { opacity: 0; transition: opacity .1s; flex-shrink: 0; }
.user-search__result:hover .user-search__add-icon { opacity: 1; }

.user-search__empty {
  padding: 10px;
  text-align: center;
  font-size: 13px;
  color: var(--color-text-maxcontrast);
}
</style>
