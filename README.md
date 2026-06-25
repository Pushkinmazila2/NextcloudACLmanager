# NcAclManager — Nextcloud плагин управления ACL

Плагин добавляет в файловый менеджер Nextcloud возможность управлять
NTFS правами на Windows шарах через AD группы.

## Требования

- Nextcloud 28–30
- PHP 8.1+
- Node.js 18+ (для сборки JS)
- Запущенный NcAclAgent на Windows сервере

## Структура

```
ncaclmanager/
├── appinfo/
│   ├── info.xml          — метаданные плагина
│   └── routes.php        — API маршруты
├── lib/
│   ├── AppInfo/          — точка входа, регистрация
│   ├── Controller/       — AclController, GroupsController, UsersController, SettingsController
│   ├── Listener/         — подключение JS в Files
│   ├── Service/          — AgentService, AuthorizationService
│   └── Settings/         — AdminSettings, AdminSection
├── src/
│   ├── api/agent.js      — HTTP клиент к NC backend
│   ├── composables/      — useAcl.js (логика состояния)
│   ├── components/       — Vue компоненты
│   │   ├── AclPanel.vue        — боковая панель
│   │   ├── AclGroupRow.vue     — строка группы с членами
│   │   ├── UserSearchInput.vue — поиск пользователей
│   │   └── AdminSettings.vue   — страница настроек
│   ├── files.js          — регистрация в файловом менеджере
│   └── settings.js       — точка входа страницы настроек
├── templates/admin.php   — шаблон страницы настроек
├── css/ncaclmanager.css  — стили
├── img/lock.svg          — иконка
├── package.json
└── webpack.config.js
```

## Установка

### 1. Собрать JS

```bash
cd ncaclmanager
npm install
npm run build
```

### 2. Скопировать в Nextcloud

```bash
cp -r ncaclmanager /var/www/nextcloud/apps/
```

### 3. Включить плагин

```bash
sudo -u www-data php /var/www/nextcloud/occ app:enable ncaclmanager
```

### 4. Настроить плагин

Открыть: **Настройки → Администрирование → ACL Manager**

Заполнить:
- URL агента: `https://10.0.1.50:8443`
- Bearer токен (из `nc-plugin-connection.txt` на Windows сервере)
- Путь к клиентскому PFX сертификату на NC сервере
- Пароль сертификата
- Группы администраторов ACL

Нажать **Проверить соединение** — убедиться что агент доступен.

## Использование

### Контекстное меню

Правый клик на папке → **ACL / Права доступа** → открывается боковая панель.

### Боковая панель

- Показывает группы RO / RX / RW для текущей папки
- Клик на группу — раскрывает список членов
- Поиск пользователей (минимум 3 символа)
- Предупреждение если пользователь уже имеет доступ через другую группу
- Кнопка создания групп (если ещё нет) — только для администраторов

## Переменные окружения агента

Для Prod режима на Windows сервере:

```
NCACL_MODE=Prod
NCACL_AGENT__SECURITY__BEARERTOKEN=<токен>
NCACL_AGENT__SECURITY__CLIENTCERTIFICATE__TRUSTEDCATHUMBPRINT=<thumbprint>
NCACL_AGENT__LISTEN__CERTIFICATEPASSWORD=<пароль>
```

## Поток данных

```
Браузер пользователя
  │
  ▼
Nextcloud (PHP контроллеры)
  │  mTLS + Bearer Token
  ▼
NcAclAgent (Windows)
  │
  ├── NTFS ACL (System.Security.AccessControl)
  └── Active Directory (DirectoryServices)
```
