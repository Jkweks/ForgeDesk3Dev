# ForgeDesk – Outbound Email Requirements

ForgeDesk needs to send transactional email (password-reset links, and later
notifications). It currently has no working mail path in production. We need an
SMTP relay it can authenticate to, plus a sender address that passes the
domain's anti-spoofing checks.

**Environment note:** the ForgeDesk server is hosted on the internal DNS name
`vosglassintra.net`, but **all outbound mail goes to `vosglass.com` recipients**
(staff mailboxes). The sending domain and relay need to deliver cleanly to
`@vosglass.com`.

## What we need from IT

### 1. SMTP relay connection details

| Field | Example | Notes |
|---|---|---|
| Host | `smtp.office365.com` / `smtp-relay.internal` | FQDN of the relay |
| Port | `587` (STARTTLS) or `465` (implicit TLS) | Tell us which |
| Username | `svc-forgedesk@vosglass.com` | Dedicated service account preferred |
| Password / app password | — | Send via a secure channel, not email |
| Auth method | LOGIN / PLAIN / none | If it's an IP-allowlisted internal relay with no auth, say so and allowlist the ForgeDesk server IP instead |
| TLS | Required? | We'll enable TLS by default |

### 2. Sender ("From") address

- A mailbox or send-as address on **`vosglass.com`**, e.g.
  **`noreply@vosglass.com`** — since recipients are all `@vosglass.com`, keeping
  the sender on the same domain avoids external-spoofing filters.
- It must be authorized to send through the relay above.
- Confirm **SPF** for `vosglass.com` includes the relay, and **DKIM** is signing
  for `vosglass.com` — otherwise reset emails land in spam or get rejected.
- The ForgeDesk host resolves as `vosglassintra.net`; if the relay does reverse
  DNS / HELO checks, let us know what HELO name it expects.

### 3. Volume / rate limits

- Any per-hour or per-message send limits on the relay or service account (so we
  can throttle if needed).
- Low volume expected: a handful of password resets per day, occasional batch
  notifications.

### 4. Internal deliverability

- Confirm mail from this sender is **not** quarantined by filtering on the way to
  `@vosglass.com` mailboxes (most password resets go to internal staff).

## What we do NOT need from IT

- No changes to the ForgeDesk server or app config — we handle that.
- No new DNS records unless SPF/DKIM for the chosen `vosglass.com` sender is
  missing (you'd tell us).

## Once we have the above

We'll enter the relay details into ForgeDesk, set the public app URL, confirm the
queue worker is processing mail, and send a test message to a `vosglass.com`
address. We'll report back if delivery fails so you can check relay/filter logs.
