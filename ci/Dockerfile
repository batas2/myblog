# The CI image: the production PHP (MyDevil runs 8.3) plus what the pipeline needs.
# Built on the runner host by ci/build-image.sh; jobs run in it via the runner label blog-ci.
FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git openssh-client rsync python3 curl sqlite3 libxml2-utils git-filter-repo ca-certificates \
        libsqlite3-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_sqlite mbstring \
    && rm -rf /var/lib/apt/lists/*

ARG GITLEAKS_VERSION=8.30.1
ARG GITLEAKS_SHA256=551f6fc83ea457d62a0d98237cbad105af8d557003051f41f3e7ca7b3f2470eb
RUN curl -fsSL -o /tmp/gitleaks.tgz \
        "https://github.com/gitleaks/gitleaks/releases/download/v${GITLEAKS_VERSION}/gitleaks_${GITLEAKS_VERSION}_linux_x64.tar.gz" \
    && echo "${GITLEAKS_SHA256}  /tmp/gitleaks.tgz" | sha256sum -c - \
    && tar -xzf /tmp/gitleaks.tgz -C /usr/local/bin gitleaks \
    && rm /tmp/gitleaks.tgz

ENV LANG=C.UTF-8

RUN php -m | grep -qi '^pdo_sqlite$' && php -m | grep -qi '^mbstring$' && php -m | grep -qi '^gd$' \
    && command -v rrsync && git filter-repo --version && gitleaks version
