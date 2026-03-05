import fs from "node:fs";
import path from "node:path";
import dotenv from "dotenv";
import { z } from "zod";

const resolvedEnv = (process.env.E2E_ENV ?? "local").trim().toLowerCase();
const envName = resolvedEnv === "production" ? "production" : "local";

const profilePath = path.resolve(__dirname, "..", `.env.${envName}`);
if (fs.existsSync(profilePath)) {
  dotenv.config({ path: profilePath, override: false });
}

const optionalPositiveInt = z.preprocess((value) => {
  if (value === undefined || value === null || value === "") {
    return undefined;
  }
  return value;
}, z.coerce.number().int().positive().optional());

const schema = z.object({
  E2E_BASE_URL: z.string().url(),
  E2E_EMAIL: z.string().min(1),
  E2E_PASSWORD: z.string().min(1),
  E2E_ORG_ID: z.coerce.number().int().positive(),
  E2E_PROJECT_ID: z.coerce.number().int().positive(),
  E2E_ASSIGNED_QA_LEAD_ID: optionalPositiveInt,
  E2E_ASSIGNED_USER_ID: optionalPositiveInt,
});

const parsed = schema.safeParse(process.env);
if (!parsed.success) {
  const reasons = parsed.error.issues.map((issue) => {
    const field = issue.path.join(".") || "env";
    return `${field}: ${issue.message}`;
  });
  throw new Error(
    `Invalid E2E configuration for E2E_ENV=${envName}\n${reasons.join("\n")}`
  );
}

const env = parsed.data;

export const cfg = Object.freeze({
  envName,
  baseUrl: env.E2E_BASE_URL,
  email: env.E2E_EMAIL,
  password: env.E2E_PASSWORD,
  orgId: env.E2E_ORG_ID,
  projectId: env.E2E_PROJECT_ID,
  assignedQaLeadId: env.E2E_ASSIGNED_QA_LEAD_ID ?? null,
  assignedUserId: env.E2E_ASSIGNED_USER_ID ?? null,
});

export type E2EConfig = typeof cfg;
