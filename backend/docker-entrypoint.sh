#!/bin/bash
echo "🚀 [entrypoint] Starting backend (production)..."

mkdir -p storage/logs \
         storage/framework/cache \
         storage/framework/sessions \
         storage/framework/views \
         storage/app/public/images/products \
         storage/app/public/images/banners \
         storage/app/public/images/blogs \
         storage/api-docs \
         bootstrap/cache

chmod -R 775 storage bootstrap/cache 2>/dev/null || true

php artisan key:generate --force --no-interaction 2>/dev/null || true
php artisan storage:link 2>/dev/null || true

echo "🗄️  [entrypoint] Running migrations..."
php artisan migrate --force --no-interaction

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ [entrypoint] Setup complete! Starting server on port ${PORT:-8000}..."
exec "$@"
