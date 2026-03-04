from __future__ import annotations

from typing import Any

import httpx


class BugCatcherClient:
    def __init__(self, base_url: str, bearer_token: str) -> None:
        self._client = httpx.AsyncClient(
            base_url=base_url.rstrip("/"),
            headers={"Authorization": f"Bearer {bearer_token}"},
            timeout=30.0,
        )

    async def close(self) -> None:
        await self._client.aclose()

    async def health(self) -> dict[str, Any]:
        response = await self._client.get("/api/openclaw/health.php")
        response.raise_for_status()
        return response.json()

    async def link_context(self, discord_user_id: str) -> dict[str, Any]:
        response = await self._client.post("/api/openclaw/link_context.php", json={"discord_user_id": discord_user_id})
        response.raise_for_status()
        return response.json()

    async def find_duplicates(self, org_id: int, project_id: int, items: list[dict[str, Any]]) -> dict[str, Any]:
        response = await self._client.post(
            "/api/openclaw/checklist_duplicates.php",
            json={"org_id": org_id, "project_id": project_id, "items": items},
        )
        response.raise_for_status()
        return response.json()

    async def submit_batch(self, payload: dict[str, Any]) -> dict[str, Any]:
        response = await self._client.post("/api/openclaw/checklist_batches.php", json=payload)
        response.raise_for_status()
        return response.json()
