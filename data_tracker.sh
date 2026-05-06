#!/usr/bin/env bash
# dt — data-tracker service manager
# Usage: ./dt <command>

set -euo pipefail

COMPOSE="docker compose"

usage() {
  cat <<EOF
Usage: ./dt <command>

Commands:
  init        First-time setup: build images, generate app key, run migrations & seed
  start       Start all services (detached)
  restart     Restart all services
  stop        Stop all services (keep volumes)
  update      Pull latest code, rebuild images, run new migrations, restart
  deploy      Same as update (alias for production use)

  logs [svc]  Tail logs (all services, or a specific one: app, nginx, worker, scheduler, mysql, node)
  shell       Open a bash shell in the app container
  test [args] Run the test suite (pass extra args, e.g. --filter=SomeTest)
  artisan     Run an artisan command (e.g. ./dt artisan migrate:fresh --seed)
EOF
}

cmd_init() {
  echo "==> Building images..."
  $COMPOSE build

  echo "==> Starting services..."
  $COMPOSE up -d

  echo "==> Waiting for MySQL to be ready..."
  for i in $(seq 1 30); do
    if $COMPOSE exec -T mysql mysqladmin ping -u laravel -psecret --silent 2>/dev/null; then
      break
    fi
    printf '.'
    sleep 2
  done
  echo

  if [ ! -f .env ]; then
    echo "==> Creating .env from .env.example..."
    cp .env.example .env
  fi

  echo "==> Generating application key..."
  $COMPOSE exec app php artisan key:generate --ansi

  echo "==> Running migrations and seeders..."
  $COMPOSE exec app php artisan migrate --seed --ansi

  echo
  echo "Done. App is running at http://localhost:8000"
}

cmd_start() {
  echo "==> Starting services..."
  $COMPOSE up -d
  echo "App: http://localhost:8000"
}

cmd_restart() {
  echo "==> Restarting services..."
  $COMPOSE restart
  echo "Done."
}

cmd_stop() {
  echo "==> Stopping services..."
  $COMPOSE down
  echo "Done."
}

cmd_update() {
  echo "==> Pulling latest code..."
  git pull

  echo "==> Rebuilding PHP image..."
  $COMPOSE build app worker scheduler

  echo "==> Restarting services..."
  $COMPOSE up -d

  echo "==> Installing PHP dependencies..."
  $COMPOSE exec app composer install --no-interaction --prefer-dist --optimize-autoloader

  echo "==> Running new migrations and seeders..."
  $COMPOSE exec app php artisan migrate --force --ansi
  $COMPOSE exec app php artisan db:seed --force --ansi

  echo "==> Clearing caches..."
  $COMPOSE exec app php artisan optimize:clear --ansi

  echo "==> Building frontend assets..."
  $COMPOSE run --rm node npm run build

  echo "==> Syncing systemd service state..."
  sudo systemctl restart data-tracker 2>/dev/null || true

  echo "==> Stopping node dev server..."
  $COMPOSE stop node
  $COMPOSE exec app rm -f public/hot

  echo "Done."
}

cmd_logs() {
  local svc="${1:-}"
  if [ -n "$svc" ]; then
    $COMPOSE logs -f "$svc"
  else
    $COMPOSE logs -f
  fi
}

cmd_shell() {
  $COMPOSE exec app bash
}

cmd_test() {
  $COMPOSE exec app php artisan test "$@"
}

cmd_artisan() {
  $COMPOSE exec app php artisan "$@"
}

# ── dispatch ──────────────────────────────────────────────────────────────────

if [ $# -eq 0 ]; then
  usage
  exit 0
fi

COMMAND="$1"
shift

case "$COMMAND" in
  init)     cmd_init ;;
  start)    cmd_start ;;
  restart)  cmd_restart ;;
  stop)     cmd_stop ;;
  update|deploy) cmd_update ;;
  logs)     cmd_logs "${1:-}" ;;
  shell)    cmd_shell ;;
  test)     cmd_test "$@" ;;
  artisan)  cmd_artisan "$@" ;;
  -h|--help|help) usage ;;
  *)
    echo "Unknown command: $COMMAND"
    echo
    usage
    exit 1
    ;;
esac
