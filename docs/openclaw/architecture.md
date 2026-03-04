# OpenClaw Architecture

## Service Boundaries

- BugCatcher PHP app:
  - stores users, organizations, projects, checklist batches, and audit data
  - exposes internal HTTP APIs for OpenClaw
  - provides the super-admin setup UI and documentation tab
- OpenClaw Python service:
  - manages Discord conversations
  - stores temporary intake state and source images
  - calls AI providers for image and checklist analysis
  - submits approved batches back to BugCatcher

## Main Data Flow

1. Discord event reaches OpenClaw.
2. OpenClaw resolves the Discord user against BugCatcher.
3. OpenClaw gathers org and project context.
4. OpenClaw stores request state and images.
5. OpenClaw drafts checklist items.
6. OpenClaw calls the duplicate-check endpoint.
7. OpenClaw collects duplicate decisions from the user.
8. OpenClaw calls the final batch-ingest endpoint.

## Trust Model

- OpenClaw does not write directly to the database.
- OpenClaw authenticates to BugCatcher APIs with `OPENCLAW_INTERNAL_SHARED_SECRET`.
- AI provider API keys are encrypted at rest in BugCatcher.
- Discord user identity is not trusted until the account-link workflow is completed.

## Audit Model

- the linked requester is recorded as `created_by` and `updated_by`
- Discord channel and message references are stored in batch metadata
- OpenClaw request tables preserve conversation status and duplicate decisions
- source images are stored as batch attachments
