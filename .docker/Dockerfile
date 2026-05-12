FROM php:8.2-apache

# Dependências de sistema para as extensões PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libzip-dev \
        libpng-dev \
        libxml2-dev \
        libicu-dev \
        libonig-dev \
        unzip \
        git \
    && rm -rf /var/lib/apt/lists/*

# Extensões PHP exigidas pelo projeto + PhpSpreadsheet
RUN docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mbstring \
        curl \
        zip \
        gd \
        intl

# Composer (binário oficial)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuração customizada do Apache (DocumentRoot=public + Alias /api)
COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers

# Entrypoint que instala dependências e inicia o Apache
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
