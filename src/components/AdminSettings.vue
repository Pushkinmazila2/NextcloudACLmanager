<template>
  <div class="acl-settings">
    <h2>ACL Manager — Настройки</h2>

    <div v-if="saveSuccess" class="acl-settings__note acl-settings__note--success">✓ Настройки сохранены</div>
    <div v-if="saveError"   class="acl-settings__note acl-settings__note--error">✗ {{ saveError }}</div>

    <!-- ── Подключение к агенту ──────────────────────────────────── -->
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
                 :placeholder="form.bearerTokenSet
                   ? '●●●●●●●● (задан, оставьте пустым чтобы не менять)'
                   : 'Введите Bearer токен (минимум 32 символа)'" />
        </label>

        <div class="acl-settings__label">
          <span>Клиентский PFX сертификат (mTLS)</span>
          <div class="acl-settings__cert-block">
            <div class="acl-settings__cert-row">
              <input class="acl-settings__input acl-settings__input--mono"
                     type="text" v-model="form.clientCert"
                     placeholder="/var/www/nextcloud/data/ncaclmanager/certs/client.pfx" />
            </div>
            <span class="acl-settings__hint">
              Путь к .pfx файлу на сервере Nextcloud (работает в Docker).
            </span>
            <div class="acl-settings__cert-upload">
              <span class="acl-settings__cert-or">или</span>
              <label class="button" :class="{ disabled: uploadingCert }">
                <span v-if="uploadingCert" class="icon-loading-small"></span>
                {{ uploadingCert ? 'Загрузка...' : '⬆ Загрузить .pfx / .p12' }}
                <input type="file" accept=".pfx,.p12" style="display:none"
                       :disabled="uploadingCert" @change="uploadCert($event)" />
              </label>
              <span v-if="certUploadResult"
                    class="acl-settings__cert-upload-result"
                    :class="certUploadResult.success ? 'ok' : 'err'">
                <template v-if="certUploadResult.success">
                  ✓ {{ certUploadResult.cert_path }}
                </template>
                <template v-else>
                  ✗ {{ certUploadResult.error || 'Неизвестная ошибка' }}
                </template>
              </span>
            </div>
          </div>
        </div>

        <label class="acl-settings__label">
          Пароль PFX сертификата
          <input class="acl-settings__input" type="password" v-model="form.certPassword"
                 autocomplete="new-password"
                 :placeholder="form.certPasswordSet
                   ? '●●●●●●●● (задан, оставьте пустым чтобы не менять)'
                   : 'Пароль от .pfx файла'" />
        </label>

        <label class="acl-settings__label">
          Таймаут (сек)
          <input class="acl-settings__input acl-settings__input--short"
                 type="number" v-model.number="form.timeout" min="1" max="60" />
        </label>

        <label class="acl-settings__checkbox">
          <input type="checkbox" v-model="form.verifySsl" />
          Проверять SSL сертификат агента (verify)
        </label>
        <div v-if="!form.verifySsl" class="acl-settings__note acl-settings__note--warn">
          ⚠ Проверка SSL отключена — используйте только в Test режиме с самоподписанным сертификатом!
        </div>

      </form>

      <div class="acl-settings__test-row">
        <button class="button primary" :disabled="saving" @click="save">
          <span v-if="saving" class="icon-loading-small"></span>
          Сохранить настройки
        </button>
        <button class="button" :disabled="!form.agentUrl || testLoading" @click="testConnection">
          <span v-if="testLoading" class="icon-loading-small"></span>
          🔌 Проверить соединение
        </button>
      </div>

      <!-- Результат теста -->
      <Transition name="fade">
        <div v-if="testResult !== null" class="acl-settings__test-result-block">

          <div class="acl-settings__test-status"
               :class="testResult.success
                 ? 'acl-settings__test-status--ok'
                 : 'acl-settings__test-status--fail'">
            {{ testResult.success ? '✓ Агент доступен' : '✗ Нет соединения' }}
            <span v-if="testResult.success && testResult.result && testResult.result.version">
              · v{{ testResult.result.version }}
            </span>
            <span v-if="testResult.error" class="acl-settings__test-err">
              — {{ testResult.error }}
            </span>
          </div>

          <!-- Предупреждение о local access rules -->
          <div v-if="isLocalAccessError" class="acl-settings__note acl-settings__note--warn">
            <strong>⚠ Nextcloud блокирует запросы к локальным IP.</strong><br>
            <code>sudo -u www-data php occ config:system:set allow_local_remote_servers --value=true --type=bool</code><br>
            Docker: <code>docker exec -u www-data nextcloud php occ config:system:set allow_local_remote_servers --value=true --type=bool</code>
          </div>

          <!-- Диагностика -->
          <div v-if="testResult.diagnostics" class="acl-settings__diag">
            <div class="acl-settings__diag-header" @click="showDiag = !showDiag">
              🔍 Диагностика параметров <span>{{ showDiag ? '▲' : '▼' }}</span>
            </div>
            <table v-if="showDiag" class="acl-settings__diag-table">
              <tbody>
                <tr v-for="(val, key) in testResult.diagnostics" :key="key">
                  <td class="acl-settings__diag-key">{{ key }}</td>
                  <td class="acl-settings__diag-val"
                      :class="{ ok: val === true, warn: val === false || String(val).includes('не задан') }">
                    {{ val === true ? '✓ да' : val === false ? '✗ нет' : val }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- curl -->
          <div v-if="testResult.curl_command" class="acl-settings__curl">
            <div class="acl-settings__curl-header">
              📋 curl для ручного теста
              <button class="button" @click="copyCurl">
                {{ curlCopied ? '✓ Скопировано!' : 'Копировать' }}
              </button>
            </div>
            <pre class="acl-settings__curl-code">{{ testResult.curl_command }}</pre>
          </div>

        </div>
      </Transition>
    </section>

    <!-- ── Группы администраторов ─────────────────────────────────── -->
    <section class="acl-settings__section">
      <h3>Группы администраторов ACL</h3>
      <p class="acl-settings__desc">
        Пользователи этих AD групп могут управлять правами на любые папки.
      </p>

      <div v-if="form.adminGroups.length === 0" class="acl-settings__warn-box">
        ⚠ Не добавлено ни одной группы!
      </div>

      <div class="acl-settings__groups">
        <div v-for="(group, idx) in form.adminGroups" :key="idx" class="acl-settings__group-row">
          <span>👥</span>
          <code class="acl-settings__group-name">{{ group }}</code>
          <button class="button" @click="removeAdminGroup(idx)">✕</button>
        </div>
      </div>

      <div class="acl-settings__add-group">
        <input class="acl-settings__input" type="text" v-model="newGroup"
               placeholder="COMPANY\IT-Admins" @keydown.enter="addAdminGroup" />
        <button class="button" :disabled="!newGroup.trim()" @click="addAdminGroup">+ Добавить</button>
      </div>
    </section>

    <!-- ── NC пользователи (только Test режим) ─────────────────────── -->
    <section v-if="isTestMode" class="acl-settings__section acl-settings__section--test">
      <h3>
        🧪 Пользователи NC с правами ACL
        <span class="acl-settings__test-badge">Только Test режим</span>
      </h3>
      <p class="acl-settings__desc">
        В Test режиме можно выдать права ACL конкретным пользователям NC без AD групп.
        В Prod режиме игнорируется.
      </p>

      <div v-if="form.ncAdminUsers.length === 0" class="acl-settings__warn-box acl-settings__warn-box--soft">
        Список пуст — используются только AD группы выше
      </div>

      <div class="acl-settings__groups">
        <div v-for="(uid, idx) in form.ncAdminUsers" :key="idx" class="acl-settings__group-row">
          <span>👤</span>
          <code class="acl-settings__group-name">{{ uid }}</code>
          <button class="button" @click="removeNcUser(idx)">✕</button>
        </div>
      </div>

      <div class="acl-settings__nc-user-search">
        <div class="acl-settings__add-group">
          <input class="acl-settings__input" type="text"
                 v-model="ncUserQuery"
                 placeholder="Поиск пользователя NC (мин. 2 символа)"
                 @input="onNcUserInput"
                 @keydown.escape="ncUserResults = []" />
        </div>
        <ul v-if="ncUserResults.length > 0" class="acl-settings__nc-user-results">
          <li v-for="u in ncUserResults" :key="u.uid"
              class="acl-settings__nc-user-result"
              :class="{ disabled: form.ncAdminUsers.includes(u.uid) }"
              @click="addNcUser(u)">
            <span class="acl-settings__nc-user-avatar">
              {{ (u.displayName || u.uid || '?')[0].toUpperCase() }}
            </span>
            <span class="acl-settings__nc-user-name">{{ u.displayName || u.uid }}</span>
            <span class="acl-settings__nc-user-uid">{{ u.uid }}</span>
            <span v-if="form.ncAdminUsers.includes(u.uid)" class="acl-settings__nc-user-added">✓</span>
          </li>
        </ul>
        <div v-else-if="ncUserQuery.length >= 2 && !ncUserLoading"
             class="acl-settings__nc-user-empty">
          Пользователи не найдены
        </div>
      </div>
    </section>

    <!-- ── Маппинги NC → UNC ────────────────────────────────────────── -->
    <section class="acl-settings__section">
      <h3>Маппинги внешних хранилищ (SMB/CIFS)</h3>
      <p class="acl-settings__desc">
        Сопоставление пути NC (/точка_монтирования) с UNC путём Windows шары (\\SERVER\Share).
        Необходимо для работы с папками подключёнными через "Внешнее хранилище".
      </p>

      <div v-if="mounts.length === 0" class="acl-settings__warn-box acl-settings__warn-box--soft">
        Маппинги не настроены — ACL панель будет использовать путь NC как есть
      </div>

      <div class="acl-settings__mounts">
        <div v-for="(m, idx) in mounts" :key="idx" class="acl-settings__mount-row">
          <div class="acl-settings__mount-paths">
            <div class="acl-settings__mount-nc">
              <span class="acl-settings__mount-label">NC путь</span>
              <input class="acl-settings__input acl-settings__input--mono"
                     type="text" v-model="m.ncPath"
                     placeholder="/smb_finance" />
            </div>
            <span class="acl-settings__mount-arrow">→</span>
            <div class="acl-settings__mount-unc">
              <span class="acl-settings__mount-label">UNC путь</span>
              <input class="acl-settings__input acl-settings__input--mono"
                     type="text" v-model="m.uncPath"
                     placeholder="\\FILESERVER\Finance" />
            </div>
            <div class="acl-settings__mount-desc">
              <span class="acl-settings__mount-label">Описание</span>
              <input class="acl-settings__input" type="text"
                     v-model="m.description" placeholder="Финансы" />
            </div>
            <button class="button" @click="removeMount(idx)">✕</button>
          </div>
        </div>
      </div>

      <div class="acl-settings__mount-add">
        <button class="button" @click="addMount">+ Добавить маппинг</button>
        <button class="button primary" :disabled="mountsSaving" @click="saveMounts">
          <span v-if="mountsSaving" class="icon-loading-small"></span>
          Сохранить маппинги
        </button>
        <span v-if="mountsSaveOk" style="color:green;font-size:13px">✓ Сохранено</span>
      </div>

      <div class="acl-settings__note acl-settings__note--info" style="font-size:12px">
        <strong>Пример:</strong><br>
        NC путь: <code>/smb_finance</code> → UNC: <code>\\FILESERVER\Finance</code><br>
        Тогда <code>/smb_finance/Reports/2024</code> → <code>\\FILESERVER\Finance\Reports\2024</code>
      </div>
    </section>

    <!-- ── Делегирование ──────────────────────────────────────────── -->
    <section class="acl-settings__section">
      <h3>Делегирование через руководителей AD</h3>
      <p class="acl-settings__desc">
        Если включено — руководитель может добавлять своих подчинённых в группы доступа.
      </p>
      <label class="acl-settings__checkbox">
        <input type="checkbox" v-model="form.ownerModeEnabled" />
        Разрешить owner группы управлять составом своих групп
      </label>
      <div v-if="form.ownerModeEnabled" class="acl-settings__note acl-settings__note--info">
        Глубина проверки: <code>AdManagerDelegation.MaxDepth</code> в конфиге агента
      </div>
    </section>

  </div>
</template>

<script>
import { getSettings, saveSettings, testAgent } from '../api/agent.js'
import { showError, generateUrl, axios } from '../api/nc.js'

export default {
  name: 'AdminSettings',

  data() {
    return {
      // Состояние UI
      loading:          false,
      saving:           false,
      saveSuccess:      false,
      saveError:        null,
      testLoading:      false,
      testResult:       null,
      showDiag:         true,
      curlCopied:       false,
      uploadingCert:    false,
      certUploadResult: null,
      // Форма
      newGroup:         '',
      agentMode:        '',
      // Поиск NC пользователей
      ncUserQuery:      '',
      ncUserResults:    [],
      ncUserLoading:    false,
      ncUserTimer:      null,
      // Маппинги NC → UNC (отдельно от form)
      mounts:       [],
      mountsSaving: false,
      mountsSaveOk: false,
      // Данные формы
      form: {
        agentUrl:         '',
        bearerToken:      '',
        bearerTokenSet:   false,
        clientCert:       '',
        certPassword:     '',
        certPasswordSet:  false,
        timeout:          10,
        adminGroups:      [],
        ncAdminUsers:     [],
        ownerModeEnabled: false,
        verifySsl:        true,
      },
    }
  },

  computed: {
    isTestMode() {
      const mode = this.agentMode || ''
      return mode === '' || mode.toLowerCase() === 'test'
    },
    isLocalAccessError() {
      if (!this.testResult || this.testResult.success) return false
      const err = this.testResult.error || ''
      return err.includes('local access rules') || err.includes('violates local')
    },
  },

  mounted() {
    this.load()
  },

  methods: {

    async load() {
      this.loading = true
      try {
        const d = await getSettings()
        this.form.agentUrl         = d.agent_url         || ''
        this.form.bearerToken      = ''
        this.form.bearerTokenSet   = !!d.bearer_token_set
        this.form.clientCert       = d.client_cert        || ''
        this.form.certPassword     = ''
        this.form.certPasswordSet  = !!d.client_cert
        this.form.timeout          = d.timeout            || 10
        this.form.adminGroups      = Array.isArray(d.admin_groups)   ? d.admin_groups   : []
        this.form.ncAdminUsers     = Array.isArray(d.nc_admin_users) ? d.nc_admin_users : []
        this.form.ownerModeEnabled = !!d.owner_mode_enabled
        this.form.verifySsl        = d.verify_ssl !== false  // по умолчанию true
        this.agentMode             = d.agent_mode         || ''
        this.testResult            = null
        // Загружаем маппинги
        try {
          const mr = await axios.get(generateUrl('/apps/ncaclmanager/api/mounts/admin'))
          this.mounts = Array.isArray(mr.data?.mounts) ? mr.data.mounts : []
        } catch (_) { this.mounts = [] }
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
        const payload = {
          agent_url:          this.form.agentUrl,
          client_cert:        this.form.clientCert,
          timeout:            String(this.form.timeout),
          admin_groups:       this.form.adminGroups,
          nc_admin_users:     this.form.ncAdminUsers,
          owner_mode_enabled: this.form.ownerModeEnabled ? 'true' : 'false',
          verify_ssl:         this.form.verifySsl ? 'true' : 'false',
        }
        if (this.form.bearerToken)  payload.bearer_token  = this.form.bearerToken
        if (this.form.certPassword) payload.cert_password = this.form.certPassword

        await saveSettings(payload)
        this.saveSuccess = true
        if (this.form.bearerToken)  { this.form.bearerTokenSet  = true; this.form.bearerToken  = '' }
        if (this.form.certPassword) { this.form.certPasswordSet = true; this.form.certPassword = '' }
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
      const payload = {
        agent_url:   this.form.agentUrl,
        client_cert: this.form.clientCert,
        timeout:     this.form.timeout,
        verify_ssl:  this.form.verifySsl,
      }
      if (this.form.bearerToken)  payload.bearer_token  = this.form.bearerToken
      if (this.form.certPassword) payload.cert_password = this.form.certPassword

      try {
        this.testResult = await testAgent(payload)
        if (this.testResult && this.testResult.agent_mode) {
          this.agentMode = this.testResult.agent_mode
        }
      } catch (e) {
        this.testResult = { success: false, error: e.message, diagnostics: null, curl_command: null }
      } finally {
        this.testLoading = false
      }
    },

    async uploadCert(event) {
      const file = event.target.files && event.target.files[0]
      if (!file) return
      this.uploadingCert    = true
      this.certUploadResult = null
      try {
        const token = (document.querySelector('head > meta[name="requesttoken"]') || {}).content || ''
        const url   = generateUrl('/apps/ncaclmanager/api/settings/upload-cert')
        const fd    = new FormData()
        fd.append('cert', file)
        const resp   = await fetch(url, { method: 'POST', headers: { requesttoken: token }, body: fd })
        const result = await resp.json()
        this.certUploadResult = result
        if (result.success && result.cert_path) this.form.clientCert = result.cert_path
      } catch (e) {
        this.certUploadResult = { success: false, error: e.message }
      } finally {
        this.uploadingCert = false
        event.target.value = ''
      }
    },

    copyCurl() {
      const cmd = this.testResult && this.testResult.curl_command
      if (!cmd) return
      if (navigator.clipboard) {
        navigator.clipboard.writeText(cmd).then(() => {
          this.curlCopied = true
          setTimeout(() => { this.curlCopied = false }, 2500)
        })
      }
    },

    addAdminGroup() {
      const g = this.newGroup.trim()
      if (!g || this.form.adminGroups.includes(g)) return
      this.form.adminGroups.push(g)
      this.newGroup = ''
    },
    removeAdminGroup(idx) { this.form.adminGroups.splice(idx, 1) },

    onNcUserInput() {
      clearTimeout(this.ncUserTimer)
      if (this.ncUserQuery.length < 2) { this.ncUserResults = []; return }
      this.ncUserLoading = true
      this.ncUserTimer = setTimeout(async () => {
        try {
          // Используем axios из nc.js — он добавляет requesttoken и OCS-APIREQUEST
          const res = await axios.get(
            generateUrl('/apps/ncaclmanager/api/settings/nc-users'),
            { params: { q: this.ncUserQuery } }
          )
          this.ncUserResults = Array.isArray(res.data.users) ? res.data.users : []
        } catch (e) {
          this.ncUserResults = []
        } finally {
          this.ncUserLoading = false
        }
      }, 300)
    },
    addNcUser(user) {
      if (!user || !user.uid) return
      if (this.form.ncAdminUsers.includes(user.uid)) return
      this.form.ncAdminUsers.push(user.uid)
      this.ncUserQuery   = ''
      this.ncUserResults = []
    },
    removeNcUser(idx) { this.form.ncAdminUsers.splice(idx, 1) },

    // ── Маппинги ─────────────────────────────────────────────────────
    addMount() {
      this.mounts.push({ ncPath: '', uncPath: '', description: '' })
    },
    removeMount(idx) { this.mounts.splice(idx, 1) },
    async saveMounts() {
      this.mountsSaving = true
      this.mountsSaveOk = false
      try {
        await axios.post(generateUrl('/apps/ncaclmanager/api/mounts'), { mounts: this.mounts })
        this.mountsSaveOk = true
        setTimeout(() => { this.mountsSaveOk = false }, 3000)
      } catch (e) { showError('Ошибка сохранения маппингов: ' + e.message) }
      finally { this.mountsSaving = false }
    },
  },
}
</script>

<style scoped>
.acl-settings { max-width: 740px; padding: 24px; font-family: inherit; }
.acl-settings h2 { font-size: 20px; font-weight: 700; margin-bottom: 24px; color: var(--color-main-text); }

.acl-settings__section {
  border: 1px solid var(--color-border); border-radius: var(--border-radius-large);
  padding: 20px 24px; margin-bottom: 20px; display: flex; flex-direction: column; gap: 14px;
}
.acl-settings__section h3 { font-size: 15px; font-weight: 600; margin: 0; color: var(--color-main-text); }
.acl-settings__section--test { border-color: rgba(255,140,0,.5); }
.acl-settings__desc { font-size: 13px; color: var(--color-text-maxcontrast); margin: 0; line-height: 1.5; }

.acl-settings__label { display: flex; flex-direction: column; gap: 5px; font-size: 13px; font-weight: 500; color: var(--color-main-text); }
.acl-settings__input {
  padding: 8px 10px; border: 1px solid var(--color-border); border-radius: var(--border-radius);
  background: var(--color-main-background); color: var(--color-main-text);
  font-size: 13px; width: 100%; box-sizing: border-box;
}
.acl-settings__input--short { width: 80px; }
.acl-settings__input--mono  { font-family: monospace; font-size: 12px; }
.acl-settings__input:focus  { outline: 2px solid var(--color-primary); border-color: transparent; }
.acl-settings__hint { font-size: 11px; color: var(--color-text-maxcontrast); }

.acl-settings__cert-block { display: flex; flex-direction: column; gap: 6px; }
.acl-settings__cert-row   { display: flex; gap: 6px; }
.acl-settings__cert-row .acl-settings__input { flex: 1; }
.acl-settings__cert-upload { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.acl-settings__cert-or { font-size: 12px; color: var(--color-text-maxcontrast); }
.acl-settings__cert-upload label.button { cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
.acl-settings__cert-upload-result { font-size: 12px; }
.acl-settings__cert-upload-result.ok  { color: var(--color-success, green); }
.acl-settings__cert-upload-result.err { color: var(--color-error, red); }

.acl-settings__test-row { display: flex; gap: 10px; flex-wrap: wrap; }
.acl-settings__test-result-block { display: flex; flex-direction: column; gap: 10px; }

.acl-settings__test-status { padding: 10px 16px; border-radius: var(--border-radius); font-size: 14px; font-weight: 600; }
.acl-settings__test-status--ok   { background: rgba(0,130,0,.1); color: var(--color-success, green); }
.acl-settings__test-status--fail { background: rgba(200,0,0,.1); color: var(--color-error, red); }
.acl-settings__test-err { font-weight: 400; font-size: 13px; margin-left: 6px; }

.acl-settings__diag { border: 1px solid var(--color-border); border-radius: var(--border-radius); overflow: hidden; }
.acl-settings__diag-header { display: flex; justify-content: space-between; padding: 8px 12px; background: var(--color-background-hover); cursor: pointer; font-size: 13px; font-weight: 500; user-select: none; }
.acl-settings__diag-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.acl-settings__diag-table tr:nth-child(even) { background: var(--color-background-hover); }
.acl-settings__diag-key { padding: 5px 12px; color: var(--color-text-maxcontrast); width: 150px; font-family: monospace; vertical-align: top; }
.acl-settings__diag-val { padding: 5px 12px; font-family: monospace; word-break: break-all; }
.acl-settings__diag-val.ok   { color: var(--color-success, green); }
.acl-settings__diag-val.warn { color: var(--color-warning, orange); }

.acl-settings__curl { border: 1px solid var(--color-border); border-radius: var(--border-radius); overflow: hidden; }
.acl-settings__curl-header { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: var(--color-background-hover); font-size: 13px; font-weight: 500; }
.acl-settings__curl-code { margin: 0; padding: 14px 16px; background: #1a1a2e; color: #a8d8ea; font-family: monospace; font-size: 12px; line-height: 1.7; overflow-x: auto; white-space: pre; }

.acl-settings__warn-box { padding: 10px 14px; border: 1px dashed var(--color-warning, orange); border-radius: var(--border-radius); color: var(--color-warning, orange); font-size: 13px; }
.acl-settings__warn-box--soft { border-color: var(--color-border); color: var(--color-text-maxcontrast); }

.acl-settings__groups { display: flex; flex-direction: column; gap: 6px; }
.acl-settings__group-row { display: flex; align-items: center; gap: 8px; padding: 7px 12px; background: var(--color-background-hover); border: 1px solid var(--color-border); border-radius: var(--border-radius); }
.acl-settings__group-name { flex: 1; font-size: 13px; }
.acl-settings__add-group { display: flex; gap: 8px; align-items: center; }
.acl-settings__add-group .acl-settings__input { flex: 1; }

.acl-settings__test-badge { display: inline-block; margin-left: 8px; padding: 2px 8px; border-radius: 10px; background: rgba(255,140,0,.15); color: #8b6000; font-size: 11px; font-weight: 600; vertical-align: middle; }

.acl-settings__nc-user-search { position: relative; }
.acl-settings__nc-user-results { position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: var(--color-main-background); border: 1px solid var(--color-border); border-radius: var(--border-radius-large); box-shadow: 0 4px 16px rgba(0,0,0,.12); z-index: 9999; list-style: none; padding: 4px; margin: 0; max-height: 240px; overflow-y: auto; }
.acl-settings__nc-user-result { display: flex; align-items: center; gap: 8px; padding: 7px 10px; border-radius: var(--border-radius); cursor: pointer; }
.acl-settings__nc-user-result:hover { background: var(--color-background-hover); }
.acl-settings__nc-user-result.disabled { opacity: .5; cursor: default; }
.acl-settings__nc-user-avatar { width: 30px; height: 30px; border-radius: 50%; background: var(--color-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
.acl-settings__nc-user-name { font-size: 13px; font-weight: 500; flex: 1; }
.acl-settings__nc-user-uid { font-size: 11px; color: var(--color-text-maxcontrast); font-family: monospace; }
.acl-settings__nc-user-added { font-size: 11px; color: var(--color-success, green); }
.acl-settings__nc-user-empty { padding: 10px; text-align: center; font-size: 13px; color: var(--color-text-maxcontrast); }

.acl-settings__checkbox { display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; }
.acl-settings__note { padding: 10px 14px; border-radius: var(--border-radius); font-size: 13px; line-height: 1.5; }
.acl-settings__note--success { background: rgba(0,130,0,.1); color: var(--color-success, green); }
.acl-settings__note--error   { background: rgba(200,0,0,.1); color: var(--color-error, red); }
.acl-settings__note--info    { background: rgba(0,100,200,.1); color: #0064c8; }
.acl-settings__note--warn    { background: rgba(255,140,0,.1); color: #8b6000; border: 1px solid rgba(255,140,0,.3); }
.acl-settings__note--warn code { background: rgba(0,0,0,.1); padding: 2px 5px; border-radius: 3px; font-size: 11px; display: inline-block; margin: 2px 0; word-break: break-all; }

.acl-settings__mounts { display: flex; flex-direction: column; gap: 10px; }
.acl-settings__mount-row { border: 1px solid var(--color-border); border-radius: var(--border-radius); padding: 10px; }
.acl-settings__mount-paths { display: flex; align-items: flex-end; gap: 8px; flex-wrap: wrap; }
.acl-settings__mount-nc,
.acl-settings__mount-unc,
.acl-settings__mount-desc { display: flex; flex-direction: column; gap: 4px; }
.acl-settings__mount-nc  { flex: 1.2; min-width: 160px; }
.acl-settings__mount-unc { flex: 1.5; min-width: 200px; }
.acl-settings__mount-desc { flex: 1; min-width: 120px; }
.acl-settings__mount-label { font-size: 11px; color: var(--color-text-maxcontrast); }
.acl-settings__mount-arrow { font-size: 18px; color: var(--color-primary); padding-bottom: 8px; flex-shrink: 0; }
.acl-settings__mount-add { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

.fade-enter-active, .fade-leave-active { transition: opacity .2s, transform .2s; }
.fade-enter-from,   .fade-leave-to     { opacity: 0; transform: translateY(-6px); }
</style>
