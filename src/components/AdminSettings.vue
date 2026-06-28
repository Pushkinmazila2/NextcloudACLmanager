<template>
  <div class="acl-settings">
    <h2>ACL Manager — Настройки</h2>

    <div v-if="saveSuccess" class="acl-settings__note acl-settings__note--success">✓ Настройки сохранены</div>
    <div v-if="saveError"   class="acl-settings__note acl-settings__note--error">✗ {{ saveError }}</div>

    <!-- ── Секция: Агент ─────────────────────────────────────────── -->
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
                 autocomplete="new-password" :placeholder="bearerPlaceholder" />
          <span class="acl-settings__hint">Оставьте пустым чтобы не менять текущий токен</span>
        </label>

        <!-- Загрузка PFX сертификата -->
        <div class="acl-settings__label">
          <span>Клиентский PFX сертификат (mTLS)</span>
          <div class="acl-settings__cert-row">
            <input class="acl-settings__input acl-settings__input--mono" type="text"
                   v-model="form.clientCert" placeholder="/var/www/nextcloud/data/ncaclmanager/certs/client.pfx"
                   readonly />
            <label class="button acl-settings__upload-btn" :class="{ 'acl-settings__upload-btn--loading': uploadingCert }">
              <span v-if="uploadingCert" class="icon-loading-small"></span>
              <span v-else class="icon-upload"></span>
              {{ uploadingCert ? 'Загрузка...' : 'Загрузить .pfx' }}
              <input type="file" accept=".pfx,.p12" style="display:none"
                     @change="uploadCert" :disabled="uploadingCert" />
            </label>
          </div>
          <span v-if="certUploadResult" class="acl-settings__hint"
                :class="certUploadResult.success ? 'acl-settings__hint--ok' : 'acl-settings__hint--err'">
            {{ certUploadResult.success
              ? `✓ Загружен: ${certUploadResult.cert_path} (${Math.round(certUploadResult.size/1024)} KB)`
              : `✗ ${certUploadResult.error}` }}
          </span>
          <span v-else class="acl-settings__hint">
            Файл сохраняется на NC сервере. Или укажите путь вручную выше.
          </span>
        </div>

        <label class="acl-settings__label">
          Пароль PFX сертификата
          <input class="acl-settings__input" type="password" v-model="form.certPassword"
                 autocomplete="new-password" placeholder="Оставьте пустым чтобы не менять" />
        </label>

        <label class="acl-settings__label">
          Таймаут (сек)
          <input class="acl-settings__input acl-settings__input--short"
                 type="number" v-model.number="form.timeout" min="1" max="60" />
        </label>
      </form>

      <!-- Кнопка теста -->
      <div class="acl-settings__test">
        <button class="button" :disabled="!form.agentUrl || testLoading" @click="testConnection">
          <span v-if="testLoading" class="icon-loading-small"></span>
          <span v-else class="icon-category-monitoring"></span>
          Проверить соединение
        </button>
      </div>

      <!-- Результат теста -->
      <Transition name="fade">
        <div v-if="testResult !== null" class="acl-settings__test-block">

          <!-- Статус -->
          <div class="acl-settings__test-status"
               :class="testResult.success ? 'acl-settings__test-status--ok' : 'acl-settings__test-status--fail'">
            {{ testResult.success ? '✓ Агент доступен' : '✗ Нет соединения' }}
            <span v-if="testResult.success && testResult.result?.version">
              · v{{ testResult.result.version }}
            </span>
            <span v-if="testResult.error" class="acl-settings__test-err">
              — {{ testResult.error }}
            </span>
          </div>

          <!-- Диагностика -->
          <div v-if="testResult.diagnostics" class="acl-settings__diag">
            <div class="acl-settings__diag-title" @click="showDiag = !showDiag">
              <span>🔍 Диагностика</span>
              <span>{{ showDiag ? '▲' : '▼' }}</span>
            </div>
            <table v-if="showDiag" class="acl-settings__diag-table">
              <tbody>
                <tr v-for="(val, key) in testResult.diagnostics" :key="key">
                  <td class="acl-settings__diag-key">{{ key }}</td>
                  <td class="acl-settings__diag-val"
                      :class="{
                        'acl-settings__diag-val--ok':   val === true,
                        'acl-settings__diag-val--warn':  val === false || val === '(не задан)',
                      }">
                    {{ formatDiagVal(val) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- curl команда -->
          <div v-if="testResult.curl_command" class="acl-settings__curl">
            <div class="acl-settings__curl-title">
              <span>📋 curl команда для ручного тестирования</span>
              <button class="button" @click="copyCurl">{{ curlCopied ? '✓ Скопировано' : 'Копировать' }}</button>
            </div>
            <pre class="acl-settings__curl-code">{{ testResult.curl_command }}</pre>
            <p class="acl-settings__hint">
              Замените замаскированные значения (*) реальными токеном и паролем сертификата.
            </p>
          </div>

        </div>
      </Transition>
    </section>

    <!-- ── Секция: Группы администраторов ────────────────────────── -->
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
               placeholder="COMPANY\IT-Admins" @keydown.enter="addAdminGroup" />
        <button class="button" :disabled="!newGroup.trim()" @click="addAdminGroup">Добавить</button>
      </div>
    </section>

    <!-- ── Секция: Делегирование ─────────────────────────────────── -->
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
        Глубина проверки настраивается в конфиге агента (AdManagerDelegation.MaxDepth)
      </div>
    </section>

    <!-- ── Действия ──────────────────────────────────────────────── -->
    <div class="acl-settings__actions">
      <button class="button primary" :disabled="saving" @click="save">
        <span v-if="saving" class="icon-loading-small"></span>
        Сохранить настройки
      </button>
      <button class="button" :disabled="loading" @click="load">Сбросить изменения</button>
    </div>
  </div>
</template>

<script>
import { getSettings, saveSettings, testAgent } from '../api/agent.js'
import { showError, generateUrl, axios } from '../api/nc.js'

export default {
  name: 'AdminSettings',
  data() {
    return {
      loading:        false,
      saving:         false,
      saveSuccess:    false,
      saveError:      null,
      testLoading:    false,
      testResult:     null,
      showDiag:       true,
      curlCopied:     false,
      newGroup:       '',
      uploadingCert:  false,
      certUploadResult: null,
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
        this.form.adminGroups      = Array.isArray(d.admin_groups) ? d.admin_groups : []
        this.form.ownerModeEnabled = d.owner_mode_enabled ?? false
        this.testResult            = null
      } catch (e) {
        showError('Не удалось загрузить настройки: ' + e.message)
      } finally {
        this.loading = false
      }
    },

    async save() {
      this.saving      = true
      this.saveSuccess = false
      this.saveError   = null
      try {
        await saveSettings({
          agent_url:          this.form.agentUrl,
          bearer_token:       this.form.bearerToken,
          client_cert:        this.form.clientCert,
          cert_password:      this.form.certPassword,
          timeout:            String(this.form.timeout),
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
      this.showDiag    = true
      try {
        this.testResult = await testAgent()
      } catch (e) {
        this.testResult = { success: false, error: e.message, diagnostics: null, curl_command: null }
      } finally {
        this.testLoading = false
      }
    },

    async uploadCert(event) {
      const file = event.target.files?.[0]
      if (!file) return

      this.uploadingCert    = true
      this.certUploadResult = null

      try {
        const formData = new FormData()
        formData.append('cert', file)

        const url   = generateUrl('/apps/ncaclmanager/api/settings/upload-cert')
        const token = document.querySelector('head > meta[name="requesttoken"]')?.content ?? ''

        const response = await fetch(url, {
          method:  'POST',
          headers: { 'requesttoken': token },
          body:    formData,
        })

        const result = await response.json()
        this.certUploadResult = result

        if (result.success) {
          this.form.clientCert = result.cert_path
        }
      } catch (e) {
        this.certUploadResult = { success: false, error: e.message }
      } finally {
        this.uploadingCert  = false
        event.target.value  = '' // сбрасываем input чтобы можно было загрузить снова
      }
    },

    copyCurl() {
      if (!this.testResult?.curl_command) return
      navigator.clipboard?.writeText(this.testResult.curl_command).then(() => {
        this.curlCopied = true
        setTimeout(() => { this.curlCopied = false }, 2500)
      })
    },

    formatDiagVal(val) {
      if (val === true)  return '✓ да'
      if (val === false) return '✗ нет'
      return String(val)
    },

    addAdminGroup() {
      const g = this.newGroup.trim()
      if (!g || this.form.adminGroups.includes(g)) return
      this.form.adminGroups.push(g)
      this.newGroup = ''
    },
    removeAdminGroup(idx) { this.form.adminGroups.splice(idx, 1) },
  },
}
</script>

<style scoped>
.acl-settings { max-width: 720px; padding: 24px; }
.acl-settings h2 { font-size: 20px; font-weight: 700; margin-bottom: 24px; }

.acl-settings__section {
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
  padding: 20px 24px; margin-bottom: 20px;
  display: flex; flex-direction: column; gap: 14px;
}
.acl-settings__section h3 { font-size: 15px; font-weight: 600; margin: 0 0 2px; }
.acl-settings__desc { font-size: 13px; color: var(--color-text-maxcontrast); margin: 0; }

.acl-settings__label { display: flex; flex-direction: column; gap: 4px; font-size: 13px; font-weight: 500; }
.acl-settings__input {
  width: 100%; padding: 8px 10px;
  border: 1px solid var(--color-border); border-radius: var(--border-radius);
  background: var(--color-main-background); color: var(--color-main-text);
  font-size: 13px; box-sizing: border-box;
}
.acl-settings__input--short { width: 80px; }
.acl-settings__input--mono  { font-family: monospace; font-size: 12px; }
.acl-settings__input:focus  { outline: 2px solid var(--color-primary); border-color: transparent; }

.acl-settings__hint     { font-size: 11px; color: var(--color-text-maxcontrast); }
.acl-settings__hint--ok  { color: var(--color-success, green); }
.acl-settings__hint--err { color: var(--color-error, red); }

/* Cert upload row */
.acl-settings__cert-row { display: flex; gap: 8px; align-items: center; }
.acl-settings__cert-row .acl-settings__input { flex: 1; }
.acl-settings__upload-btn { display: flex; align-items: center; gap: 6px; cursor: pointer; white-space: nowrap; flex-shrink: 0; }
.acl-settings__upload-btn--loading { opacity: .7; pointer-events: none; }

/* Test */
.acl-settings__test { display: flex; align-items: center; gap: 12px; }

.acl-settings__test-block { display: flex; flex-direction: column; gap: 12px; margin-top: 4px; }

.acl-settings__test-status {
  padding: 10px 16px; border-radius: var(--border-radius);
  font-size: 14px; font-weight: 600;
}
.acl-settings__test-status--ok   { background: rgba(0,130,0,.1); color: var(--color-success, green); }
.acl-settings__test-status--fail { background: rgba(200,0,0,.1); color: var(--color-error, red); }
.acl-settings__test-err { font-weight: 400; font-size: 13px; margin-left: 8px; }

/* Diagnostics table */
.acl-settings__diag { border: 1px solid var(--color-border); border-radius: var(--border-radius); overflow: hidden; }
.acl-settings__diag-title {
  display: flex; justify-content: space-between;
  padding: 8px 12px; background: var(--color-background-hover);
  cursor: pointer; font-size: 13px; font-weight: 500; user-select: none;
}
.acl-settings__diag-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.acl-settings__diag-table tr:nth-child(even) { background: var(--color-background-hover); }
.acl-settings__diag-key { padding: 5px 12px; color: var(--color-text-maxcontrast); width: 160px; font-family: monospace; }
.acl-settings__diag-val { padding: 5px 12px; font-family: monospace; }
.acl-settings__diag-val--ok   { color: var(--color-success, green); }
.acl-settings__diag-val--warn { color: var(--color-warning, orange); }

/* curl */
.acl-settings__curl { border: 1px solid var(--color-border); border-radius: var(--border-radius); overflow: hidden; }
.acl-settings__curl-title {
  display: flex; justify-content: space-between; align-items: center;
  padding: 8px 12px; background: var(--color-background-hover); font-size: 13px; font-weight: 500;
}
.acl-settings__curl-code {
  margin: 0; padding: 12px 16px;
  background: #1e1e1e; color: #d4d4d4;
  font-family: monospace; font-size: 12px;
  overflow-x: auto; white-space: pre; line-height: 1.6;
}

/* Groups */
.acl-settings__groups        { display: flex; flex-direction: column; gap: 6px; }
.acl-settings__group-row     { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: var(--color-background-hover); border-radius: var(--border-radius); border: 1px solid var(--color-border); }
.acl-settings__group-name    { flex: 1; font-family: monospace; font-size: 13px; }
.acl-settings__groups-empty  { padding: 10px; text-align: center; font-size: 13px; color: var(--color-warning, orange); border: 1px dashed var(--color-warning, orange); border-radius: var(--border-radius); }
.acl-settings__add-group     { display: flex; gap: 8px; align-items: center; }
.acl-settings__add-group .acl-settings__input { flex: 1; }

.acl-settings__checkbox { display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; }

.acl-settings__note          { padding: 10px 14px; border-radius: var(--border-radius); font-size: 13px; }
.acl-settings__note--success { background: rgba(0,130,0,.1);   color: var(--color-success, green); }
.acl-settings__note--error   { background: rgba(200,0,0,.1);   color: var(--color-error, red); }
.acl-settings__note--info    { background: rgba(0,100,200,.1); color: #0064c8; }

.acl-settings__actions { display: flex; gap: 12px; margin-top: 8px; }

.fade-enter-active,.fade-leave-active { transition: opacity .25s, transform .25s; }
.fade-enter-from,.fade-leave-to       { opacity: 0; transform: translateY(-4px); }
</style>
