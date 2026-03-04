from pydantic import BaseModel, Field
import os


class Settings(BaseModel):
    discord_bot_token: str = Field(alias="OPENCLAW_DISCORD_BOT_TOKEN")
    bugcatcher_base_url: str = Field(alias="OPENCLAW_BUGCATCHER_BASE_URL")
    internal_shared_secret: str = Field(alias="OPENCLAW_INTERNAL_SHARED_SECRET")
    log_level: str = Field(default="INFO", alias="OPENCLAW_LOG_LEVEL")

    @classmethod
    def from_env(cls) -> "Settings":
        payload = {
            "OPENCLAW_DISCORD_BOT_TOKEN": os.getenv("OPENCLAW_DISCORD_BOT_TOKEN", ""),
            "OPENCLAW_BUGCATCHER_BASE_URL": os.getenv("OPENCLAW_BUGCATCHER_BASE_URL", ""),
            "OPENCLAW_INTERNAL_SHARED_SECRET": os.getenv("OPENCLAW_INTERNAL_SHARED_SECRET", ""),
            "OPENCLAW_LOG_LEVEL": os.getenv("OPENCLAW_LOG_LEVEL", "INFO"),
        }
        return cls.model_validate(payload)
