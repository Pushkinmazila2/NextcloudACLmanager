<template>
  <div class="group-row" :class="{ 'group-row--expanded': group.isExpanded }">

    <div class="group-row__header" @click="$emit('toggle')">
      <span class="group-row__icon">{{ group.icon }}</span>
      <div class="group-row__info">
        <span class="group-row__label">{{ group.label }}</span>
        <span class="group-row__sam" :title="group.samAccountName">{{ group.samAccountName }}</span>
      </div>
      <span v-if="group.memberCount > 0" class="group-row__counter">{{ group.memberCount }}</span>
      <span class="group-row__chevron" :class="group.isExpanded ? 'icon-triangle-n' : 'icon-triangle-s'"></span>
    </div>

    <Transition name="expand">
      <div v-if="group.isExpanded" class="group-row__body">

        <div v-if="group.membersLoading" class="group-row__loading">
          <span class="icon-loading-small"></span> Загрузка...
        </div>

        <ul v-else-if="group.members.length" class="group-row__members">
          <li v-for="member in group.members" :key="member.samAccountName" class="group-row__member">
            <div class="group-row__member-avatar">{{ initials(member.displayName) }}</div>
            <div class="group-row__member-info">
              <span class="group-row__member-name">{{ member.displayName }}</span>
              <span v-if="member.title || member.department" class="group-row__member-sub">
                {{ [member.title, member.department].filter(Boolean).join(' · ') }}
              </span>
            </div>
            <button v-if="isAdmin"
                    class="group-row__remove-btn"
                    :title="'Удалить из группы'"
                    @click.stop="$emit('remove-member', member.samAccountName)">
              <span class="icon-close"></span>
            </button>
          </li>
        </ul>

        <div v-else class="group-row__empty">Группа пуста</div>

        <div v-if="isAdmin || isOwner" class="group-row__add">
          <UserSearchInput
            :search-query="searchQuery"
            :search-results="searchResults"
            :search-loading="searchLoading"
            :group-members="group.members"
            @search="$emit('search', $event)"
            @select="$emit('add-member', $event)"
          />
        </div>

      </div>
    </Transition>
  </div>
</template>

<script>
import UserSearchInput from './UserSearchInput.vue'

export default {
  name: 'AclGroupRow',
  components: { UserSearchInput },
  props: {
    group:         { type: Object,  required: true },
    isAdmin:       { type: Boolean, default: false },
    isOwner:       { type: Boolean, default: false },
    searchQuery:   { type: String,  default: '' },
    searchResults: { type: Array,   default: () => [] },
    searchLoading: { type: Boolean, default: false },
  },
  emits: ['toggle', 'add-member', 'remove-member', 'search'],
  methods: {
    initials(name) {
      return (name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
    },
  },
}
</script>

<style scoped>
.group-row {
  border-radius: var(--border-radius-large);
  border: 1px solid transparent;
  margin: 0 8px 2px;
  overflow: hidden;
  transition: border-color .15s;
}
.group-row--expanded { border-color: var(--color-border); background: var(--color-background-hover); }

.group-row__header {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 12px; cursor: pointer; user-select: none;
  border-radius: var(--border-radius-large);
}
.group-row__header:hover { background: var(--color-background-hover); }

.group-row__icon { font-size: 16px; width: 22px; text-align: center; flex-shrink: 0; }

.group-row__info { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.group-row__label { font-size: 13px; font-weight: 500; }
.group-row__sam { font-size: 11px; color: var(--color-text-maxcontrast); font-family: monospace; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.group-row__counter {
  background: var(--color-primary); color: #fff;
  border-radius: 10px; padding: 1px 7px; font-size: 11px; font-weight: 600;
}

.group-row__chevron { color: var(--color-text-maxcontrast); flex-shrink: 0; }

.group-row__body { padding: 0 12px 12px; }

.group-row__loading { display: flex; align-items: center; gap: 8px; padding: 8px 0; font-size: 13px; color: var(--color-text-maxcontrast); }

.group-row__members { list-style: none; padding: 0; margin: 0 0 8px; display: flex; flex-direction: column; gap: 2px; }

.group-row__member {
  display: flex; align-items: center; gap: 8px;
  padding: 5px 8px; border-radius: var(--border-radius);
}
.group-row__member:hover { background: var(--color-background-dark); }

.group-row__member-avatar {
  width: 30px; height: 30px; border-radius: 50%;
  background: var(--color-primary-light, #e8f0fe);
  color: var(--color-primary);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; flex-shrink: 0;
}

.group-row__member-info { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.group-row__member-name { font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.group-row__member-sub { font-size: 11px; color: var(--color-text-maxcontrast); }

.group-row__remove-btn {
  background: none; border: none; cursor: pointer;
  color: var(--color-text-maxcontrast); padding: 4px;
  border-radius: var(--border-radius); opacity: 0; transition: opacity .1s;
}
.group-row__member:hover .group-row__remove-btn { opacity: 1; }
.group-row__remove-btn:hover { background: var(--color-background-dark); color: var(--color-error); }

.group-row__empty { font-size: 12px; color: var(--color-text-maxcontrast); padding: 8px 0; text-align: center; }

.group-row__add { margin-top: 8px; }

.expand-enter-active, .expand-leave-active { transition: opacity .2s, transform .2s; transform-origin: top; }
.expand-enter-from, .expand-leave-to { opacity: 0; transform: scaleY(.95); }
</style>
