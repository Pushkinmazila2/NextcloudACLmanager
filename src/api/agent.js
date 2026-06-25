import { axios, generateUrl } from './nc.js'

const base = (path) => generateUrl(`/apps/ncaclmanager/api${path}`)

// ── ACL ───────────────────────────────────────────────────────────────
export const getAcl = (path) =>
  axios.get(base('/acl'), { params: { path } }).then(r => r.data)

export const setAcl = (path, groupIdentity, permission, action = 'Allow', comment = null) =>
  axios.post(base('/acl'), { path, groupIdentity, permission, action, comment }).then(r => r.data)

export const removeAcl = (path, groupIdentity, comment = null) =>
  axios.delete(base('/acl'), { data: { path, groupIdentity, comment } }).then(r => r.data)

// ── Группы ────────────────────────────────────────────────────────────
export const getFolderGroups = (path) =>
  axios.get(base('/groups'), { params: { path } }).then(r => r.data)

export const createFolderGroups = (path, suffixes = ['RO', 'RX', 'RW']) =>
  axios.post(base('/groups'), { path, suffixes }).then(r => r.data)

export const deleteFolderGroups = (path) =>
  axios.delete(base('/groups'), { data: { path } }).then(r => r.data)

// ── Состав группы ─────────────────────────────────────────────────────
export const getGroupMembers = (groupName) =>
  axios.get(base(`/groups/${encodeURIComponent(groupName)}/members`)).then(r => r.data)

export const addGroupMember = (groupName, userSam, comment = null) =>
  axios.post(base(`/groups/${encodeURIComponent(groupName)}/members`),
    { userSamName: userSam, comment }).then(r => r.data)

export const removeGroupMember = (groupName, userSam, comment = null) =>
  axios.delete(
    base(`/groups/${encodeURIComponent(groupName)}/members/${encodeURIComponent(userSam)}`),
    { data: { comment } }).then(r => r.data)

// ── Пользователи ──────────────────────────────────────────────────────
export const searchUsers = (q, max = 20) =>
  axios.get(base('/users/search'), { params: { q, max } }).then(r => r.data)

export const getManagerChain = (sam) =>
  axios.get(base(`/users/${encodeURIComponent(sam)}/manager-chain`)).then(r => r.data)

// ── Настройки ─────────────────────────────────────────────────────────
export const getSettings = () =>
  axios.get(base('/settings')).then(r => r.data)

export const saveSettings = (data) =>
  axios.post(base('/settings'), data).then(r => r.data)

export const testAgent = () =>
  axios.post(base('/settings/test-agent')).then(r => r.data)
