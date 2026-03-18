# 1. Image de base
FROM php:8.2-apache

# 2. On active les modules Rewrite (pour les URLs) et SSL (pour le HTTPS)
RUN a2enmod rewrite ssl

# 3. On crée la configuration Apache pour lui dire où trouver tes certificats
RUN echo "<VirtualHost *:443>\n\
    DocumentRoot /var/www/html\n\
    SSLEngine on\n\
    SSLCertificateFile /etc/ssl/certs-upcycle/nginx-selfsigned.crt\n\
    SSLCertificateKeyFile /etc/ssl/certs-upcycle/nginx-selfsigned.key\n\
    <Directory /var/www/html>\n\
        AllowOverride All\n\
    </Directory>\n\
</VirtualHost>" > /etc/apache2/sites-available/default-ssl.conf

# 4. On active le site sécurisé qu'on vient de créer
RUN a2ensite default-ssl

# 5. On met à jour et on copie ton code
RUN apt-get update && apt-get upgrade -y
COPY . /var/www/html/

# 6. Permissions
RUN chown -R www-data:www-data /var/www/html/ && chmod -R 755 /var/www/html/

# 7. On expose le port sécurisé 443 !
EXPOSE 443