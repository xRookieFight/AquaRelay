#!/bin/sh
set -e

APP_DIR="/app"
DATA_DIR="/app/data"

if [ ! -d "$DATA_DIR" ]; then
  mkdir -p "$DATA_DIR"
fi

chmod -R 755 "$DATA_DIR"

echo "Starting AquaRelay..."
exec php "$APP_DIR/AquaRelay.phar"