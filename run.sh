#!/usr/bin/env bash
#
# run.sh — one-shot setup & launch for the Intelligent ERP stack.
#
# Idempotent: safe to run on a clean checkout or to re-run later. It creates a
# .env with generated secrets (only if missing), builds and starts every
# service via docker compose, installs backend Composer deps when absent, runs
# migrations, seeds demo data, and health-checks each endpoint.
#
# Usage:
#   ./run.sh              # set up (if needed) and start everything
#   ./run.sh --seed       # also (re)run the demo seeder
#   ./run.sh --rebuild    # force a no-cache rebuild of all images
#   ./run.sh --down       # stop and remove the stack (keeps volumes)
#
set -euo pipefail
cd "$(dirname "$0")"

SEED=0 REBUILD=0
for arg in "$@"; do
  case "$arg" in
    --seed)    SEED=1 ;;
    --rebuild) REBUILD=1 ;;
    --down)    docker compose down; echo "Stack stopped."; exit 0 ;;
    *) echo "Unknown option: $arg" >&2; exit 2 ;;
  esac
done

log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }

# --- 1. Docker must be running -----------------------------------------------
log "Checking Docker daemon"
if ! docker info >/dev/null 2>&1; then
  echo "Docker daemon is not reachable. Start Docker Desktop and re-run." >&2
  exit 1
fi

# --- 2. .env with generated secrets ------------------------------------------
if [ ! -f .env ]; then
  log "Creating .env with generated secrets"
  APP_KEY="base64:$(openssl rand -base64 32)"
  JWT_SECRET="$(openssl rand -hex 32)"
  DB_PW="erp_$(openssl rand -hex 8)"
  sed -e "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" \
      -e "s|^JWT_SECRET=.*|JWT_SECRET=${JWT_SECRET}|" \
      -e "s|^POSTGRES_PASSWORD=.*|POSTGRES_PASSWORD=${DB_PW}|" \
      -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PW}|" \
      .env.example > .env
  echo "  .env created."
else
  echo "  .env already exists — leaving it as is."
fi

# --- 3. Build images ---------------------------------------------------------
if [ "$REBUILD" -eq 1 ]; then
  log "Rebuilding all images (no cache)"
  docker compose build --no-cache
else
  log "Building images"
  docker compose build
fi

# --- 4. Backend Composer dependencies ----------------------------------------
# ./backend is bind-mounted over the image, so vendor/ must exist on the host.
if [ ! -f backend/vendor/autoload.php ]; then
  log "Installing backend Composer dependencies"
  docker compose run --rm --no-deps -T backend \
    composer install --no-interaction --prefer-dist
else
  echo "  backend/vendor present — skipping composer install."
fi

# --- 5. Start the stack ------------------------------------------------------
log "Starting services"
docker compose up -d

# --- 6. Wait for Postgres, run migrations ------------------------------------
log "Waiting for database to be healthy"
until [ "$(docker compose ps db --format '{{.Health}}' 2>/dev/null)" = "healthy" ]; do
  sleep 2
done
log "Running migrations"
docker compose exec -T backend php artisan migrate --force

# --- 7. Seed demo data (first run, or --seed) --------------------------------
if [ "$SEED" -eq 1 ] || [ ! -f .seeded ]; then
  log "Seeding demo data"
  docker compose exec -T backend php artisan db:seed --class=DemoSeeder --force
  touch .seeded
else
  echo "  Demo data already seeded (pass --seed to re-run)."
fi

# --- 8. Health checks --------------------------------------------------------
log "Health checks"
check() { # name url
  code="$(curl -s -o /dev/null -w '%{http_code}' "$2" || echo 000)"
  printf '  %-20s %s -> HTTP %s\n' "$1" "$2" "$code"
}
check "frontend"  http://localhost:5173
check "backend"   http://localhost:8000
check "ai-service" http://localhost:8001/ready

cat <<'EOF'

All set. Open the app at:  http://localhost:5173

Demo logins:
  admin@erp.local    / Admin123!
  manager@erp.local  / Manager123!
  employee@erp.local / Employee123!

Useful:
  docker compose ps
  docker compose logs -f backend
  ./run.sh --down       # stop the stack
EOF
