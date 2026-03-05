# OpenClaw + Discord Self-Setup Runbook

This runbook is the fastest path to bring up upstream OpenClaw on BugCatcher using the `super_admin` control plane.

Target flow:

- `Discord -> OpenClaw -> BugCatcher checklist batch`

## 1. Prerequisites

Before setup, confirm these are already done:

- BugCatcher production is up
- DB migration for OpenClaw tables is applied
- at least one `super_admin` account can log in
- upstream OpenClaw is installed on the VM under `/opt/openclaw`
- `openclaw-gateway.service` and `openclaw-runtime-sync.timer` exist

## 2. Discord app + bot

In Discord Developer Portal:

1. Create/select your app
2. Add a bot user
3. Copy bot token
4. Enable intents:
   - `Guilds`
   - `Guild Messages`
   - `Message Content`
   - `Direct Messages`
5. Invite the bot to your server

Minimum bot permissions:

- View Channels
- Send Messages
- Read Message History
- Attach Files

## 3. Get guild and channel IDs

In Discord desktop app:

1. Enable `Developer Mode`
2. Right-click server -> `Copy Server ID`
3. Right-click target text channel -> `Copy Channel ID`

Use a server channel, not DM.

## 4. Configure BugCatcher `super_admin`

Open `/super-admin/openclaw.php` and configure tabs in this order.

### Channels

Add at least one enabled channel binding:

- `Guild ID`: copied server ID
- `Guild name`: your server name
- `Channel ID`: copied channel ID
- `Channel name`: channel name (example: `general` or `qa-intake`)
- `Enabled`: checked
- `Allow DM follow-up`: checked

### Providers

#### If you use Kimi Coding key

Use exactly:

- `Provider key`: `kimi-coding`
- `Display name`: `Kimi Coding`
- `Provider type`: `anthropic-compatible`
- `Base URL`: leave blank (Default)
- `API key`: your Kimi Coding key
- `Enabled`: checked

#### If you use Moonshot Open Platform key

Use exactly:

- `Provider key`: `moonshot`
- `Display name`: `Moonshot`
- `Provider type`: `openai-compatible`
- `Base URL`: `https://api.moonshot.ai/v1`
- `API key`: your Moonshot key
- `Enabled`: checked

Do not mix key types. Kimi Coding keys and Moonshot keys are not interchangeable.

### Models

For Kimi Coding provider:

- `Provider`: `Kimi Coding`
- `Remote model id`: `k2p5`
- `Display name`: `Kimi K2.5`
- `Supports JSON`: checked
- `Enabled`: checked
- `Default for provider`: checked

For Moonshot provider:

- `Provider`: `Moonshot`
- `Remote model id`: `kimi-k2.5`
- `Display name`: `Kimi K2.5`
- `Supports JSON`: checked
- `Enabled`: checked
- `Default for provider`: checked

### Discord

- `Enable OpenClaw`: checked
- `Discord bot token`: paste real token
- `Default provider`: select the provider you added
- `Default model`: select the model you added
- click `Save Runtime`
- click `Reload OpenClaw Config`

## 5. Runtime verification

In `Overview` tab, verify:

- `Runtime enabled: Yes`
- `Desired config version` equals `Applied config version`
- `Gateway state: running`
- `Discord state: configured`
- `Provider error: None`
- `Discord error: None`

Queue note:

- After clicking reload, `Queue item #... is pending` is expected briefly.
- It should clear after the runtime poll/reload cycle.

## 6. VM verification commands

Run on VM:

```bash
sudo systemctl status openclaw-gateway.service --no-pager -l
sudo systemctl status openclaw-runtime-sync.timer --no-pager -l
sudo systemctl status openclaw-runtime-sync.service --no-pager -l
sudo journalctl -u openclaw-gateway.service -n 120 --no-pager
```

Healthy signals in logs:

- `agent model: kimi-coding/k2p5` (or `moonshot/kimi-k2.5`)
- `channels resolved: <guild>/<channel>`
- `logged in to discord as ...`

## 7. End-to-end test

In the approved Discord channel:

1. Send `hello`
2. Send one image with checklist request prompt
3. If prompted, complete account linking
4. Choose organization/project
5. Confirm duplicate handling choice
6. Verify checklist batch is created in BugCatcher

## 8. Troubleshooting

### Bot replies `HTTP 401: Invalid Authentication`

Cause:

- provider key/model/key type mismatch

Fix:

- for Kimi Coding use `kimi-coding` + model `k2p5`
- for Moonshot use `moonshot` + model `kimi-k2.5`
- regenerate provider key and paste again
- save + reload config

### Logs show `Failed to resolve Discord application id`

Cause:

- invalid/missing bot token in runtime

Fix:

- regenerate Discord bot token
- save in `Discord` tab
- click `Reload OpenClaw Config`
- wait for runtime sync and retry

### Bot does not respond in channel

Check:

- runtime enabled in `Discord` tab
- channel is allowlisted in `Channels` tab
- bot is invited to same guild
- bot has send/read permissions
- `Message Content` intent is enabled

### `Applied config version` does not update

Check:

- `openclaw-runtime-sync.timer` is active
- gateway service is running
- no API auth error between VM and BugCatcher

## 9. Security checklist

- Never share bot token or provider API keys in screenshots/chat.
- If exposed, rotate immediately and save new secrets in `super_admin`.
- Keep OpenClaw gateway bound to localhost (`127.0.0.1`).
- Keep `OPENCLAW_INTERNAL_SHARED_SECRET` private and consistent between services.
