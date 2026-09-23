#!/usr/bin/env sh
# Resets the development stack's database to the visual reference dataset (docs/testing/visual-regression.md).
# Destroys everything else in that database.
set -e
cd "$(dirname "$0")/../.."
mkdir -p legal
for f in legal-notice legal-policy privacy; do cp "docs/deployment/legal-examples/$f.md" "legal/$f.md"; done
docker compose -f compose.dev.yaml exec -T php-fpm sh -c '
  php artisan migrate:fresh --force --seed --seeder="Database\Seeders\VisualReferenceSeeder" >/dev/null &&
  php artisan search:reindex >/dev/null && php artisan cache:clear >/dev/null'
# The queue worker indexes asynchronously.
sleep 5
