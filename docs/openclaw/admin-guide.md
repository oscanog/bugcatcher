# Admin Guide

## Super-Admin Route

Use `/super-admin/openclaw.php`.

## Tabs

- `Overview`: health, default runtime summary, and rollout readiness
- `Discord`: bot token, runtime enablement, default provider, default model
- `Providers`: API providers and encrypted keys
- `Models`: model list and vision capability flags
- `Channels`: approved guild/channel bindings and DM follow-up policy
- `Users`: linked Discord users
- `Requests`: recent intake requests and submission outcomes
- `Documents`: full operational docs sourced from repo markdown

## Setup Order

1. Configure the Discord runtime.
2. Add at least one provider.
3. Add at least one vision-capable model.
4. Bind at least one Discord channel.
5. Ask users to link their Discord accounts.
6. Verify the health endpoint and service logs.

## Troubleshooting

- No bot response:
  - confirm the service is running
  - confirm the runtime is enabled
  - confirm the channel binding is enabled
  - confirm the Discord bot has the correct intents and permissions
- Link code fails:
  - check that the code is still within 10 minutes
  - confirm the user generated a fresh code from BugCatcher
- Batch creation fails:
  - confirm the selected project belongs to the selected organization
  - confirm the requester is still a member of that organization
  - confirm at least one image was uploaded
