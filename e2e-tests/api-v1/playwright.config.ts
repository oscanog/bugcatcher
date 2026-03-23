import { defineConfig } from "@playwright/test";
import { cfg } from "./src/config";

function readPositiveInt(key: string, fallback: number): number {
  const raw = process.env[key];
  if (!raw || raw.trim() === "") {
    return fallback;
  }
  const parsed = Number.parseInt(raw.trim(), 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

export default defineConfig({
  testDir: "./tests",
  timeout: readPositiveInt("E2E_API_TEST_TIMEOUT_MS", 20_000),
  expect: {
    timeout: readPositiveInt("E2E_API_EXPECT_TIMEOUT_MS", 8_000),
  },
  fullyParallel: true,
  workers: readPositiveInt("E2E_API_WORKERS", 6),
  reporter: [["list"]],
  use: {
    baseURL: cfg.baseUrl,
    extraHTTPHeaders: {
      "X-E2E-Env": cfg.envName,
    },
    ignoreHTTPSErrors: cfg.baseUrl.startsWith("https://"),
  },
});
