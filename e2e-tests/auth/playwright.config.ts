import { defineConfig } from "@playwright/test";
import { cfg } from "./src/config";

export default defineConfig({
  testDir: "./tests",
  timeout: 120_000,
  expect: {
    timeout: 10_000,
  },
  fullyParallel: false,
  workers: 1,
  reporter: [["list"]],
  use: {
    baseURL: cfg.baseUrl,
    ignoreHTTPSErrors: cfg.baseUrl.startsWith("https://"),
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
});
