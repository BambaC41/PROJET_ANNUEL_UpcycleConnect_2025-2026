FROM php:8.2-apache

# Remplacer les sources Debian Trixie par Bookworm (stable)
RUN sed -i 's/deb.debian.org\/debian trixie/deb.debian.org\/debian bookworm/g' /etc/apt/sources.list \
    && sed -i 's/deb.debian.org\/debian-security trixie-security/deb.debian.org\/debian-security bookworm-security/g' /etc/apt/sources.list \
    && sed -i 's/deb.debian.org\/debian trixie-updates/deb.debian.org\/debian bookworm-updates/g' /etc/apt/sources.list

# Installer les dépendances système
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Installer les extensions PHP
RUN docker-php-ext-install pdo pdo_mysql mysqli zip

# Activer mod_rewrite
RUN a2enmod rewrite

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copier le code source
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/uploads \
    && chmod -R 755 /var/www/html/storage

EXPOSE 80