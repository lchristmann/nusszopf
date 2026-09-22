import { expect } from '@playwright/test';

/**
 * The dev/CI Mailpit catcher (`compose.dev.yaml`, `docs/testing/README.md`) — `E2E_MAILPIT_URL` is
 * the host-published port (Playwright runs outside the Compose network). Specs that need it are
 * skipped without it, the same pattern `E2E_MEILISEARCH_URL` already uses for the recovery spec.
 */
export function mailpitUrl(): string | undefined {
    return process.env.E2E_MAILPIT_URL;
}

interface MailpitMessageSummary {
    ID: string;
    Subject: string;
    To: { Address: string }[];
    ReplyTo: { Address: string }[];
}

/** Polls Mailpit's own search API until a message to `to` with `subject` shows up, then returns its full HTML body. */
export async function waitForMail(to: string, subject: string): Promise<string> {
    const base = mailpitUrl();
    let latest: MailpitMessageSummary | undefined;

    await expect(async () => {
        const list = await fetch(`${base}/api/v1/messages?limit=50`).then((r) => r.json());
        latest = (list.messages as MailpitMessageSummary[]).find(
            (m) => m.Subject === subject && m.To.some((t) => t.Address === to),
        );
        expect(latest).toBeTruthy();
    }).toPass({ timeout: 20_000 });

    const full = await fetch(`${base}/api/v1/message/${latest!.ID}`).then((r) => r.json());

    return full.HTML as string;
}
