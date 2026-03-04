# Discord Setup

## Discord Prerequisites

- a Discord application
- a Discord bot user
- access to the target guilds and channels
- permission to invite the bot into the server

## Required Bot Intents

- `Guilds`
- `Guild Messages`
- `Message Content`
- `Direct Messages`

## Required Bot Permissions

- View Channels
- Send Messages
- Read Message History
- Attach Files
- Create Public Threads if your workflow uses them

## Recommended Invite Scopes

- `bot`
- `applications.commands`

## Channel Rules

- OpenClaw should only be enabled in channels explicitly configured in the BugCatcher super-admin page.
- Use focused intake channels such as `qa-intake` or `checklist-intake`.
- DM follow-up should stay enabled when users may need private duplicate-review or account-linking guidance.

## User Linking

1. User signs in to BugCatcher.
2. User opens the `Discord Link` page.
3. User generates a one-time code.
4. User DMs OpenClaw with `link <code>`.
5. OpenClaw confirms the BugCatcher identity and activates the link.

## Image Requirement

- OpenClaw must reject text-only checklist requests.
- At least one image attachment is required before analysis.
- Multiple images are allowed and recommended for larger checklist batches.
