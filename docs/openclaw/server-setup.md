# Server Setup

## Runtime Placement

OpenClaw runs on the same GCP VM as BugCatcher, but as a separate Python service.

## Server Requirements

- Python 3.11+
- virtual environment for OpenClaw
- outbound HTTPS access to Discord and AI providers
- shared server config containing:
  - `OPENCLAW_INTERNAL_SHARED_SECRET`
  - `OPENCLAW_ENCRYPTION_KEY`
  - `OPENCLAW_TEMP_UPLOAD_DIR`
  - `OPENCLAW_LOG_LEVEL`

## Recommended Files

- code under `services/openclaw/`
- env file at `/etc/bugcatcher/openclaw.env`
- systemd unit `openclaw.service`

## Basic Deployment Steps

1. Deploy the latest BugCatcher code.
2. Create a Python virtual environment for OpenClaw.
3. Install dependencies from `services/openclaw/requirements.txt`.
4. Create `/etc/bugcatcher/openclaw.env`.
5. Install the systemd unit from `services/openclaw/systemd/openclaw.service`.
6. Enable and start `openclaw.service`.
7. Configure the runtime, providers, models, and channels in `/super-admin/openclaw.php`.

## Operational Commands

```bash
sudo systemctl status openclaw.service
sudo systemctl restart openclaw.service
sudo journalctl -u openclaw.service -n 200 --no-pager
```

## Temporary Image Storage

- OpenClaw stores inbound Discord images in `OPENCLAW_TEMP_UPLOAD_DIR`.
- The final BugCatcher ingest endpoint moves approved files into checklist batch attachments.
- Temporary files should be cleaned when a request finishes or expires.
