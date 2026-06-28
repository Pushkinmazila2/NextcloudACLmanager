# Установка NcAclManager

## 1. Скопировать плагин

```bash
cp -r ncaclmanager /var/www/nextcloud/apps/
sudo chown -R www-data:www-data /var/www/nextcloud/apps/ncaclmanager
sudo -u www-data php /var/www/nextcloud/occ app:enable ncaclmanager
```

## 2. Разрешить запросы к локальным адресам (ОБЯЗАТЕЛЬНО)

Nextcloud по умолчанию блокирует HTTP запросы к приватным IP адресам
(10.x.x.x, 172.16-31.x.x, 192.168.x.x).
Агент работает внутри сети — нужно разрешить:

```bash
sudo -u www-data php /var/www/nextcloud/occ \
  config:system:set allow_local_remote_servers --value=true --type=bool
```

Или вручную в `config/config.php`:
```php
'allow_local_remote_servers' => true,
```

### Docker / Compose

Если NC в Docker а агент на хосте (172.20.x.x — Docker bridge):

```bash
docker exec -u www-data nextcloud php occ \
  config:system:set allow_local_remote_servers --value=true --type=bool
```

Или добавить в `config/config.php` который монтируется в контейнер.

## 3. Настроить плагин

Открыть: **Настройки NC → Администрирование → ACL Manager**

Заполнить:
- URL агента (например `https://172.20.112.1:8443`)
- Bearer токен (из `C:\ProgramData\NcAclAgent\nc-plugin-connection.txt`)
- Загрузить .pfx или указать путь к клиентскому сертификату
- Добавить группы администраторов ACL

Нажать **Проверить соединение**.

## 4. Проверить вкладку ACL

Вкладка ACL появляется в боковой панели только:
- Для **папок** (не файлов)
- Только если пользователь входит в одну из групп администраторов ACL
  (или включён режим делегирования)

Если вкладки нет — проверьте что:
1. Настройки сохранены и хотя бы одна группа добавлена
2. Текущий пользователь входит в эту группу в NC
3. Выполнить: `sudo -u www-data php occ app:list | grep ncaclmanager`
