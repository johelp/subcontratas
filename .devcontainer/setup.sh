#!/bin/bash

# Este script se ejecuta la primera vez que se crea el Codespace.

DB_NAME="snow_subcontratas"
DB_ROOT_PASS="root"
SQL_FILE="/workspaces/dump.sql" # Ubicación del archivo SQL en el Codespace

echo "Iniciando la configuración del entorno para $DB_NAME..."

# 1. Esperar a que el servicio 'db' (MySQL) esté completamente listo.
echo "Esperando 15 segundos a que el servicio de base de datos (db) se inicie..."
sleep 15 # Esperar más tiempo asegura que el servicio esté listo.

# 2. Verificar que el archivo SQL exista
if [ ! -f "$SQL_FILE" ]; then
    echo "❌ ERROR: No se encontró el archivo $SQL_FILE en la raíz del proyecto."
    exit 1
fi

# 3. Importar el archivo SQL. Usamos docker exec para correr el comando dentro del contenedor de la DB.
echo "Importando la base de datos '$DB_NAME' desde $SQL_FILE..."

# El comando se ejecuta: mysql -u root -p[password] [database_name] < [sql_file]
docker exec mysql-db sh -c "mysql -u root -p$DB_ROOT_PASS $DB_NAME < $SQL_FILE"

if [ $? -eq 0 ]; then
    echo "✅ Base de datos '$DB_NAME' importada exitosamente."
else
    echo "❌ Error al importar la base de datos. Verifica la conexión o el contenido de $SQL_FILE."
fi

echo "Configuración inicial finalizada. Tu aplicación PHP ya está lista para usarse."