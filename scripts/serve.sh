#!/bin/bash
#
# Local launcher for the SUBRAMANYAM website + CMS.
#
# Starts the local database and the PHP web server if they are not already
# running, then opens the website and the CMS in Safari. Safe to run repeatedly —
# it never starts a second copy of anything.
#
# Self-healing: if the local database folder is ever deleted, this re-downloads
# and rebuilds it automatically (a one-time ~250 MB download), so it keeps working
# across restarts.
#
#   bash ~/Desktop/subramanyam-agency/scripts/serve.sh
#
# Or double-click "Open SUBRAMANYAM.command" on the Desktop.

set -e

PROJECT="$HOME/Desktop/subramanyam-agency"
DB="$HOME/.agency-db/mysql"
DATA="$DB/data"
SOCK="/tmp/ags.sock"
PID="/tmp/ags.pid"
DB_PORT=3399
WEB_PORT=8132
PHP="$HOME/Library/Application Support/Herd/bin/php"
MYSQL_URL="https://cdn.mysql.com/Downloads/MySQL-8.4/mysql-8.4.9-macos15-arm64.tar.gz"

WEBSITE="http://localhost:${WEB_PORT}/"
CMS="http://localhost:${WEB_PORT}/admin/login"

say() { printf "\033[38;5;179m%s\033[0m\n" "$1"; }   # champagne-gold text

# --- Database -------------------------------------------------------------
if ! "$DB/bin/mysqladmin" --socket="$SOCK" -u root ping >/dev/null 2>&1; then

  # Re-provision if the install has been wiped.
  if [ ! -x "$DB/bin/mysqld" ]; then
    say "Local database not found — downloading MySQL once (~250 MB)…"
    mkdir -p "$HOME/.agency-db"
    curl -L --fail -o "$HOME/.agency-db/mysql.tar.gz" "$MYSQL_URL"
    tar xzf "$HOME/.agency-db/mysql.tar.gz" -C "$HOME/.agency-db"
    mv "$HOME/.agency-db/mysql-8.4.9-macos15-arm64" "$DB"
    rm -f "$HOME/.agency-db/mysql.tar.gz"
  fi

  FRESH=0
  if [ ! -d "$DATA" ]; then
    say "Initialising the database…"
    "$DB/bin/mysqld" --initialize-insecure --basedir="$DB" --datadir="$DATA" \
      --log-error="$HOME/.agency-db/init.log"
    FRESH=1
  fi

  say "Starting the database…"
  "$DB/bin/mysqld" --basedir="$DB" --datadir="$DATA" --port="$DB_PORT" --socket="$SOCK" \
    --mysqlx=OFF --log-error="$HOME/.agency-db/mysql.log" --pid-file="$PID" --daemonize

  for i in $(seq 1 30); do
    "$DB/bin/mysqladmin" --socket="$SOCK" -u root ping >/dev/null 2>&1 && break
    sleep 1
  done

  # Make sure the app's database and user exist (idempotent).
  "$DB/bin/mysql" --socket="$SOCK" -u root -e "
    CREATE DATABASE IF NOT EXISTS agency CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS 'agency'@'127.0.0.1' IDENTIFIED BY 'agency_dev';
    GRANT ALL ON agency.* TO 'agency'@'127.0.0.1'; FLUSH PRIVILEGES;"

  if [ "$FRESH" = "1" ]; then
    say "Building schema and seed content…"
    "$PHP" "$PROJECT/database/migrate.php" --seed
    "$PHP" "$PROJECT/scripts/create-admin.php" --name="Subramanyam" \
      --email="you@example.com" --role=admin --password="ChangeMeAfterFirstLogin!2026"
  fi
else
  say "Database already running."
fi

# --- Web server -----------------------------------------------------------
if curl -s -o /dev/null --max-time 3 "$WEBSITE"; then
  say "Web server already running."
else
  say "Starting the web server on port ${WEB_PORT}…"
  nohup "$PHP" -S "127.0.0.1:${WEB_PORT}" -t "$PROJECT/public" "$PROJECT/public/index.php" \
    >/tmp/agency-web.log 2>&1 &
  for i in $(seq 1 15); do
    curl -s -o /dev/null --max-time 2 "$WEBSITE" && break
    sleep 1
  done
fi

# --- Open -----------------------------------------------------------------
open -a Safari "$WEBSITE"
sleep 1
open -a Safari "$CMS"

echo
say "SUBRAMANYAM is live locally:"
echo "  Website : $WEBSITE"
echo "  CMS     : $CMS"
echo "  Sign in : you@example.com"
echo
echo "To stop everything later:  bash $PROJECT/scripts/stop.sh"
