<template>
  <div class="acl-settings">
    <h2>ACL Manager — Настройки</h2>

    <div v-if="saveSuccess" class="acl-settings__note acl-settings__note--success">
      ✓ Настройки сохранены
    </div>
    <div v-if="saveError" class="acl-settings__note acl-settings__note--error">
      ✗ {{ saveError }}
    </div>

    <!-- Секция: Агент -->
    <section class="acl-settings__section">
      <h3>Подключение к агенту</h3>
      <form autocomplete="off" @submit.prevent>

      <label class="acl-settings__label">
        URL агента
        <input class="acl-settings__input" type="url" v-model="form.agentUrl"
               placeholder="https://10.0.1.50:8443" />
        <span class="acl-settings__hint">HTTPS адрес Windows агента внутри сети</span>
      </label>

      <label class="acl-settings__label">
        Bearer токен
        <input class="acl-settings__input" type="password" v-model="form.bearerToken"
               autocomplete="new-password"
               :placeholder="bearerPlaceholder" />
        <span class="acl-settings__hint">Оставьте пустым чтобы не менять текущий токен</span>
      </label>

      <label class="acl-settings__label">
        Путь к клиентскому PFX сертификату
        <input class="acl-settings__input" type="text" v-model="form.clientCert"
               placeholder="/etc/nextcloud/certs/ncaclagent-client.pfx" />
        <span class="acl-settings__hint">Абсолютный путь на NC сервере</span>
      </label>

      <label class="acl-settings__label">
        Пароль сертификата
        <input class="acl-settings__input" type="password" v-model="form.certPassword"
               autocomplete="new-password" />
      </label>

      <label class="acl-settings__label">
        Таймаут (сек)
        <input class="acl-settings__input acl-settings__input--short"
               type="number" v-model.number="form.timeout" min="1" max="60" />
      </label>

      </form>
      <!-- Тест соединения -->
      <div class="acl-settings__test">
        <button class="button" :disabled="!form.agentUrl || testLoading" @click="testConnection">
          <span v-if="testLoading" class="icon-loading-small"></span>
          <span v-else class="icon-category-monitoring"></span>
          Проверить соединение
        </button>

        <Transition name="fade">
          <div v-if="testResult !== null"
               class="acl-settings__test-result"
               :class="testResult.success ? 'acl-settings__test-result--ok' : 'acl-settings__test-result--fail'">
            <template v-if="testResult.success">
              ✓ Агент доступен
              <span class="acl-settings__test-meta">
                · v{{ testResult.result?.version ?? '?' }}
                · {{ testResult.result?.timestamp ? new Date(testResult.result.timestamp).toLocaleTimeString() : '' }}
              </span>
            </template>
            <template v-else>
              ✗ Не удалось подключиться: {{ testResult.error ?? testResult.result?.error }}
            </template>
          </div>
        </Transition>
      </div>
    </section>

    <!-- Секция: Группы администраторов -->
    <section class="acl-settings__section">
      <h3>Группы администраторов ACL</h3>
      <p class="acl-settings__desc">
        Пользователи этих групп могут управлять правами на любые папки. Можно добавить несколько групп.
      </p>

      <div v-if="form.adminGroups.length === 0" class="acl-settings__groups-empty">
        ⚠ Не добавлено ни одной группы — управление правами недоступно!
      </div>

      <div class="acl-settings__groups">
        <div v-for="(group, idx) in form.adminGroups" :key="idx" class="acl-settings__group-row">
          <span class="icon-group"></span>
          <span class="acl-settings__group-name">{{ group }}</span>
          <button class="button" @click="removeAdminGroup(idx)">
            <span class="icon-close"></span>
          </button>
        </div>
      </div>

      <div class="acl-settings__add-group">
        <input class="acl-settings__input" type="text" v-model="newGroup"
               placeholder="COMPANY\IT-Admins"
               @keydown.enter="addAdminGroup" />
        <button class="button" :disabled="!newGroup.trim()" @click="addAdminGroup">
          Добавить
        </button>
      </div>
    </section>

    <!-- Секция: Делегирование -->
    <section class="acl-settings__section">
      <h3>Делегирование через руководителей</h3>
      <p class="acl-settings__desc">
        Если включено — руководитель может добавлять своих подчинённых в группы доступа.
      </p>

      <label class="acl-settings__checkbox">
        <input type="checkbox" v-model="form.ownerModeEnabled" />
        Разрешить owner группы управлять составом своих групп
      </label>

      <div v-if="form.ownerModeEnabled" class="acl-settings__note acl-settings__note--info">
        Глубина проверки цепочки руководителей настраивается в конфиге агента
        (AdManagerDelegation.MaxDepth)
      </div>
    </section>

    <!-- Действия -->
    <div class="acl-settings__actions">
      <button class="button primary" :disabled="saving" @click="save">
        <span v-if="saving" class="icon-loading-small"></span>
        Сохранить настройки
      </button>
      <button class="button" :disabled="loading" @click="load">
        Сбросить изменения
      </button>
    </div>
  </div>
</template>

<script>
import { getSettings, saveSettings, testAgent } from '../api/agent.js'
import { showError } from '../api/nc.js'

