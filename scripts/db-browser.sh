#!/usr/bin/env bash
#
# Open the OyaFind database in a browser (Adminer) through an SSH tunnel.
#
#   ./scripts/db-browser.sh          # start the tunnel and open the browser
#   ./scripts/db-browser.sh close    # stop the tunnel
#
# Adminer listens on the VM loopback only, so it cannot be opened directly at
# http://145.241.204.16:8081. The tunnel makes it appear as 127.0.0.1:8081 on
# this computer, which is why the address only works while this script's ssh
# process is running.
#
# Override the defaults with environment variables:
#   OYAFIND_HOST=ubuntu@145.241.204.16
#   OYAFIND_SSH_KEY=~/.ssh/id_ed25519
#   OYAFIND_ADMINER_PORT=8081
#
set -euo pipefail

HOST="${OYAFIND_HOST:-ubuntu@145.241.204.16}"
KEY="${OYAFIND_SSH_KEY:-$HOME/.ssh/id_ed25519}"
PORT="${OYAFIND_ADMINER_PORT:-8081}"
URL="http://127.0.0.1:${PORT}/"

tunnel_pid() {
    # The listener belongs to the ssh process forwarding the port.
    lsof -nP -tiTCP:"${PORT}" -sTCP:LISTEN 2>/dev/null | head -1
}

case "${1:-open}" in
    close|stop)
        pid="$(tunnel_pid)"
        if [ -z "${pid}" ]; then
            echo "No tunnel is running on port ${PORT}."
            exit 0
        fi
        kill "${pid}"
        echo "Closed the tunnel on port ${PORT} (pid ${pid})."
        exit 0
        ;;
    open|start) ;;
    *)
        echo "Usage: $0 [open|close]" >&2
        exit 2
        ;;
esac

if [ -n "$(tunnel_pid)" ]; then
    echo "A tunnel is already listening on port ${PORT}."
else
    if [ ! -f "${KEY}" ]; then
        echo "SSH key not found: ${KEY}" >&2
        echo "Set OYAFIND_SSH_KEY to the key you use for ${HOST}." >&2
        exit 1
    fi

    echo "Opening the tunnel ${HOST} -> 127.0.0.1:${PORT} ..."
    ssh -f -N \
        -i "${KEY}" \
        -o IdentitiesOnly=yes \
        -o ExitOnForwardFailure=yes \
        -o ServerAliveInterval=30 \
        -o ServerAliveCountMax=3 \
        -L "${PORT}:127.0.0.1:${PORT}" \
        "${HOST}"

    for _ in 1 2 3 4 5 6 7 8 9 10; do
        [ -n "$(tunnel_pid)" ] && break
        sleep 0.3
    done

    if [ -z "$(tunnel_pid)" ]; then
        echo "The tunnel did not come up. Check that ${HOST} is reachable:" >&2
        echo "  ssh -i ${KEY} ${HOST} true" >&2
        exit 1
    fi

    echo "Tunnel is up."
fi

if command -v open >/dev/null 2>&1; then
    open "${URL}"
elif command -v xdg-open >/dev/null 2>&1; then
    xdg-open "${URL}"
fi

cat <<EOF

Adminer: ${URL}

  Server   mysql            (pre-filled)
  Username oyafind
  Password (DB_PASSWORD in deploy/.env)
  Database oyafind

The tunnel stays open until you run:  $0 close
EOF
