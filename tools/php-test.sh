#!/usr/bin/env bash
set -euo pipefail
if command -v php >/dev/null && php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);' 2>/dev/null; then
  exec php "$@"
fi
runtime="$(cd "$(dirname "$0")/../work/php-runtime" && pwd)"
export LD_LIBRARY_PATH="$runtime/usr/lib/x86_64-linux-gnu${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
exec "$runtime/usr/bin/php8.4" -n -d "extension=$runtime/usr/lib/php/20240924/pdo.so" -d "extension=$runtime/usr/lib/php/20240924/pdo_sqlite.so" "$@"