export default {
  name: 'AdminSettings',
  data() {
    return {
      loading:      false,
      saving:       false,
      saveSuccess:  false,
      saveError:    null,
      testLoading:  false,
      testResult:   null,
      newGroup:     '',
      form: {
        agentUrl:         '',
        bearerToken:      '',
        clientCert:       '',
        certPassword:     '',
        timeout:          10,
        adminGroups:      [],
        ownerModeEnabled: false,
      },
    }
  },
  computed: {
    bearerPlaceholder() {
      return this.form.bearerToken
        ? 'Токен задан — оставьте пустым для сохранения текущего'
        : 'Введите Bearer токен (минимум 32 символа)'
    },
  },
  mounted() { this.load() },
  methods: {
    async load() {
      this.loading = true
      try {
        const d = await getSettings()
        this.form.agentUrl         = d.agent_url         ?? ''
        this.form.bearerToken      = ''
        this.form.clientCert       = d.client_cert        ?? ''
        this.form.certPassword     = ''
        this.form.timeout          = d.timeout            ?? 10
        this.form.adminGroups      = d.admin_groups       ?? []
        this.form.ownerModeEnabled = d.owner_mode_enabled ?? false
        this.testResult            = null
      } catch (e) {
        showError('Не удалось загрузить настройки: ' + e.message)
      } finally {
        this.loading = false
      }
    },
    async save() {
      this.saving     = true
      this.saveSuccess = false
      this.saveError   = null
      try {
        await saveSettings({
          agent_url:          this.form.agentUrl,
          bearer_token:       this.form.bearerToken,
          client_cert:        this.form.clientCert,
          cert_password:      this.form.certPassword,
          timeout:            this.form.timeout,
          admin_groups:       this.form.adminGroups,
          owner_mode_enabled: this.form.ownerModeEnabled ? 'true' : 'false',
        })
        this.saveSuccess = true
        setTimeout(() => { this.saveSuccess = false }, 4000)
      } catch (e) {
        this.saveError = e.message
      } finally {
        this.saving = false
      }
    },
    async testConnection() {
      this.testLoading = true
      this.testResult  = null
      try {
        this.testResult = await testAgent()
      } catch (e) {
        this.testResult = { success: false, error: e.message }
      } finally {
        this.testLoading = false
      }
    },
    addAdminGroup() {
      const g = this.newGroup.trim()
      if (!g || this.form.adminGroups.includes(g)) return
      this.form.adminGroups.push(g)
      this.newGroup = ''
    },
    removeAdminGroup(idx) {
      this.form.adminGroups.splice(idx, 1)
    },
  },
}
</script>

<style scoped>
.acl-settings { max-width: 700px; padding: 24px; }
.acl-settings h2 { font-size: 20px; font-weight: 700; margin-bottom: 24px; }

.acl-settings__section {
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
  padding: 20px 24px;
  margin-bottom: 20px;
  display: flex; flex-direction: column; gap: 14px;
}
.acl-settings__section h3 { font-size: 15px; font-weight: 600; margin: 0 0 2px; }
.acl-settings__desc { font-size: 13px; color: var(--color-text-maxcontrast); margin: 0; }

.acl-settings__label { display: flex; flex-direction: column; gap: 4px; font-size: 13px; font-weight: 500; }
.acl-settings__input { width: 100%; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: var(--border-radius); background: var(--color-main-background); color: var(--color-main-text); font-size: 13px; box-sizing: border-box; }
.acl-settings__input--short { width: 100px; }
.acl-settings__input:focus { outline: 2px solid var(--color-primary); border-color: transparent; }
.acl-settings__hint { font-size: 11px; color: var(--color-text-maxcontrast); }

.acl-settings__test { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.acl-settings__test-result { padding: 8px 14px; border-radius: var(--border-radius); font-size: 13px; font-weight: 500; }
.acl-settings__test-result--ok   { background: rgba(0,130,0,.1); color: var(--color-success, green); }
.acl-settings__test-result--fail { background: rgba(200,0,0,.1); color: var(--color-error, red); }
.acl-settings__test-meta { font-weight: 400; opacity: .75; margin-left: 4px; }

.acl-settings__groups { display: flex; flex-direction: column; gap: 6px; }
.acl-settings__group-row {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; background: var(--color-background-hover);
  border-radius: var(--border-radius); border: 1px solid var(--color-border);
}
.acl-settings__group-name { flex: 1; font-family: monospace; font-size: 13px; }
.acl-settings__groups-empty {
  padding: 10px; text-align: center; font-size: 13px;
  color: var(--color-warning, orange);
  border: 1px dashed var(--color-warning, orange);
  border-radius: var(--border-radius);
}

.acl-settings__add-group { display: flex; gap: 8px; align-items: center; }
.acl-settings__add-group .acl-settings__input { flex: 1; }

.acl-settings__checkbox { display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; }

.acl-settings__note { padding: 10px 14px; border-radius: var(--border-radius); font-size: 13px; }
.acl-settings__note--success { background: rgba(0,130,0,.1); color: var(--color-success, green); }
.acl-settings__note--error   { background: rgba(200,0,0,.1); color: var(--color-error, red); }
.acl-settings__note--info    { background: rgba(0,100,200,.1); color: var(--color-info, #0064c8); }

.acl-settings__actions { display: flex; gap: 12px; margin-top: 8px; }

.fade-enter-active, .fade-leave-active { transition: opacity .25s, transform .25s; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
