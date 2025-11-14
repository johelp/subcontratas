#!/bin/bash
# Script para configurar DocumentRoot de Apache

APACHE_CONF_FILE="/etc/apache2/sites-available/000-default.conf"
PROJECT_PATH="/workspaces/subcontratas"

echo "Configurando DocumentRoot de Apache a: $PROJECT_PATH"

# 1. Edita el DocumentRoot
sudo sed -i "s|DocumentRoot /var/www/html|DocumentRoot ${PROJECT_PATH}|g" $APACHE_CONF_FILE

# 2. Edita la directiva Directory
# Usamos un marcador de posición temporal para asegurar que el sed funcione
sudo sed -i "s|<Directory /var/www/html>|TEMP_DIR_MARKER|g" $APACHE_CONF_FILE
sudo sed -i "s|TEMP_DIR_MARKER|<Directory ${PROJECT_PATH}>\n        Options Indexes FollowSymLinks\n        AllowOverride All\n        Require all granted\n</Directory>|g" $APACHE_CONF_FILE

# 3. Habilita el módulo PHP 8.3 y reescribe URLs
sudo a2enmod rewrite
sudo a2enmod php8.3

# 4. Asigna la propiedad y permisos a la carpeta del proyecto
sudo chown -R www-data:www-data $PROJECT_PATH
sudo chmod -R 755 $PROJECT_PATH

echo "Configuración de Apache finalizada."