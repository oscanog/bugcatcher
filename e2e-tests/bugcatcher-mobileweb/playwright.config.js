const { defineConfig } = require("../api-v1/node_modules/@playwright/test");
const { cfg } = require("./src/config");

module.exports = defineConfig({
  testDir: "./tests",
  timeout: 120_000,
  expect: {
    timeout: 15_000,
  },
  fullyParallel: false,
  workers: 1,
  reporter: [["list"]],
  use: {
    baseURL: cfg.baseUrl,
    headless: true,
    trace: "retain-on-failure",
  },
  webServer: cfg.skipWebServer
    ? undefined
    : {
        command: "npm run dev -- --host 127.0.0.1 --port 4174",
        cwd: cfg.mobileRepoPath,
        url: cfg.baseUrl,
        reuseExistingServer: true,
        timeout: 120_000,
      },
});
