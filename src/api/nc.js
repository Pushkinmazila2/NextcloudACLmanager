/**
 * HTTP обёртка для Nextcloud.
 * NC не экспортирует axios как window.axios — он доступен через ES module import.
 * Используем fetch напрямую с CSRF токеном.
 */

// ── CSRF токен ────────────────────────────────────────────────────────

function getRequestToken() {
  // NC кладёт токен в мета-тег
  const meta = document.querySelector('head > meta[name="requesttoken"]')
  if (meta?.content) return meta.content
  // Или в глобальную переменную
  if (typeof window !== 'undefined' && window.OC?.requestToken) return window.OC.requestToken
  return ''
}

// ── Базовый fetch с CSRF и JSON ───────────────────────────────────────

async function ncFetch(method, url, { params, data } = {}) {
  // Добавляем query params
  if (params && Object.keys(params).length) {
    url += '?' + new URLSearchParams(params).toString()
  }

  const token = getRequestToken()
  const headers = {
    'Accept':       'application/json',
    'OCS-APIREQUEST': 'true',
  }
  if (token) headers['requesttoken'] = token

  const init = { method, headers }

  if (data !== undefined) {
    headers['Content-Type'] = 'application/json'
    init.body = JSON.stringify(data)
  }

  const response = await fetch(url, init)

  if (!response.ok) {
    const text = await response.text()
    throw new Error(`HTTP ${response.status}: ${text.slice(0, 200)}`)
  }

  const text = await response.text()
  if (!text) return {}

  try {
    return JSON.parse(text)
  } catch {
    throw new Error('Невалидный JSON от сервера: ' + text.slice(0, 100))
  }
}

// ── Публичный API — имитирует axios интерфейс ─────────────────────────

export const axios = {
  get:    (url, cfg)       => ncFetch('GET',    url, { params: cfg?.params }).then(d => ({ data: d })),
  post:   (url, data, cfg) => ncFetch('POST',   url, { data }).then(d => ({ data: d })),
  delete: (url, cfg)       => ncFetch('DELETE', url, { data: cfg?.data }).then(d => ({ data: d })),
}

// ── URL хелпер ────────────────────────────────────────────────────────

export function generateUrl(path) {
  if (typeof window !== 'undefined' && window.OC?.generateUrl) {
    return window.OC.generateUrl(path)
  }
  // Стандартный fallback для NC без pretty URLs
  const base = window.location?.origin ?? ''
  return base + '/index.php' + path
}

// ── Уведомления ───────────────────────────────────────────────────────

export function showError(msg) {
  console.error('[NcAclManager]', msg)
  try {
    // NC 26+ toast
    if (window.OC?.Notification?.showTemporary) {
      window.OC.Notification.showTemporary(msg, { type: 'error' })
    }
  } catch (e) { /* игнорируем */ }
}

export function showSuccess(msg) {
  console.info('[NcAclManager]', msg)
  try {
    if (window.OC?.Notification?.showTemporary) {
      window.OC.Notification.showTemporary(msg)
    }
  } catch (e) { /* игнорируем */ }
}

// ── Перевод ───────────────────────────────────────────────────────────

export function t(app, str, vars = {}) {
  if (typeof window !== 'undefined' && typeof window.t === 'function') {
    return window.t(app, str, vars)
  }
  return Object.entries(vars).reduce((s, [k, v]) => s.replace(`{${k}}`, v), str)
}
