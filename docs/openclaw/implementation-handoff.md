# OpenClaw Implementation Handoff

This document is the complete handoff checklist for the next developer. It covers all remaining work after the current foundation changes.

## Current Status

Implemented in the repo now:

- BugCatcher-side schema design in `infra/database/schema.sql`
- `super_admin` role support in PHP auth normalization
- super-admin setup route at `/super-admin/openclaw.php`
- user Discord linking page at `/discord-link.php`
- internal OpenClaw API endpoints under `api/openclaw/`
- repo-backed Documents tab support
- OpenClaw Python scaffold under `services/openclaw/`
- OpenClaw documentation set under `docs/openclaw/`

Not fully complete yet:

- the live database has not been migrated from this repo alone
- the Python service is only a scaffold, not the finished Discord bot runtime
- the AI provider execution pipeline is not implemented
- the duplicate-review conversation loop is not implemented end to end
- the GCP server setup for OpenClaw has not been completed from this repo alone

## Phase Tracking

## Phase 1: Database And Config Rollout

Status: foundation coded, migration rollout still pending

Remaining tasks:

- apply the `users.role` enum change to the real database
- create all new OpenClaw tables in the live database:
  - `checklist_batch_attachments`
  - `discord_user_links`
  - `discord_channel_bindings`
  - `openclaw_runtime_config`
  - `ai_provider_configs`
  - `ai_models`
  - `openclaw_requests`
  - `openclaw_request_items`
  - `openclaw_request_attachments`
- confirm foreign keys succeed against the current production schema
- seed at least one real `super_admin` user or promote an existing admin
- update production shared config with:
  - `OPENCLAW_INTERNAL_SHARED_SECRET`
  - `OPENCLAW_ENCRYPTION_KEY`
  - `OPENCLAW_TEMP_UPLOAD_DIR`
  - `OPENCLAW_LOG_LEVEL`
- create the actual temp directory on the server and set write permissions
- verify that existing login and role behavior still works after adding `super_admin`

## Phase 2: BugCatcher PHP Hardening

Status: base routes and helpers are present, but deeper production polish is still required

Remaining tasks:

- run PHP lint against all changed PHP files in an environment with `php` on PATH
- test `/discord-link.php` end to end:
  - generate code
  - unlink
  - regenerate
- test `/super-admin/openclaw.php` against a migrated database
- add CSRF protection to the new super-admin and discord-link forms if the app standard requires it
- add stronger validation and clearer error messages for:
  - provider keys
  - provider base URLs
  - model ids
  - guild ids
  - channel ids
- verify `source_reference` length is safe for serialized Discord metadata
- consider whether some larger Discord metadata should move from `source_reference` into a dedicated table
- review whether the markdown renderer needs stricter escaping rules or richer formatting support
- test the Documents tab on mobile and desktop
- verify the legacy dashboard and organization sidebars still render correctly after the new links were added
- review whether `Manage Users` and `All Reports` placeholders should stay hidden until implemented

## Phase 3: OpenClaw Python Service Completion

Status: scaffold only

The file `services/openclaw/openclaw_bot/main.py` is not the finished service. It currently proves config loading and BugCatcher connectivity only.

Remaining tasks:

- implement the real Discord client startup using `discord.py`
- subscribe to guild messages and DMs
- restrict responses to configured channels plus DM follow-up behavior
- load approved channel bindings from BugCatcher at startup and refresh them periodically
- implement normal-message intake start behavior
- implement linked-user lookup on every relevant Discord message
- implement the introductory message for new or unlinked users
- implement the `link <code>` DM flow that calls `api/openclaw/link_confirm.php`
- implement request/session persistence into `openclaw_requests`
- implement request-item persistence into `openclaw_request_items`
- implement source-image persistence into `openclaw_request_attachments`
- implement resumable session recovery after process restart
- implement timeout and stale-session cleanup behavior
- implement project-selection prompts
- implement organization-selection prompts for multi-org users
- implement clarifying-question prompts before generation
- implement image download from Discord attachments
- enforce image-only requirement before generation
- reject unsupported attachment MIME types clearly
- add request logging with request ids and Discord references
- add structured error handling and retry-safe behavior

## Phase 4: AI Drafting Pipeline

Status: prompt scaffold only

Remaining tasks:

- implement provider adapters for:
  - OpenAI-compatible endpoints
  - Codex-compatible endpoints
  - Kimi-compatible endpoints
- decide and implement one normalized provider request format
- implement model selection logic from BugCatcher runtime config
- ensure only enabled vision-capable models are selectable for checklist drafting
- implement actual image + text prompt submission
- implement draft parsing into normalized checklist item objects
- ensure elaborated descriptions always include:
  - test intent
  - setup or prerequisites
  - user action
  - expected result
  - verification note when needed
- implement provider failure handling:
  - timeout
  - malformed response
  - model unavailable
  - quota or auth failure
- add per-request prompt logging policy without storing raw secrets
- decide whether to store prompt/response excerpts for audit or debugging

## Phase 5: Duplicate Review Workflow

Status: BugCatcher duplicate endpoint exists, Discord review flow not implemented

Remaining tasks:

