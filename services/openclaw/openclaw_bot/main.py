from __future__ import annotations

import asyncio
import logging

from dotenv import load_dotenv

from .api_client import BugCatcherClient
from .config import Settings
from .prompts import OPENCLAW_SYSTEM_PROMPT


async def run() -> None:
    load_dotenv()
    settings = Settings.from_env()
    logging.basicConfig(level=getattr(logging, settings.log_level.upper(), logging.INFO))
    logger = logging.getLogger("openclaw")
    logger.info("Starting OpenClaw service scaffold")
    logger.debug("Prompt length: %s", len(OPENCLAW_SYSTEM_PROMPT))

    client = BugCatcherClient(settings.bugcatcher_base_url, settings.internal_shared_secret)
    try:
        health = await client.health()
        logger.info("Connected to BugCatcher health endpoint: %s", health)
        logger.warning("Discord runtime scaffold is present, but full Discord event handling still needs service-specific implementation.")
        while True:
            await asyncio.sleep(300)
    finally:
        await client.close()


def main() -> None:
    asyncio.run(run())


if __name__ == "__main__":
    main()
