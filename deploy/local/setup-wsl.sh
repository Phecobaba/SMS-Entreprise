#!/bin/sh
set -eu

# Run as root inside the project-scoped Alpine WSL distro.
project="$(cd "$(dirname "$0")/../.." && pwd)"
app_root='/home/oakfield/oakfield-sms'
shared="$app_root/shared"

test -f "$project/deploy/local/.local/id_ed25519.pub"
test -f /mnt/c/ProgramData/ComposerSetup/bin/composer.phar
apk add --no-cache php82-gd

if ! id oakfield >/dev/null 2>&1; then
    adduser -D -h /home/oakfield -s /bin/bash oakfield
fi
# Alpine creates a locked account; OpenSSH rejects public-key login for it.
# Password login remains disabled in sshd_config.
passwd -d oakfield >/dev/null
addgroup oakfield www-data || true
ln -sfn /usr/bin/php82 /usr/local/bin/php
cp /mnt/c/ProgramData/ComposerSetup/bin/composer.phar /usr/local/bin/composer.phar
printf '#!/bin/sh\nexec /usr/bin/php82 /usr/local/bin/composer.phar "$@"\n' > /usr/local/bin/composer
chmod 755 /usr/local/bin/composer

mkdir -p /home/oakfield/.ssh "$shared/storage/framework/cache" \
    "$shared/storage/framework/sessions" "$shared/storage/framework/views" \
    "$shared/storage/logs" "$app_root/releases" "$app_root/placeholder/public" \
    /run/apache2 /run/mysqld
if [ ! -e "$app_root/current" ]; then
    ln -s "$app_root/placeholder" "$app_root/current"
fi
cp "$project/deploy/local/.local/id_ed25519.pub" /home/oakfield/.ssh/authorized_keys
chmod 700 /home/oakfield/.ssh
chmod 600 /home/oakfield/.ssh/authorized_keys

if [ ! -d /var/lib/mysql/mysql ]; then
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null
fi
chown mysql:mysql /run/mysqld
if mysqladmin ping --silent >/dev/null 2>&1; then
    mysqladmin shutdown
fi
mariadbd --user=mysql --datadir=/var/lib/mysql --socket=/run/mysqld/mysqld.sock \
    --pid-file=/run/mysqld/mysqld.pid --bind-address=127.0.0.1 \
    --skip-networking=0 --port=3306 >/var/log/mysql.log 2>&1 &
sleep 5
mysqladmin ping --silent
mysql -e "CREATE DATABASE IF NOT EXISTS oakfield_local; CREATE USER IF NOT EXISTS 'oakfield'@'localhost' IDENTIFIED BY 'local-only-password'; GRANT ALL PRIVILEGES ON oakfield_local.* TO 'oakfield'@'localhost'; FLUSH PRIVILEGES;"

if [ ! -f "$shared/.env" ]; then
    app_key="$(php -r 'echo "base64:",base64_encode(random_bytes(32));')"
    printf '%s\n' \
        'APP_NAME="Oakfield Local Deployment"' \
        'APP_ENV=production' \
        "APP_KEY=$app_key" \
        'APP_DEBUG=false' \
        'APP_URL=http://localhost:8080' \
        'APP_MODE=single_school' \
        'DEFAULT_SCHOOL_ID=1' \
        'LOG_CHANNEL=stack' \
        'LOG_LEVEL=warning' \
        'DB_CONNECTION=mysql' \
        'DB_HOST=127.0.0.1' \
        'DB_PORT=3306' \
        'DB_DATABASE=oakfield_local' \
        'DB_USERNAME=oakfield' \
        'DB_PASSWORD=local-only-password' \
        'SESSION_DRIVER=database' \
        'SESSION_ENCRYPT=true' \
        'CACHE_STORE=database' \
        'QUEUE_CONNECTION=sync' \
        'FILESYSTEM_DISK=local' \
        'MAIL_MAILER=log' > "$shared/.env"
fi

chown -R oakfield:www-data /home/oakfield
chmod 750 /home/oakfield "$app_root" "$shared"
chmod 640 "$shared/.env"
find "$shared/storage" -type d -exec chmod 2770 {} +
find "$shared/storage" -type f -exec chmod 660 {} +

cp "$project/deploy/local/wsl-apache.conf" /etc/apache2/conf.d/oakfield.conf
sed -i 's/^Listen 80$/# Listen 80/' /etc/apache2/httpd.conf
sed -i 's/^#LoadModule rewrite_module/LoadModule rewrite_module/' /etc/apache2/httpd.conf
sed -i 's/^LoadModule mpm_event_module/#LoadModule mpm_event_module/' /etc/apache2/httpd.conf
sed -i 's/^#LoadModule mpm_prefork_module/LoadModule mpm_prefork_module/' /etc/apache2/httpd.conf
httpd -t

ssh-keygen -A >/dev/null
printf '%s\n' 'Port 2222' 'PubkeyAuthentication yes' 'PasswordAuthentication no' \
    'AllowUsers oakfield' > /etc/ssh/sshd_config.d/oakfield.conf
mkdir -p /run/sshd
if ! pidof sshd >/dev/null; then /usr/sbin/sshd; fi
if ! pidof httpd >/dev/null; then /usr/sbin/httpd; fi

printf '%s\n' '* * * * * cd /home/oakfield/oakfield-sms/current && /usr/bin/php82 artisan schedule:run >> /home/oakfield/oakfield-sms/shared/storage/logs/cron.log 2>&1' \
    > /var/spool/cron/crontabs/oakfield
chown root:root /var/spool/cron/crontabs/oakfield
chmod 600 /var/spool/cron/crontabs/oakfield
if ! pidof crond >/dev/null; then /usr/sbin/crond -b; fi

echo 'Alpine PHP 8.2 SSH/Apache/cron deployment target is ready.'
