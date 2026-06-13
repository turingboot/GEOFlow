#!/usr/bin/env bash
set -Eeuo pipefail

# GEOFlow local (dev/demo) Docker deployment helper.
# It checks the Docker environment, prepares .env from .env.example,
# builds and starts the docker-compose.yml stack, waits for the one-shot
# init container (APP_KEY + migrate + seed) to finish, and verifies that
# the app answers over HTTP.
#
# Usage:
#   bash deploy-scripts/geoflow-local-deploy.sh            # full flow
#   GEOFLOW_SKIP_BUILD=1 bash deploy-scripts/...           # skip image build
#
# Environment overrides:
#   GEOFLOW_SKIP_BUILD=1   reuse existing geoflow-app image, skip `compose build`
#   GEOFLOW_INIT_TIMEOUT   seconds to wait for the init container (default 600)
#   GEOFLOW_HTTP_TIMEOUT   seconds to wait for the app HTTP endpoint (default 120)

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SKIP_BUILD="${GEOFLOW_SKIP_BUILD:-0}"
INIT_TIMEOUT="${GEOFLOW_INIT_TIMEOUT:-600}"
HTTP_TIMEOUT="${GEOFLOW_HTTP_TIMEOUT:-120}"

log() {
  printf '\033[1;34m[geoflow]\033[0m %s\n' "$*"
}

warn() {
  printf '\033[1;33m[warn]\033[0m %s\n' "$*" >&2
}

fail() {
  printf '\033[1;31m[error]\033[0m %s\n' "$*" >&2
  exit 1
}

on_error() {
  local line="$1"
  fail "Local deployment failed near line ${line}. Check the logs above, then rerun this script."
}
trap 'on_error $LINENO' ERR

command_exists() {
  command -v "$1" >/dev/null 2>&1
}

# Read a variable from .env (last assignment wins), stripping optional quotes.
env_value() {
  local key="$1" default="$2" value
  value="$(grep -E "^${key}=" "$REPO_DIR/.env" | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' || true)"
  printf '%s' "${value:-$default}"
}

# --- 1. Preflight -----------------------------------------------------------

log "Step 1/6: checking Docker environment"

command_exists docker || fail "docker is not installed. Install Docker Desktop first: https://docs.docker.com/desktop/"
docker info >/dev/null 2>&1 || fail "Docker daemon is not running. Start Docker Desktop, then rerun."
docker compose version >/dev/null 2>&1 || fail "docker compose v2 is unavailable. Update Docker Desktop / install the compose plugin."

if ! docker info 2>/dev/null | grep -q 'Registry Mirrors'; then
  warn "No Docker Hub registry mirror is configured. If image pulls stall, configure one"
  warn "(e.g. https://dockerproxy.net) in daemon.json: docker desktop stop -> edit ~/.docker/daemon.json -> docker desktop start."
fi

cd "$REPO_DIR"
[ -f docker-compose.yml ] || fail "docker-compose.yml not found in ${REPO_DIR}; run this script from the GEOFlow repo."

# --- 2. Prepare .env --------------------------------------------------------

log "Step 2/6: preparing .env"

if [ -f .env ]; then
  log ".env already exists, keeping it as-is."
else
  cp .env.example .env
  log "Created .env from .env.example (defaults work out of the box for docker compose)."
fi

APP_PORT="$(env_value APP_PORT 18080)"
ADMIN_BASE_PATH="$(env_value ADMIN_BASE_PATH geo_admin)"
REVERB_EXPOSE_PORT="$(env_value REVERB_PORT 18081)"

# --- 3. Build ---------------------------------------------------------------

if [ "$SKIP_BUILD" = "1" ]; then
  log "Step 3/6: skipping image build (GEOFLOW_SKIP_BUILD=1)"
else
  log "Step 3/6: building images (first run downloads base images; this can take a while)"
  docker compose build
fi

# --- 4. Start the stack -----------------------------------------------------

log "Step 4/6: starting services (postgres, redis, init, app, queue, scheduler, reverb)"
if [ "$SKIP_BUILD" = "1" ]; then
  docker compose up -d --no-build
else
  docker compose up -d
fi

# --- 5. Wait for init -------------------------------------------------------

log "Step 5/6: waiting for the init container (APP_KEY / migrate / seed), timeout ${INIT_TIMEOUT}s"

deadline=$((SECONDS + INIT_TIMEOUT))
while :; do
  state="$(docker inspect -f '{{.State.Status}}' geoflow-init 2>/dev/null || echo missing)"
  case "$state" in
    exited)
      exit_code="$(docker inspect -f '{{.State.ExitCode}}' geoflow-init)"
      if [ "$exit_code" = "0" ]; then
        log "Init finished successfully."
        break
      fi
      docker logs --tail 50 geoflow-init >&2 || true
      fail "Init container exited with code ${exit_code}. Full logs: docker compose logs init"
      ;;
    missing)
      fail "Init container not found. Check: docker compose ps"
      ;;
  esac
  [ "$SECONDS" -lt "$deadline" ] || fail "Init container did not finish within ${INIT_TIMEOUT}s. Logs: docker compose logs -f init"
  sleep 3
done

# --- 6. Verify HTTP ---------------------------------------------------------

log "Step 6/6: waiting for the app to answer on http://localhost:${APP_PORT}, timeout ${HTTP_TIMEOUT}s"

deadline=$((SECONDS + HTTP_TIMEOUT))
until curl -fsS -o /dev/null "http://localhost:${APP_PORT}"; do
  [ "$SECONDS" -lt "$deadline" ] || fail "App did not respond within ${HTTP_TIMEOUT}s. Logs: docker compose logs -f app"
  sleep 3
done

log "App is up."
docker compose ps

cat <<SUMMARY

============================================================
 GEOFlow local deployment complete
------------------------------------------------------------
 Frontend : http://localhost:${APP_PORT}
 Admin    : http://localhost:${APP_PORT}/${ADMIN_BASE_PATH}/login
 Reverb WS: localhost:${REVERB_EXPOSE_PORT}
 Admin user/password: see GEOFLOW_ADMIN_* in .env
   (dev defaults: admin / password; only seeded on first init)

 Useful commands:
   docker compose logs -f app        # app logs
   docker compose ps                 # service status
   docker compose down               # stop (data kept in ./docker-data/dev)
   docker compose up -d              # start again
============================================================
SUMMARY
