#!/bin/bash
#
# Stops the local SUBRAMANYAM web server and database.
#
#   bash ~/Desktop/subramanyam-agency/scripts/stop.sh

DB="$HOME/.agency-db/mysql"
SOCK="/tmp/ags.sock"

say() { printf "\033[38;5;179m%s\033[0m\n" "$1"; }

# Web server (the PHP built-in server on port 8132)
if pkill -f "php -S 127.0.0.1:8132" 2>/dev/null; then
  say "Web server stopped."
else
  echo "Web server was not running."
fi

# Database (clean shutdown so the data stays consistent)
if "$DB/bin/mysqladmin" --socket="$SOCK" -u root ping >/dev/null 2>&1; then
  "$DB/bin/mysqladmin" --socket="$SOCK" -u root shutdown 2>/dev/null
  say "Database stopped."
else
  echo "Database was not running."
fi
