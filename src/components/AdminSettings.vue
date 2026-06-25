<template>
  <div class="acl-settings">
    <h2>{{ t('ncaclmanager', 'ACL Manager — Настройки') }}</h2>

    <NcNoteCard v-if="saveSuccess" type="success" class="acl-settings__note">
      {{ t('ncaclmanager', 'Настройки сохранены') }}
    </NcNoteCard>

    <!-- ── Секция: Агент ──────────────────────────────────────────── -->
    <section class="acl-settings__section">
      <h3>{{ t('ncaclmanager', 'Подключение к агенту') }}</h3>

      <NcTextField
        :value="form.agentUrl"
        :label="t('ncaclmanager', 'URL агента')"
        :placeholder="'https://10.0.1.50:8443'"
        :helper-text="t('ncaclmanager', 'HTTPS адрес Windows агента внутри сети')"
        @update:value="form.agentUrl = $event" />

      <NcTextField
        :value="form.bearerToken"
        :label="t('ncaclmanager', 'Bearer токен')"
        :placeholder="bearerTokenPlaceholder"
        :helper-text="t('ncaclmanager', 'Оставьте пустым чтобы не менять текущий токен')"
        type="password"
        autocomplete="new-password"
        @update:value="form.bearerToken = $event" />

      <NcTextField
        :value="form.clientCert"
        :label="t('ncaclmanager', 'Путь к клиентскому сертификату (PFX)')"
        :placeholder="'/etc/nextcloud/certs/ncaclagent-client.pfx'"
        :helper-text="t('ncaclmanager', 'Абсолютный путь на NC сервере')"
        @update:value="form.clientCert = $event" />

      <NcTextField
        :value="form.certPassword"
        :label="t('ncaclmanager', 'Пароль сертификата')"
        type="password"
        autocomplete="new-password"
        @update:value="form.certPassword = $event" />

      <NcTextField
        :value="String(form.timeout)"
        :label="t('ncaclmanager', 'Таймаут соединения (сек)')"
        type="number"
        @update:value="form.timeout = Number($event)" />

      <!-- Тест связи с агентом -->
      <div class="acl-settings__test">
        <NcButton
          type="secondary"
          :loading="testLoading"
          :disabled="!form.agentUrl"
          @click="testConnection">
          <template #icon><ConnectionIcon :size="18" /></template>
          {{ t('ncaclmanager', 'Проверить соединение') }}
        </NcButton>

        <!-- Результат теста -->
        <Transition name="fade">
          <div v-if="testResult !== null" class="acl-settings__test-result"
               :class="testResult.success ? 'acl-settings__test-result--ok' : 'acl-settings__test-result--fail'">
            <CheckCircleIcon v-if="testResult.success" :size="18" />
            <AlertCircleIcon v-else                    :size="18" />
            <div class="acl-settings__test-detail">
              <span v-if="testResult.success">
                {{ t('ncaclmanager', 'Агент доступен') }}
                <span class="acl-settings__test-meta">
                  · v{{ testResult.result?.version ?? '?' }}
                  · {{ testResult.result?.timestamp ? formatTime(testResult.result.timestamp) : '' }}
                </span>
              </span>
              <span v-else>
                {{ t('ncaclmanager', 'Не удалось подключиться') }}:
                {{ testResult.error ?? testResult.result?.error }}
              </span>
            </div>
          </div>
        </Transition>
      </div>
    </section>

    <!-- ── Секция: Группы администраторов ────────────────────────── -->
    <section class="acl-settings__section">
      <h3>{{ t('ncaclmanager', 'Группы администраторов ACL') }}</h3>
      <p class="acl-settings__desc">
        {{ t('ncaclmanager', 'Пользователи этих групп могут управлять правами на любые папки. Можно добавить несколько групп.') }}
      </p>

      <!-- Список добавленных групп -->
      <div class="acl-settings__groups">
        <div v-for="(group, idx) in form.adminGroups"
             :key="idx"
             class="acl-settings__group-row">
          <AccountGroupIcon :size="18" class="acl-settings__group-icon" />
          <span class="acl-settings__group-name">{{ group }}</span>
          <NcButton type="tertiary-no-background"
                    :aria-label="t('ncaclmanager', 'Удалить группу')"
                    @click="removeAdminGroup(idx)">
            <template #icon><CloseIcon :size="16" /></template>
          </NcButton>
        </div>

        <div v-if="form.adminGroups.length === 0" class="acl-settings__groups-empty">
          {{ t('ncaclmanager', 'Не добавлено ни одной группы — управление правами недоступно!') }}
        </div>
      </div>

      <!-- Добавление новой группы -->
      <div class="acl-settings__add-group">
        <NcTextField
          :value="newGroup"
          :label="t('ncaclmanager', 'Добавить группу')"
          :placeholder="'COMPANY\\IT-Admins'"
          :helper-text="t('ncaclmanager', 'Формат: DOMAIN\\GroupName или sAMAccountName')"
          @update:value="newGroup = $event"
          @keydown.enter="addAdminGroup" />
        <NcButton type="secondary"
                  :disabled="!newGroup.trim()"
                  @click="addAdminGroup">
          <template #icon><PlusIcon :size="16" /></template>
          {{ t('ncaclmanager', 'Добавить') }}
        </NcButton>
      </div>
    </section>

    <!-- ── Секция: Делегирование ─────────────────────────────────── -->
    <section class="acl-settings__section">
      <h3>{{ t('ncaclmanager', 'Делегирование через руководителей') }}</h3>
      <p class="acl-settings__desc">
        {{ t('ncaclmanager', 'Если включено — руководитель может добавлять своих подчинённых в группы доступа (проверяется цепочка manager в AD).') }}
      </p>

      <NcCheckboxRadioSwitch
        :checked="form.ownerModeEnabled"
        @update:checked="form.ownerModeEnabled = $event">
        {{ t('ncaclmanager', 'Разрешить owner группы управлять составом своих групп') }}
      </NcCheckboxRadioSwitch>

      <NcNoteCard v-if="form.ownerModeEnabled" type="info" class="acl-settings__note">
        {{ t('ncaclmanager', 'Глубина проверки цепочки руководителей настраивается в конфиге агента (AdManagerDelegation.MaxDepth)') }}
      </NcNoteCard>
    </section>

    <!-- ── Кнопки действий ───────────────────────────────────────── -->
    <div class="acl-settings__actions">
      <NcButton type="primary"
                :loading="saving"
                @click="save">
        <template #icon><ContentSaveIcon :size="18" /></template>
        {{ t('ncaclmanager', 'Сохранить настройки') }}
      </NcButton>

      <NcButton type="tertiary"
                :loading="loading"
                @click="load">
        <template #icon><RefreshIcon :size="18" /></template>
        {{ t('ncaclmanager', 'Сбросить изменения') }}
      </NcButton>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'

