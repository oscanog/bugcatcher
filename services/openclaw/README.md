# OpenClaw Service

This folder contains the Python runtime scaffold for the OpenClaw Discord service.

## Purpose

- connect to Discord in realtime
- read linked-user and project context from BugCatcher
- require images before checklist generation
- call AI providers for checklist drafting
- call BugCatcher internal APIs for duplicate review and final batch creation

## Quick Start

1. Create a virtual environment.
2. Install `requirements.txt`.
3. Copy `.env.example` to your runtime env file.
4. Configure the BugCatcher internal API URL and bearer token.
5. Start `python -m openclaw_bot.main`.

## Notes

- This scaffold is designed to run as `openclaw.service`.
- The production service should load secrets from `/etc/bugcatcher/openclaw.env`.
- BugCatcher remains the source of truth; OpenClaw should not write directly to the database.
