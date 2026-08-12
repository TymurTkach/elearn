# Исправление ошибки 500 после изменения прав доступа

## Проблема
После изменения прав доступа появляется ошибка HTTP 500 при входе в администрацию.

## Решение

Выполните следующие команды на сервере:

```bash
# 1. Установите правильные права на файлы и папки
sudo find /var/www/html/elearn -type d -exec chmod 755 {} \;
sudo find /var/www/html/elearn -type f -exec chmod 644 {} \;

# 2. Установите владельца файлов (важно для чтения веб-сервером)
sudo chown -R uboxuser:www-data /var/www/html/elearn

# 3. Убедитесь, что папка uploads доступна для записи
sudo chmod 775 /var/www/html/elearn/uploads
sudo chmod 775 /var/www/html/elearn/uploads/courses

# 4. Проверьте права на папку для сессий PHP (если используется)
# Обычно это /var/lib/php/sessions или /tmp
# Убедитесь, что www-data может писать в эту папку
```

## Проверка

1. Откройте в браузере: `http://ваш-сервер/elearn/admin/test.php`
   - Это покажет, какие именно компоненты не работают

2. Проверьте логи ошибок PHP:
```bash
sudo tail -f /var/log/apache2/error.log
# или
sudo tail -f /var/log/nginx/error.log
# или
sudo tail -f /var/log/php-fpm/error.log
```

3. Убедитесь, что файлы доступны для чтения:
```bash
sudo -u www-data cat /var/www/html/elearn/config.php
sudo -u www-data cat /var/www/html/elearn/auth.php
sudo -u www-data cat /var/www/html/elearn/admin/index.php
```

Если эти команды не выдают ошибок, значит файлы доступны для чтения.

## Альтернативное решение

Если проблема сохраняется, попробуйте:

```bash
# Дать веб-серверу права на чтение всех файлов
sudo chmod -R o+r /var/www/html/elearn
sudo chmod -R o+X /var/www/html/elearn
```

Это даст всем пользователям (включая www-data) права на чтение файлов и выполнение папок.

