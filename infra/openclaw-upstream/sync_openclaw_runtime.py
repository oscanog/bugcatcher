#!/usr/bin/env python3
import argparse
import json
import subprocess
import sys
import urllib.error
import urllib.request
from pathlib import Path


PLUGIN_ID = "bugcatcher-openclaw"


def load_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding="utf-8"))


def fetch_runtime_snapshot(base_url: str, shared_secret: str, timeout_ms: int) -> dict:
    request = urllib.request.Request(
        f"{base_url.rstrip('/')}/api/openclaw/runtime_config.php",
        headers={
            "Authorization": f"Bearer {shared_secret}",
            "Accept": "application/json",
        },
        method="GET",
    )
    with urllib.request.urlopen(request, timeout=max(timeout_ms / 1000, 1)) as response:
        payload = response.read().decode("utf-8")
    return json.loads(payload)


def update_discord_runtime(config: dict, snapshot: dict) -> bool:
    runtime = snapshot.get("runtime") or {}
    desired_enabled = bool(runtime.get("is_enabled"))
    desired_token = str(runtime.get("discord_bot_token") or "").strip()
    desired_channels = snapshot.get("channels") or []

    channels = config.setdefault("channels", {})
    discord = channels.setdefault("discord", {})
    current_enabled = bool(discord.get("enabled"))
    current_token = str(discord.get("token") or "")

    next_enabled = desired_enabled and desired_token != ""
    changed = False

    if current_enabled != next_enabled:
        discord["enabled"] = next_enabled
        changed = True

    if current_token != desired_token:
        discord["token"] = desired_token
        changed = True

    desired_guilds: dict[str, dict] = {}
    for channel in desired_channels:
        guild_id = str(channel.get("guild_id") or "").strip()
        channel_id = str(channel.get("channel_id") or "").strip()
        if not guild_id or not channel_id:
            continue
        guild_entry = desired_guilds.setdefault(
            guild_id,
            {
                "slug": str(channel.get("guild_name") or guild_id).strip().lower().replace(" ", "-"),
                "requireMention": False,
                "channels": {},
            },
        )
        guild_entry["channels"][channel_id] = {
            "allow": True,
            "requireMention": False,
        }

    if discord.get("groupPolicy") != "allowlist":
        discord["groupPolicy"] = "allowlist"
        changed = True

    current_guilds = discord.get("guilds") if isinstance(discord.get("guilds"), dict) else {}
    if current_guilds != desired_guilds:
        discord["guilds"] = desired_guilds
        changed = True

    return changed


def build_provider_models(snapshot: dict) -> tuple[dict, str | None, str | None]:
    providers = snapshot.get("providers") or []
    models = snapshot.get("models") or []
    runtime = snapshot.get("runtime") or {}

    providers_by_id = {int(provider["id"]): provider for provider in providers if "id" in provider}
    models_by_provider: dict[int, list[dict]] = {}
    for model in models:
        provider_id = int(model.get("provider_config_id") or 0)
        if provider_id <= 0:
            continue
        models_by_provider.setdefault(provider_id, []).append(model)

    provider_configs: dict[str, dict] = {}
    for provider_id, provider in providers_by_id.items():
        provider_key = str(provider.get("provider_key") or "").strip()
        if not provider_key:
            continue
        provider_models = []
        for model in models_by_provider.get(provider_id, []):
            provider_models.append(
                {
                    "id": str(model.get("model_id") or "").strip(),
                    "name": str(model.get("display_name") or model.get("model_id") or "").strip(),
                    "reasoning": False,
                    "input": ["text", "image"] if model.get("supports_vision") else ["text"],
                    "cost": {"input": 0, "output": 0, "cacheRead": 0, "cacheWrite": 0},
                    "contextWindow": 200000,
                    "maxTokens": 8192,
                }
            )

        if not provider_models:
            continue

        provider_configs[provider_key] = {
            "baseUrl": str(provider.get("base_url") or "").strip(),
            "apiKey": str(provider.get("api_key") or "").strip(),
            "api": "openai-completions",
            "models": provider_models,
        }

    default_provider_id = int(runtime.get("default_provider_config_id") or 0)
    default_model_id = int(runtime.get("default_model_id") or 0)
    primary_model_ref = None
    image_model_ref = None

    if default_model_id > 0:
        for model in models:
            if int(model.get("id") or 0) != default_model_id:
                continue
            provider = providers_by_id.get(int(model.get("provider_config_id") or 0))
            if provider:
                provider_key = str(provider.get("provider_key") or "").strip()
                model_id = str(model.get("model_id") or "").strip()
                if provider_key and model_id:
                    primary_model_ref = f"{provider_key}/{model_id}"
                    if model.get("supports_vision"):
                        image_model_ref = primary_model_ref
                break

    if primary_model_ref is None and default_provider_id > 0:
        provider = providers_by_id.get(default_provider_id)
        if provider:
            provider_key = str(provider.get("provider_key") or "").strip()
            provider_models = models_by_provider.get(default_provider_id, [])
            if provider_key and provider_models:
                primary_model_ref = f"{provider_key}/{provider_models[0]['model_id']}"
                if provider_models[0].get("supports_vision"):
                    image_model_ref = primary_model_ref

    if primary_model_ref is None:
        for provider_key, provider_cfg in provider_configs.items():
            if provider_cfg["models"]:
                primary_model_ref = f"{provider_key}/{provider_cfg['models'][0]['id']}"
                if "image" in provider_cfg["models"][0].get("input", []):
                    image_model_ref = primary_model_ref
                break

    if image_model_ref is None:
        for provider_key, provider_cfg in provider_configs.items():
            for model in provider_cfg["models"]:
                if "image" in model.get("input", []):
                    image_model_ref = f"{provider_key}/{model['id']}"
                    break
            if image_model_ref is not None:
                break

    return provider_configs, primary_model_ref, image_model_ref


