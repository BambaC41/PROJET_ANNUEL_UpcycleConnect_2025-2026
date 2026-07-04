FROM php:8.2-apache

# Installer les extensions PHP nécessaires
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Activer mod_rewrite
RUN a2enmod rewrite

# Installer Composer (curl est déjà présent dans l'image)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copier le code source
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/uploads \
    && chmod -R 755 /var/www/html/storage

EXPOSE 80