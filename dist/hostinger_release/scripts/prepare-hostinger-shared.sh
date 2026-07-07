#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="$ROOT_DIR/dist"
RELEASE_DIR="$DIST_DIR/hostinger_release"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
PACKAGE_FILE="$DIST_DIR/my_business_hostinger_${TIMESTAMP}.tar.gz"
ZIP_PACKAGE_FILE="$DIST_DIR/my_business_hostinger_${TIMESTAMP}.zip"

require_cmd() {
  local cmd="$1"
  if ! command -v "$cmd" >/dev/null 2>&1; then
    echo "Erro: comando '$cmd' nao encontrado." >&2
    exit 1
  fi
}

require_cmd rsync
require_cmd tar

echo "[1/3] Montando release minimo..."
cd "$ROOT_DIR"
rm -rf "$RELEASE_DIR"
mkdir -p "$RELEASE_DIR"
mkdir -p "$DIST_DIR"

rsync -a \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='.vscode' \
  --exclude='.continue' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='tests' \
  --exclude='docker' \
  --exclude='compose.yaml' \
  --exclude='compose.prod.yaml' \
  --exclude='Dockerfile.prod' \
  --exclude='storage/logs/*' \
  --exclude='storage/framework/cache/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' \
  --exclude='.env' \
  --exclude='.env.testing' \
  --exclude='.env.production' \
  --exclude='public/hot' \
  --exclude='dist' \
  "$ROOT_DIR/" "$RELEASE_DIR/"

# Safety net: never ship Vite dev-server marker file to production.
rm -f "$RELEASE_DIR/public/hot"

echo "[2/3] Gerando pacote..."
rm -f "$DIST_DIR"/my_business_hostinger_*.tar.gz
rm -f "$DIST_DIR"/my_business_hostinger_*.zip
( cd "$RELEASE_DIR" && tar -czf "$PACKAGE_FILE" . )

if command -v zip >/dev/null 2>&1; then
  ( cd "$RELEASE_DIR" && zip -r -q "$ZIP_PACKAGE_FILE" . )
  GENERATED_ZIP=1
else
  GENERATED_ZIP=0
fi

echo "[3/3] Finalizado."
echo
echo "Release pronta em:"
echo "$RELEASE_DIR"
echo
echo "Pacote compactado em:"
echo "$PACKAGE_FILE"
if [[ "$GENERATED_ZIP" == "1" ]]; then
  echo "$ZIP_PACKAGE_FILE"
else
  echo "Aviso: comando 'zip' nao encontrado, arquivo .zip nao foi gerado."
fi
echo
echo "Proximos passos no servidor:"
echo "1) Extrair o pacote"
echo "2) Criar .env"
echo "3) Rodar composer install --no-dev --optimize-autoloader"
echo "4) Rodar npm ci e npm run build"
echo "5) Rodar php artisan migrate --force"
echo "6) Rodar php artisan storage:link e caches"
