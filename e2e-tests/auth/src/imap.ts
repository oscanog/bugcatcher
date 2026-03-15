import { ImapFlow } from "imapflow";
import { cfg } from "./config";

type WaitForOtpOptions = {
  recipient: string;
  since: Date;
  timeoutMs?: number;
  pollMs?: number;
};

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function normalizeEmail(value: string): string {
  return value.trim().toLowerCase();
}

function extractOtp(rawSource: Buffer | string): string | null {
  const sourceText = Buffer.isBuffer(rawSource)
    ? rawSource.toString("utf8")
    : String(rawSource);
  const match = sourceText.match(/\b(\d{6})\b/);
  return match ? match[1] : null;
}

export async function waitForLatestOtp({
  recipient,
  since,
  timeoutMs = 120_000,
  pollMs = 5_000,
}: WaitForOtpOptions): Promise<string> {
  const deadline = Date.now() + timeoutMs;
  const normalizedRecipient = normalizeEmail(recipient);

  while (Date.now() < deadline) {
    const client = new ImapFlow({
      host: cfg.imapHost,
      port: cfg.imapPort,
      secure: cfg.imapPort === 993,
      auth: {
        user: cfg.imapUser,
        pass: cfg.imapPassword,
      },
    });

    try {
      await client.connect();
      await client.mailboxOpen("INBOX");

      const uids = await client.search({ since });
      const recentUids = (uids === false ? [] : uids).slice(-20).reverse();

      for await (const message of client.fetch(recentUids, {
        envelope: true,
        internalDate: true,
        source: true,
      })) {
        const deliveredAt = message.internalDate instanceof Date ? message.internalDate : null;
        if (deliveredAt && deliveredAt.getTime() < since.getTime()) {
          continue;
        }

        const toList = message.envelope?.to ?? [];
        const recipients = toList
          .map((entry) => normalizeEmail(entry.address ?? ""))
          .filter((value) => value !== "");
        if (!recipients.includes(normalizedRecipient)) {
          continue;
        }

        const otp = extractOtp(message.source ?? "");
        if (otp) {
          return otp;
        }
      }
    } finally {
      await client.logout().catch(() => undefined);
    }

    await delay(pollMs);
  }

  throw new Error(`Timed out waiting for OTP email for ${recipient}.`);
}
