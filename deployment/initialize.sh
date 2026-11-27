#!/usr/bin/env bash
set -euo pipefail

deployment_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
compose=(docker compose --project-directory "$deployment_dir" -f "$deployment_dir/compose.yaml")

case "${1:-}" in
  "") ;;
  --edge) compose+=(-f "$deployment_dir/edge.compose.yaml") ;;
  *) echo 'Usage: bash initialize.sh [--edge]' >&2; exit 2 ;;
esac

for ((attempt = 1; attempt <= 30; attempt++)); do
  if "${compose[@]}" exec -T showdoc wget -q -T 2 -O /dev/null http://127.0.0.1/web/; then
    exec "${compose[@]}" exec -T -u 0 -w /var/www/html/server showdoc sh -ec '
      exec su-exec 1000:1000 php -d display_errors=0 -d log_errors=0 -d zend.exception_ignore_args=1 \
        /usr/local/lib/showdoc-initialize.php < /run/secrets/showdoc_admin_password
    '
  fi
  sleep 2
done

echo 'ShowDoc did not become ready for local initialization.' >&2
exit 1
