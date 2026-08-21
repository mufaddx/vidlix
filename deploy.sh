#!/usr/bin/env bash
#
# Vidlix deploy, for Hostinger shared hosting.
#
# Safe to run repeatedly. The first run sets things up; later runs pull, install
# and re-cache. Nothing is deleted without a backup being taken first.
#
#   bash deploy.sh            normal deploy
#   bash deploy.sh --first    first run: also links public_html and storage
#
# Every step announces itself and the script stops at the first failure, because
# a half-finished deploy that keeps going is worse than one that stops where it
# broke.

set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/vidlix}"
PUBLIC_HTML="${PUBLIC_HTML:-$HOME/domains/vidlix.in/public_html}"
BRANCH="${BRANCH:-vidlix-architecture}"

FIRST_RUN=false
[[ "${1:-}" == "--first" ]] && FIRST_RUN=true

say()  { printf '\n\033[1;36m▸ %s\033[0m\n' "$*"; }
ok()   { printf '  \033[32m✓\033[0m %s\n' "$*"; }
warn() { printf '  \033[33m○\033[0m %s\n' "$*"; }
die()  { printf '\n\033[1;31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

# ── Preconditions ────────────────────────────────────────────────────────────
# Checked up front rather than discovered halfway through, when the site is
# already half-swapped.

say "Checking the environment"

command -v php >/dev/null      || die "php is not on PATH."
command -v composer >/dev/null || die "composer is not on PATH."
command -v git >/dev/null      || die "git is not on PATH."

PHP_VERSION="$(php -r 'echo PHP_VERSION;')"
php -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);' \
  || die "PHP $PHP_VERSION is too old. Vidlix needs 8.4+. Change it in hPanel → Advanced → PHP Configuration, for the web side as well as the CLI."
ok "PHP $PHP_VERSION"

[[ -d "$APP_DIR" ]] || die "No application at $APP_DIR. Clone it first:
    git clone -b $BRANCH https://github.com/mufaddx/vidlix.git $APP_DIR"
ok "Application at $APP_DIR"

cd "$APP_DIR"

# The app must not live inside the web root: that would put .env, storage/ and
# vendor/ on the public internet.
case "$APP_DIR" in
  "$PUBLIC_HTML"*) die "The application is inside the web root. Move it to $HOME/vidlix — otherwise .env is readable by anyone who guesses the path." ;;
esac
ok "Application is outside the web root"

# ── Code ─────────────────────────────────────────────────────────────────────

say "Fetching the code"

if [[ -n "$(git status --porcelain)" ]]; then
  warn "There are local changes on the server. Leaving them alone and skipping the pull."
  warn "Run 'git status' in $APP_DIR to see what they are."
else
  git fetch origin "$BRANCH"
  git checkout "$BRANCH"
  git pull --ff-only origin "$BRANCH"
  ok "On $BRANCH at $(git rev-parse --short HEAD)"
fi

say "Installing dependencies"
composer install --no-dev --optimize-autoloader --no-interaction
ok "Dependencies installed"

# ── Environment ──────────────────────────────────────────────────────────────

say "Checking the environment file"

if [[ ! -f .env ]]; then
  [[ -f .env.production.example ]] || die "No .env and no .env.production.example to copy."
  cp .env.production.example .env
  php artisan key:generate --force
  die ".env has been created from the template and a key generated.

Fill it in — database, the four domains, providers — then run this script again:
    nano $APP_DIR/.env"
fi

grep -q '^APP_KEY=.\+' .env || { php artisan key:generate --force; ok "Generated APP_KEY"; }
ok ".env present"

# ── First run: the links ─────────────────────────────────────────────────────

if $FIRST_RUN; then
  say "Linking public_html"

  if [[ -L "$PUBLIC_HTML" ]]; then
    ok "public_html is already a symlink → $(readlink "$PUBLIC_HTML")"
  else
    if [[ -e "$PUBLIC_HTML" ]]; then
      BACKUP="$HOME/public_html_backup_$(date +%F_%H%M%S)"
      # Moved, never deleted. Whatever was there might be the only copy.
      mv "$PUBLIC_HTML" "$BACKUP"
      warn "Moved the old public_html to $BACKUP"
    fi

    mkdir -p "$(dirname "$PUBLIC_HTML")"
    ln -s "$APP_DIR/public" "$PUBLIC_HTML"
    ok "public_html → $APP_DIR/public"
  fi

  say "Linking storage"

  if [[ -e "$APP_DIR/public/storage" ]]; then
    ok "storage is already linked"
  else
    # php artisan storage:link fails on Hostinger: symlink() is in
    # disable_functions. The shell is not subject to that.
    ln -s "$APP_DIR/storage/app/public" "$APP_DIR/public/storage"
    ok "public/storage → storage/app/public"
  fi
fi

# ── Database ─────────────────────────────────────────────────────────────────

say "Running migrations"
php artisan migrate --force
ok "Migrations are up to date"

# ── Permissions and cache ────────────────────────────────────────────────────

say "Permissions"
chmod -R 775 storage bootstrap/cache
ok "storage and bootstrap/cache are writable"

say "Caching configuration"
php artisan config:cache
php artisan route:cache
php artisan view:cache
ok "Config, routes and views cached"

# ── Verdict ──────────────────────────────────────────────────────────────────

say "Preflight"

if php artisan vidlix:preflight; then
  printf '\n\033[1;32m✓ Deployed. Preflight found no blockers.\033[0m\n'
else
  printf '\n\033[1;33m▲ Deployed, but preflight found blockers — see above.\033[0m\n'
  printf '  The site is running. Fix each one before pointing real people at it.\n'
  exit 1
fi

cat <<'NEXT'

Check each face answers:

  curl -sI https://vidlix.in/up        | head -1
  curl -sI https://app.vidlix.in/up    | head -1
  curl -sI https://autodm.vidlix.in/up | head -1
  curl -sI https://admin.vidlix.in/up  | head -1

Four 200s means all four hosts share one document root.

NEXT
