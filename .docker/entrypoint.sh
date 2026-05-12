#!/bin/bash
set -e

cd /var/www/html

# Cria diretórios graváveis caso ainda não existam
mkdir -p storage/exports logs

# Instala dependências do Composer se ainda não houver vendor/
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "[entrypoint] Instalando dependências do Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Garante permissão para o Apache escrever em storage/ e logs/
chown -R www-data:www-data storage logs 2>/dev/null || true
chmod -R 0775 storage logs 2>/dev/null || true

exec "$@"
