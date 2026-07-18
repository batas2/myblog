FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_sqlite mbstring \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

EXPOSE 8080

CMD ["sh", "-c", "php tools/build.php && php -S 0.0.0.0:8080"]
