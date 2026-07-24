#!/usr/bin/env bash
#
# Compiles Tailwind to a single static stylesheet.
#
#   ./resources/build-css.sh          production build (minified)
#   ./resources/build-css.sh --watch  rebuild on change during development
#
# Uses the standalone Tailwind binary, so no Node runtime and no node_modules are
# required — here or on the server. The binary itself is gitignored; fetch it with:
#
#   curl -sSL -o resources/bin/tailwindcss \
#     https://github.com/tailwindlabs/tailwindcss/releases/download/v3.4.17/tailwindcss-macos-arm64
#   chmod +x resources/bin/tailwindcss
#
# (swap macos-arm64 for linux-x64 / macos-x64 as needed)
#
# The compiled public/assets/css/app.css IS committed, because deployment to shared
# hosting is a file copy with no build step at the far end.
set -euo pipefail

cd "$(dirname "$0")/.."

BIN="resources/bin/tailwindcss"

if [ ! -x "$BIN" ]; then
  echo "Tailwind binary not found at $BIN — see the comment at the top of this script." >&2
  exit 1
fi

if [ "${1:-}" = "--watch" ]; then
  exec "$BIN" -c resources/tailwind.config.js -i resources/css/app.css -o public/assets/css/app.css --watch
fi

"$BIN" -c resources/tailwind.config.js -i resources/css/app.css -o public/assets/css/app.css --minify
echo "Built public/assets/css/app.css"
