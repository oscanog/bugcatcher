# OpenClaw Docs

OpenClaw is BugCatcher's Discord-driven checklist intake assistant.

## What OpenClaw does

- listens in approved Discord channels
- requires a linked BugCatcher account
- requires at least one image before checklist generation
- asks the user to pick an organization and project
- drafts checklist batches with senior-QA-style detail
- checks project-scoped duplicates before submission
- creates the final checklist batch inside BugCatcher

## Documentation Map

- [Architecture](architecture.md)
- [Discord setup](discord-setup.md)
- [Provider setup](provider-setup.md)
- [Server setup](server-setup.md)
- [Super admin account](super-admin-account.md)
- [User guide](user-guide.md)
- [Admin guide](admin-guide.md)
- [API reference](api.md)
- [Implementation handoff](implementation-handoff.md)

## End-to-End Flow

1. A linked Discord user sends a message in an approved channel.
2. OpenClaw checks account linkage and image attachments.
3. OpenClaw resolves the user's organizations and projects from BugCatcher.
4. The user confirms the project.
5. OpenClaw analyzes the image and request.
6. OpenClaw drafts checklist items and checks duplicates in the selected project.
7. The user decides how duplicates should be handled.
8. OpenClaw submits the final batch to BugCatcher.

## Minimum Requirements

- BugCatcher `super_admin` role configured
- Discord bot token configured in the super-admin page
- at least one enabled AI provider
- at least one enabled vision-capable model
- at least one approved Discord channel binding
- `OPENCLAW_INTERNAL_SHARED_SECRET` and `OPENCLAW_ENCRYPTION_KEY` configured on the server
