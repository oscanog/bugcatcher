#!/usr/bin/env bash
set -euo pipefail

OPENCLAW_CONFIG_PATH="${OPENCLAW_CONFIG_PATH:-/opt/openclaw/config/openclaw.json}"
EXAMPLE_CONFIG_PATH="${EXAMPLE_CONFIG_PATH:-/home/m/bugcatcher-openclaw-deploy/integrations/openclaw-upstream/openclaw.json.example}"
BUGCATCHER_ROOT="${BUGCATCHER_ROOT:-/var/www/bugcatcher}"
BUGCATCHER_CONFIG_PATH="${BUGCATCHER_CONFIG_PATH:-}"

resolve_config_path() {
  local root="$1"
  local explicit="$2"
  local candidate=""

  if [[ -n "$explicit" ]]; then
    if [[ -f "$explicit" ]]; then
      printf '%s\n' "$explicit"
      return 0
    fi
    echo "Config file not found: $explicit" >&2
    return 1
  fi

  for candidate in \
    "$root/shared/config.php" \
    "$root/infra/config/local.php" \
    "$root/config/local.php"
  do
    if [[ -f "$candidate" ]]; then
      printf '%s\n' "$candidate"
      return 0
    fi
  done

  echo "No config file found. Checked: $root/shared/config.php, $root/infra/config/local.php, $root/config/local.php" >&2
  return 1
}

BUGCATCHER_CONFIG_PATH="$(resolve_config_path "$BUGCATCHER_ROOT" "$BUGCATCHER_CONFIG_PATH")"

SHARED_SECRET="$(php -r "echo (require '$BUGCATCHER_CONFIG_PATH')['OPENCLAW_INTERNAL_SHARED_SECRET'];")"
GATEWAY_TOKEN="${OPENCLAW_GATEWAY_TOKEN:-$(openssl rand -hex 24)}"

env \
  OPENCLAW_CONFIG_PATH="$OPENCLAW_CONFIG_PATH" \
  EXAMPLE_CONFIG_PATH="$EXAMPLE_CONFIG_PATH" \
  SHARED_SECRET="$SHARED_SECRET" \
  GATEWAY_TOKEN="$GATEWAY_TOKEN" \
  python3 - <<'PY'
import json
import os
import pathlib

config_path = pathlib.Path(os.environ["OPENCLAW_CONFIG_PATH"])
example_path = pathlib.Path(os.environ["EXAMPLE_CONFIG_PATH"])
shared_secret = os.environ["SHARED_SECRET"]
gateway_token = os.environ["GATEWAY_TOKEN"]

data = json.loads(example_path.read_text())
data["plugins"]["entries"]["bugcatcher-openclaw"]["config"]["bugcatcherSharedSecret"] = shared_secret
data["gateway"]["auth"]["token"] = gateway_token

config_path.write_text(json.dumps(data, indent=2) + "\n")
PY

chown openclaw:openclaw "$OPENCLAW_CONFIG_PATH"
chmod 0640 "$OPENCLAW_CONFIG_PATH"

echo "Updated $OPENCLAW_CONFIG_PATH"
echo "Gateway token written to config."
