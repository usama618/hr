FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev default-mysql-client \
    && docker-php-ext-install intl pdo_mysql \
    && echo "date.timezone=Europe/Berlin" > /usr/local/etc/php/conf.d/timezone.ini \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .
RUN composer dump-autoload --optimize --no-interaction

COPY docker/entrypoint.sh /usr/local/bin/hr-entrypoint
RUN chmod +x /usr/local/bin/hr-entrypoint

EXPOSE 8000

ENTRYPOINT ["hr-entrypoint"]
