#!/usr/bin/env bash
set -euo pipefail

app_root='/home/oakfield/oakfield-sms'
shared_path="$app_root/shared"

if [ ! -f /run/deploy-key.pub ]; then
    echo 'Missing deployment public key.' >&2
    exit 78
fi

mkdir -p /home/oakfield/.ssh "$shared_path/storage/framework/cache" \
    "$shared_path/storage/framework/sessions" "$shared_path/storage/framework/views" \
    "$shared_path/storage/logs" "$app_root/releases"
cp /run/deploy-key.pub /home/oakfield/.ssh/authorized_keys
chmod 700 /home/oakfield/.ssh
chmod 600 /home/oakfield/.ssh/authorized_keys

if [ ! -f "$shared_path/.env" ]; then
    app_key="$(php -r 'echo "base64:",base64_encode(random_bytes(32));')"
    cat > "$shared_path/.env" <<EOF
APP_NAME="Oakfield Local Deployment"
APP_ENV=production
APP_KEY=$app_key
APP_DEBUG=false
APP_URL=http://localhost:8080
APP_MODE=single_school
DEFAULT_SCHOOL_ID=1
LOG_CHANNEL=stack
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=oakfield_local
DB_USERNAME=oakfield
DB_PASSWORD=local-only-password
SESSION_DRIVER=database
SESSION_ENCRYPT=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
MAIL_MAILER=log
EOF
fi

chown -R oakfield:www-data /home/oakfield
chmod 750 /home/oakfield "$app_root" "$shared_path"
chmod -R u+rwX,g+rwX,o-rwx "$shared_path/storage"

cat > /etc/cron.d/oakfield <<EOF
* * * * * oakfield cd $app_root/current && /usr/local/bin/php artisan schedule:run >> $shared_path/storage/logs/cron.log 2>&1
EOF
chmod 644 /etc/cron.d/oakfield

ssh-keygen -A >/dev/null
/usr/sbin/sshd
cron
exec apache2-foreground