import NcButton              from '@nextcloud/vue/dist/Components/NcButton.js'
import NcTextField           from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcNoteCard            from '@nextcloud/vue/dist/Components/NcNoteCard.js'

import ConnectionIcon   from 'vue-material-design-icons/AccessPoint.vue'
import CheckCircleIcon  from 'vue-material-design-icons/CheckCircle.vue'
import AlertCircleIcon  from 'vue-material-design-icons/AlertCircle.vue'
import AccountGroupIcon from 'vue-material-design-icons/AccountGroup.vue'
import CloseIcon        from 'vue-material-design-icons/Close.vue'
import PlusIcon         from 'vue-material-design-icons/Plus.vue'
import ContentSaveIcon  from 'vue-material-design-icons/ContentSave.vue'
import RefreshIcon      from 'vue-material-design-icons/Refresh.vue'

const api = (path) => generateUrl(`/apps/ncaclmanager/api${path}`)

// ── Состояние ─────────────────────────────────────────────────────────
const loading     = ref(false)
const saving      = ref(false)
const saveSuccess = ref(false)
const testLoading = ref(false)
const testResult  = ref(null)
const newGroup    = ref('')

const form = reactive({
  agentUrl:         '',
  bearerToken:      '',
  clientCert:       '',
  certPassword:     '',
  timeout:          10,
  adminGroups:      [],
  ownerModeEnabled: false,
})

const bearerTokenPlaceholder = computed(() =>
  form.bearerToken
    ? t('ncaclmanager', 'Токен задан — оставьте пустым для сохранения текущего')
    : t('ncaclmanager', 'Введите Bearer токен (минимум 32 символа)')
)

// ── Загрузка настроек ─────────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    const res = await axios.get(api('/settings'))
    const d   = res.data
    form.agentUrl         = d.agent_url         ?? ''
    form.bearerToken      = ''                        // токен не возвращаем
    form.clientCert       = d.client_cert        ?? ''
    form.certPassword     = ''                        // пароль не возвращаем
    form.timeout          = d.timeout            ?? 10
    form.adminGroups      = d.admin_groups       ?? []
    form.ownerModeEnabled = d.owner_mode_enabled ?? false
    testResult.value      = null
  } catch (e) {
    showError(t('ncaclmanager', 'Не удалось загрузить настройки: ') + e.message)
  } finally {
    loading.value = false
  }
}

