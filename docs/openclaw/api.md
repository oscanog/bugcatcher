# OpenClaw Internal API

## Authentication

Bot-facing endpoints require:

```text
Authorization: Bearer <OPENCLAW_INTERNAL_SHARED_SECRET>
```

## Endpoints

### `POST /api/openclaw/link_prepare.php`

- session-authenticated BugCatcher endpoint
- generates a one-time link code

### `POST /api/openclaw/link_confirm.php`

- internal bot endpoint
- inputs: `code`, `discord_user_id`, optional Discord identity metadata
- output: linked BugCatcher user identity

### `POST /api/openclaw/link_context.php`

- internal bot endpoint
- input: `discord_user_id`
- output: linked user, organizations, and active projects

### `POST /api/openclaw/checklist_duplicates.php`

- internal bot endpoint
- inputs: `org_id`, `project_id`, `items`
- output: duplicate status and match summaries per proposed item

### `POST /api/openclaw/checklist_batches.php`

- internal bot endpoint
- creates the final checklist batch and checklist items
- requires at least one batch attachment token

### `GET /api/openclaw/health.php`

- internal bot endpoint
- returns runtime, provider, channel, and recent-request health summary

## Batch Submission Rules

- requester must be linked to the supplied Discord user
- requester must belong to the selected organization
- project must belong to the selected organization
- at least one image attachment is required
- final records are attributed to the linked requester
