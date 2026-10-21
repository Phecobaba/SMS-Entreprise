FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends openssh-server cron git unzip libzip-dev libpng-dev libjpeg62-turbo-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo_mysql zip gd \
    && a2enmod rewrite \
    && useradd --create-home --gid www-data --shell /bin/bash oakfield \
    && mkdir -p /run/sshd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY apache.conf /etc/apache2/sites-available/000-default.conf
COPY entrypoint.sh /usr/local/bin/oakfield-local-entrypoint

RUN chmod 755 /usr/local/bin/oakfield-local-entrypoint \
    && printf '%s\n' 'PasswordAuthentication no' 'PermitRootLogin no' 'AllowUsers oakfield' >> /etc/ssh/sshd_config

EXPOSE 22 80
ENTRYPOINT ["/usr/local/bin/oakfield-local-entrypoint"]
