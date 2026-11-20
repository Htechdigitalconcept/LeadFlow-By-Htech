#!/usr/bin/env bash
set -euo pipefail
: "${HTECL_WP_PATH:?Provide a new temporary directory}"
: "${HTECL_DB_NAME:?Provide a disposable database}"
: "${HTECL_DB_USER:?Provide a database test user}"
: "${HTECL_DB_PASSWORD:?Provide a test password}"
root=$(cd "$(dirname "$0")/.." && pwd)
mkdir -p "$HTECL_WP_PATH"
[[ ! -e "$HTECL_WP_PATH/wp-config.php" ]]
args=(--path="$HTECL_WP_PATH")
wp "${args[@]}" core download --version="${WP_VERSION:-latest}"
wp "${args[@]}" config create --dbname="$HTECL_DB_NAME" --dbuser="$HTECL_DB_USER" --dbpass="$HTECL_DB_PASSWORD" --dbhost="${HTECL_DB_HOST:-127.0.0.1:3307}" --extra-php <<'PHP'
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', true);
PHP
url=${HTECL_QA_URL:-http://127.0.0.1:8091}
wp "${args[@]}" core install --url="$url" --title='LeadFlow QA' --admin_user=qa_admin --admin_password="${HTECL_QA_PASSWORD:-$(openssl rand -hex 24)}" --admin_email=qa@example.test --skip-email
wp "${args[@]}" option update siteurl "$url"
wp "${args[@]}" option update home "$url"
if [[ ${HTECL_UPLOAD_QA:-0} != 1 ]]; then
  ln -s "$root" "$HTECL_WP_PATH/wp-content/plugins/htech-leadflow"
  wp "${args[@]}" plugin activate htech-leadflow
fi
if [[ ${WITH_WOO:-false} == true ]]; then wp "${args[@]}" plugin install woocommerce --activate; fi
