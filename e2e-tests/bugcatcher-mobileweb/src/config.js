const fs = require("node:fs");
const path = require("node:path");
const dotenv = require("../../api-v1/node_modules/dotenv");

const profilePath = path.resolve(__dirname, "..", ".env.local");
if (fs.existsSync(profilePath)) {
  dotenv.config({ path: profilePath, override: false });
}

function readEnv(key, fallback) {
  const value = process.env[key];
  if (typeof value === "string" && value.trim() !== "") {
    return value.trim();
  }
  return fallback;
}

function readBool(key, fallback) {
  const value = process.env[key];
  if (typeof value !== "string" || value.trim() === "") {
    return fallback;
  }
  return ["1", "true", "yes"].includes(value.trim().toLowerCase());
}

function account(email, password, systemRole, orgRole) {
  return Object.freeze({ email, password, systemRole, orgRole });
}

const cfg = Object.freeze({
  baseUrl: readEnv("E2E_MOBILE_BASE_URL", "http://127.0.0.1:4174").replace(/\/+$/, ""),
  apiBaseUrl: readEnv("E2E_MOBILE_API_BASE_URL", "http://localhost").replace(/\/+$/, ""),
  apiBasePath: `/${readEnv("E2E_MOBILE_API_BASE_PATH", "/bugcatcher/api/v1").replace(/^\/+/, "").replace(/\/+$/, "")}`,
  mobileRepoPath: readEnv("E2E_MOBILE_REPO_PATH", "C:\\projects\\school\\gendejesus\\bugcatcher-mobileweb"),
  skipWebServer: readBool("E2E_SKIP_WEB_SERVER", false),
  accounts: Object.freeze({
    superAdmin: account("superadmin@local.dev", "DevPass123!", "super_admin", "owner"),
    admin: account("admin@local.dev", "DevPass123!", "admin", "member"),
    pm: account("pm@local.dev", "DevPass123!", "user", "Project Manager"),
    seniorDev: account("senior@local.dev", "DevPass123!", "user", "Senior Developer"),
    juniorDev: account("junior@local.dev", "DevPass123!", "user", "Junior Developer"),
    qaTester: account("qa@local.dev", "DevPass123!", "user", "QA Tester"),
    seniorQa: account("seniorqa@local.dev", "DevPass123!", "user", "Senior QA"),
    qaLead: account("qalead@local.dev", "DevPass123!", "user", "QA Lead"),
  }),
});

module.exports = { cfg };