- call the duplicate endpoint after checklist drafting
- summarize duplicates in Discord before submission
- implement the three decision paths:
  - skip duplicates and add only new items
  - add all duplicates again
  - review duplicates one by one
- persist duplicate decisions into `openclaw_request_items`
- ensure final submission filters `final_include` correctly
- improve duplicate matching logic beyond the current conservative helper if needed
- validate duplicate UX against real checklist data with long descriptions and module/submodule variants

## Phase 6: Final Submission And Media Flow

Status: PHP ingest path exists, but end-to-end OpenClaw upload flow is unfinished

Remaining tasks:

- implement the Python-side temp file upload strategy
- decide how Discord-downloaded images are named before final submission
- ensure every final submission sends at least one valid image attachment token
- persist source images as `checklist_batch_attachments`
- confirm checklist item creation, batch creation, and source metadata render correctly on the checklist page
- verify created records are attributed to the linked requester, not a bot user
- optionally enrich `notes` with request summaries and QA verification prompts

## Phase 7: Super-Admin UX Completion

Status: route exists, but still needs production-grade behavior

Remaining tasks:

- confirm the route works cleanly with real migrated tables
- optionally add edit-in-place support for existing providers, models, and channels
- add revoke controls for linked Discord users if desired
- add pagination or filtering if the request/user tables grow large
- add live health details:
  - last heartbeat timestamp
  - last provider error
  - last Discord connection state
- decide whether the runtime page should support test actions like:
  - test provider
  - test duplicate endpoint
  - test Discord token validation
- verify the Documents tab remains in sync whenever repo docs change

## Phase 8: GCP Deployment For OpenClaw

Status: not deployed from this repo yet

Remaining tasks on the GCP server:

- install Python 3.11+ if not already available
- create a dedicated virtual environment for OpenClaw
- install dependencies from `services/openclaw/requirements.txt`
- copy `services/openclaw/systemd/openclaw.service` to `/etc/systemd/system/openclaw.service`
- create `/etc/bugcatcher/openclaw.env`
- populate `/etc/bugcatcher/openclaw.env` with:
  - `OPENCLAW_DISCORD_BOT_TOKEN`
  - `OPENCLAW_BUGCATCHER_BASE_URL`
  - `OPENCLAW_INTERNAL_SHARED_SECRET`
  - `OPENCLAW_LOG_LEVEL`
- make sure the service user can read the env file
- make sure the service user can write to `OPENCLAW_TEMP_UPLOAD_DIR`
- run:
  - `sudo systemctl daemon-reload`
  - `sudo systemctl enable openclaw.service`
  - `sudo systemctl start openclaw.service`
- verify:
  - `sudo systemctl status openclaw.service`
  - `sudo journalctl -u openclaw.service -n 200 --no-pager`
- confirm outbound access to:
  - Discord
  - OpenAI-compatible endpoints
  - Kimi-compatible endpoints
- if the production VM still uses `/var/www/bugcatcher` directly instead of release directories, update the unit `WorkingDirectory` and `ExecStart` paths accordingly
- confirm the BugCatcher domain used by OpenClaw resolves correctly from the VM
- test OpenClaw startup after a VM reboot

## Phase 9: Production Validation

Status: pending

Remaining tasks:

- create or promote a `super_admin` user
- save a real Discord bot token in the super-admin route
- save at least one provider and one vision-capable model
- bind one real Discord channel
- generate a Discord link code from a BugCatcher user
- complete account linking in Discord
- test a full image-based checklist flow in Discord
- verify duplicate detection with:
  - a unique item set
  - an exact duplicate set
  - a mixed set with one-by-one decisions
- verify the checklist batch appears in BugCatcher
- verify source images appear on the batch detail page
- verify requester attribution is correct
- verify unlinked users get the introduction and guidance
- verify a user with multiple orgs gets the org/project selection flow
- verify text-only requests are rejected

## Phase 10: Optional Hardening

Status: not required for first merge, but recommended

Possible follow-up work:

- add automated tests for PHP helper functions
- add automated tests for Python API client and conversation state machine
- add DB migration files instead of relying only on the full schema snapshot
- add a cleanup job for stale temp images
- add richer markdown support for the Documents tab
- add encrypted-secret rotation tooling
- add rate limiting for internal OpenClaw endpoints if needed
- add webhook or admin alerting for repeated provider failures
- add a service heartbeat writeback from OpenClaw into BugCatcher health tables

## Recommended Execution Order For The Next Developer

1. Migrate the database and create a real `super_admin`.
2. Configure shared secrets and temp directories on the server.
3. Finish the Python Discord runtime.
4. Finish AI provider execution and duplicate review flow.
5. Deploy `openclaw.service` on GCP.
6. Run full production validation with a real Discord channel and images.

## Important Files Already Added

- `app/openclaw_lib.php`
- `api/openclaw/`
- `discord-link.php`
- `super-admin/openclaw.php`
- `docs/openclaw/`
- `services/openclaw/`

## Important Caveat

The current repo now contains the structure and most of the BugCatcher-side foundations for OpenClaw, but it should not be described as feature-complete until the Discord runtime, AI execution, duplicate interaction loop, database rollout, and GCP service deployment are fully finished and validated.
