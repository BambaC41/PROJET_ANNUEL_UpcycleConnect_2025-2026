FROM php:8.2-apache-bookworm

# Forcer HTTPS pour les dépôts Debian (contournement du blocage HTTP)
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g; s|http://security.debian.org|https://security.debian.org|g' /etc/apt/sources.list.d/debian.sources 2>/dev/null || \
    sed -i 's|http://deb.debian.org|https://deb.debian.org|g; s|http://security.debian.org|https://security.debian.org|g' /etc/apt/sources.list

# Forcer apt à utiliser IPv4 (au cas où l'IPv6 serait mal routé)
RUN echo 'Acquire::ForceIPv4 "true";' > /etc/apt/apt.conf.d/99force-ipv4

# Mettre à jour et installer les dépendances
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    curl \
    ca-certificates \
    apt-transport-https \
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