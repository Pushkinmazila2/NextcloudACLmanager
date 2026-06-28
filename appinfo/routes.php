<?php
declare(strict_types=1);
return [
    'routes' => [
        // ── ACL ──────────────────────────────────────────────────────
        ['name' => 'acl#get',    'url' => '/api/acl', 'verb' => 'GET'],
        ['name' => 'acl#set',    'url' => '/api/acl', 'verb' => 'POST'],
        ['name' => 'acl#remove', 'url' => '/api/acl', 'verb' => 'DELETE'],

        // ── Группы ───────────────────────────────────────────────────
        ['name' => 'groups#getFolderGroups',    'url' => '/api/groups',                               'verb' => 'GET'],
        ['name' => 'groups#createFolderGroups', 'url' => '/api/groups',                               'verb' => 'POST'],
        ['name' => 'groups#deleteFolderGroups', 'url' => '/api/groups',                               'verb' => 'DELETE'],
        ['name' => 'groups#getMembers',         'url' => '/api/groups/{groupName}/members',           'verb' => 'GET'],
        ['name' => 'groups#addMember',          'url' => '/api/groups/{groupName}/members',           'verb' => 'POST'],
        ['name' => 'groups#removeMember',       'url' => '/api/groups/{groupName}/members/{userSam}', 'verb' => 'DELETE'],

        // ── Пользователи ─────────────────────────────────────────────
        ['name' => 'users#search',       'url' => '/api/users/search',              'verb' => 'GET'],
        ['name' => 'users#managerChain', 'url' => '/api/users/{sam}/manager-chain', 'verb' => 'GET'],

        // ── Настройки ────────────────────────────────────────────────
        ['name' => 'settings#getSettings',   'url' => '/api/settings',               'verb' => 'GET'],
        ['name' => 'settings#saveSettings',  'url' => '/api/settings',               'verb' => 'POST'],
        ['name' => 'settings#testAgent',     'url' => '/api/settings/test-agent',    'verb' => 'POST'],
        ['name' => 'settings#uploadCert',    'url' => '/api/settings/upload-cert',   'verb' => 'POST'],
        ['name' => 'settings#searchNcUsers', 'url' => '/api/settings/nc-users',      'verb' => 'GET'],

        // ── Маппинги NC путей → UNC ──────────────────────────────────
        ['name' => 'mounts#index',      'url' => '/api/mounts',       'verb' => 'GET'],
        ['name' => 'mounts#adminIndex', 'url' => '/api/mounts/admin', 'verb' => 'GET'],
        ['name' => 'mounts#save',       'url' => '/api/mounts',       'verb' => 'POST'],
    ],
];