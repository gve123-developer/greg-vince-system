#!/bin/sh
set -e

# Support dynamic port binding if Coolify or provider sets $PORT, default to 8000
PORT="${PORT:-8000}"

# Adjust Apache configuration to listen on the configured port
sed -i "s/Listen [0-9]*/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf

echo "Starting Zoe POS Inventory System on port $PORT..."
exec "$@"
