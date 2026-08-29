#!/usr/bin/env bash
set -e

echo "🚀 Iniciando despliegue..."

# 1. Poner la aplicación en modo mantenimiento temporal
php artisan down --refresh=15 --secret="deploy-bypass-token" || true

# 2. Descargar últimos cambios de Git
echo "📥 Obteniendo cambios del repositorio..."
git fetch origin main
git reset --hard origin/main

# 3. Instalar/actualizar dependencias de Composer (sin dev y optimizadas)
echo "📦 Instalando dependencias de PHP..."
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "🧭 Generando acciones de Laravel Wayfinder..."
php artisan wayfinder:generate || true

# 4. Compilar Frontend (Node / Vite / Vue)
echo "⚡ Compilando assets de Vue/Vite..."
npm install --no-save
npm run build

# 5. Ejecutar migraciones pendientes de base de datos
echo "🗄️ Ejecutando migraciones..."
php artisan migrate --force

# 6. Limpiar y reconstruir caché de Laravel
echo "🧹 Optimizando cachés de Laravel..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Si usas Queue Workers (opcional/descomentar si usas Supervisor)
# php artisan queue:restart

# 8. Corregir permisos de almacenamiento y caché
echo "🔒 Reafirmando permisos..."
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# 9. Reactivar la aplicación
php artisan up

echo "✅ Despliegue completado con éxito."