// ── Сохранение ────────────────────────────────────────────────────────
async function save() {
  saving.value      = true
  saveSuccess.value = false
  try {
    await axios.post(api('/settings'), {
      agent_url:          form.agentUrl,
      bearer_token:       form.bearerToken,   // пустой = не перезаписываем
      client_cert:        form.clientCert,
      cert_password:      form.certPassword,
      timeout:            form.timeout,
      admin_groups:       form.adminGroups,
      owner_mode_enabled: form.ownerModeEnabled ? 'true' : 'false',
    })
    saveSuccess.value = true
    setTimeout(() => { saveSuccess.value = false }, 4000)
  } catch (e) {
    showError(t('ncaclmanager', 'Ошибка сохранения: ') + e.message)
  } finally {
    saving.value = false
  }
}

// ── Тест соединения ───────────────────────────────────────────────────
async function testConnection() {
  testLoading.value = true
  testResult.value  = null
  try {
    const res        = await axios.post(api('/settings/test-agent'))
    testResult.value = res.data
  } catch (e) {
    testResult.value = { success: false, error: e.message }
  } finally {
    testLoading.value = false
  }
}

// ── Управление группами администраторов ───────────────────────────────
function addAdminGroup() {
  const g = newGroup.value.trim()
  if (!g) return
  if (form.adminGroups.includes(g)) {
    showError(t('ncaclmanager', 'Группа уже добавлена'))
    return
  }
  form.adminGroups.push(g)
  newGroup.value = ''
}

function removeAdminGroup(idx) {
  form.adminGroups.splice(idx, 1)
}

// ── Утилиты ───────────────────────────────────────────────────────────
function formatTime(iso) {
  try {
    return new Date(iso).toLocaleTimeString()
  } catch {
    return iso
  }
}

onMounted(() => load())
</script>

<style scoped>
.acl-settings {
  max-width: 700px;
  padding: 24px;
}

.acl-settings h2 {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 24px;
  color: var(--color-main-text);
}

.acl-settings__section {
  background: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  padding: 20px 24px;
  margin-bottom: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.acl-settings__section h3 {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-main-text);
  margin: 0 0 4px;
}

.acl-settings__desc {
  font-size: 13px;
  color: var(--color-text-maxcontrast);
  margin: 0;
  line-height: 1.5;
}

/* ── Тест соединения ────────────────────────────────────────────────── */

.acl-settings__test {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.acl-settings__test-result {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
}

.acl-settings__test-result--ok {
  background: rgba(var(--color-success-rgb, 0,130,0), 0.12);
  color: var(--color-success, #008000);
}

.acl-settings__test-result--fail {
  background: rgba(var(--color-error-rgb, 200,0,0), 0.12);
  color: var(--color-error, #c00000);
}

.acl-settings__test-meta {
  font-weight: 400;
  opacity: 0.75;
  margin-left: 4px;
}

.acl-settings__test-detail {
  display: flex;
  flex-direction: column;
}

/* ── Группы ─────────────────────────────────────────────────────────── */

.acl-settings__groups {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.acl-settings__group-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: var(--color-background-hover);
  border-radius: 8px;
  border: 1px solid var(--color-border);
}

.acl-settings__group-icon {
  color: var(--color-primary);
  flex-shrink: 0;
}

.acl-settings__group-name {
  flex: 1;
  font-size: 13px;
  font-family: monospace;
  color: var(--color-main-text);
}

.acl-settings__groups-empty {
  padding: 12px;
  text-align: center;
  font-size: 13px;
  color: var(--color-error);
  background: rgba(var(--color-error-rgb, 200,0,0), 0.08);
  border-radius: 8px;
  border: 1px dashed var(--color-error);
}

.acl-settings__add-group {
  display: flex;
  gap: 8px;
  align-items: flex-end;
}

.acl-settings__add-group > :first-child {
  flex: 1;
}

/* ── Кнопки ─────────────────────────────────────────────────────────── */

.acl-settings__actions {
  display: flex;
  gap: 12px;
  margin-top: 8px;
}

.acl-settings__note {
  margin: 0;
}

/* ── Анимации ───────────────────────────────────────────────────────── */

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s, transform 0.25s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
