# Provider Setup

## Supported Provider Shape

The first release expects OpenAI-compatible HTTP APIs, including:

- OpenAI-hosted endpoints
- Codex-compatible endpoints
- Kimi-compatible endpoints

## Provider Fields

- provider key
- display name
- provider type
- base URL
- encrypted API key
- enabled flag
- supports-model-sync flag

## Model Requirements

At least one enabled model must:

- support image input
- support structured output or predictable text formatting
- be marked enabled in the super-admin UI

## Default Recommendation

- set one stable vision model as the runtime default
- keep experimental models disabled until verified
- prefer models that can follow duplicate-review and QA-detail prompts consistently

## Security Notes

- API keys are encrypted before storage
- secrets must never be copied into repo files
- the server-side encryption key is managed outside the UI
