#!/usr/bin/env bash
# run.sh — build and serve site in Docker. Usage: ./run.sh [start|stop]
set -euo pipefail
cd "$(dirname "$0")"

CMD="${1:-start}"
PORT="${PORT:-8080}"

if [ ! -f config.php ]; then
    echo "config.php missing — copying from config.example.php"
    cp config.example.php config.php
    echo "edit config.php secrets before using contact/comments/admin features"
fi

case "$CMD" in
    start)
        echo "starting at http://127.0.0.1:${PORT}"
        docker compose up --build -d
        docker compose logs -f site
        ;;
    stop)
        docker compose down
        ;;
    *)
        echo "usage: $0 [start|stop]" >&2
        exit 1
        ;;
esac