def update_model_runtime(config: dict, snapshot: dict) -> bool:
    provider_configs, primary_model_ref, image_model_ref = build_provider_models(snapshot)
    changed = False

    models_cfg = config.setdefault("models", {})
    if models_cfg.get("mode") != "merge":
        models_cfg["mode"] = "merge"
        changed = True

    current_providers = models_cfg.get("providers") if isinstance(models_cfg.get("providers"), dict) else {}
    if current_providers != provider_configs:
        models_cfg["providers"] = provider_configs
        changed = True

    agents = config.setdefault("agents", {})
    defaults = agents.setdefault("defaults", {})
    current_primary = (((defaults.get("model") or {}) if isinstance(defaults.get("model"), dict) else {}).get("primary"))
    current_image_primary = (((defaults.get("imageModel") or {}) if isinstance(defaults.get("imageModel"), dict) else {}).get("primary"))

    if primary_model_ref and current_primary != primary_model_ref:
        defaults["model"] = {"primary": primary_model_ref}
        changed = True

    if image_model_ref:
        if current_image_primary != image_model_ref:
            defaults["imageModel"] = {"primary": image_model_ref}
            changed = True
    elif "imageModel" in defaults:
        defaults.pop("imageModel", None)
        changed = True

    return changed


def run_systemctl(args: list[str]) -> None:
    subprocess.run(["systemctl", *args], check=True)


def main() -> int:
    parser = argparse.ArgumentParser(description="Sync OpenClaw runtime config from BugCatcher.")
    parser.add_argument("--config", required=True, help="Path to openclaw.json")
    parser.add_argument("--restart-service", default="", help="Systemd service to restart when config changes")
    parser.add_argument("--timeout-ms", type=int, default=30000, help="HTTP timeout in milliseconds")
    args = parser.parse_args()

    config_path = Path(args.config)
    if not config_path.is_file():
        print(f"Config file not found: {config_path}", file=sys.stderr)
        return 1

    config = load_json(config_path)
    plugin_cfg = (
        config.get("plugins", {})
        .get("entries", {})
        .get(PLUGIN_ID, {})
        .get("config", {})
    )

    base_url = str(plugin_cfg.get("bugcatcherBaseUrl") or "").strip()
    shared_secret = str(plugin_cfg.get("bugcatcherSharedSecret") or "").strip()
    if not base_url or not shared_secret:
        print("BugCatcher plugin config is missing bugcatcherBaseUrl or bugcatcherSharedSecret.", file=sys.stderr)
        return 1

    try:
        snapshot = fetch_runtime_snapshot(base_url, shared_secret, args.timeout_ms)
    except (urllib.error.URLError, urllib.error.HTTPError, json.JSONDecodeError) as exc:
        print(f"Failed to fetch runtime snapshot: {exc}", file=sys.stderr)
        return 1

    changed = False
    changed = update_discord_runtime(config, snapshot) or changed
    changed = update_model_runtime(config, snapshot) or changed
    if not changed:
        print("OpenClaw runtime sync: no changes.")
        return 0

    config_path.write_text(json.dumps(config, indent=2) + "\n", encoding="utf-8")
    print("OpenClaw runtime sync: updated Discord token/enabled state from BugCatcher.")

    if args.restart_service:
        run_systemctl(["restart", args.restart_service])
        print(f"OpenClaw runtime sync: restarted {args.restart_service}.")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
