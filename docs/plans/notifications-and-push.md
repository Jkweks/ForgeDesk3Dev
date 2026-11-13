# Plan: Per-user notifications with targeting, and phone push (PWA)

Status: PLAN, not started (written 2026-11-13).

## Goal

1. Replace the single shared admin "bell" with a real notification system where a notification
   can be addressed to **one user, a group of users (role / explicit list), or everyone**, and
   each recipient has their own read / dismissed state.
2. Optionally deliver the same notifications as **push notifications to phones** for installed
   PWAs: the field app (field crews) and, later, ForgeDesk itself (office / approvers).

Out of scope: email/SMS digests, notification preference UI beyond per-type mute (phase 4),
offline queueing of notifications, chat/replies.

## Current state (verified in code)

- `system_notifications` + `SystemNotification` model: global rows (`type`, `level`, `title`,
  `message`, `data` json, `dismissed_at`, `dismissed_by_user_id`). **One dismissal clears it for
  everyone.** No recipient concept.
- `NotificationController` (`GET /notifications`, `POST /notifications/{id}/dismiss`): admins see
  everything. Anyone with `shipments.approve` sees only `type = shipment_request` (added with the
  shipping-request feature, 2026-11-13). Everyone else gets `[]`.
- Bell UI: `resources/views/partials/notifications.blade.php`, polls every 60 s, modal detail with
  an optional link derived from `data`. Rendered only for admins / approvers.
- Producers today: backup health, special-order received (`SpecialOrderReceivedNotifier`),
  shipment inspection results (`ShipmentInspectionService`), shipping requests
  (`ShipmentRequestController`). They all call `SystemNotification::create(...)` directly.
- `users` have a single `role` (string, FK-ish to `roles.name`); permissions come from the role.
  There is no group/team concept.
- Field crews **do not have ForgeDesk accounts**. The field app (`field-app/`, Node + its own
  Postgres, own users in `fa_users` with cookie sessions in `fa_sessions`) is the only
  internet-facing piece; ForgeDesk is LAN-only and always initiates the connection
  (see `field-fleet-assets.md`, "Field app sync boundary").
- Field app already has a manifest link and registers `/sw.js` (offline shell); no push handler.
  The cut station PWA is a separate, kiosk-only PWA and is not a push target.

## Design

### 1. Data model (ForgeDesk)

Keep `system_notifications` as the **notification** (what happened), add recipients and
per-recipient state. Don't repurpose the dismissed columns.

```
notifications                       (rename/extend system_notifications; keep table, add columns)
  id, type, level, title, message, data json, url (nullable, where tapping goes),
  audience_type  enum: user | users | role | permission | all | field_users
  audience_value json (user ids / role names / permission name / field user ids)
  created_by (nullable user), expires_at (nullable), created_at

notification_recipients             (one row per user per notification, materialised at send time)
  id, notification_id, user_id, read_at, dismissed_at, created_at
  unique (notification_id, user_id), index (user_id, dismissed_at)

notification_preferences            (phase 4)
  user_id, type, in_app bool, push bool   -- absent row = default (on)

push_subscriptions                  (ForgeDesk users; phase 5)
  id, user_id, endpoint (unique), p256dh, auth, user_agent, last_used_at, created_at
```

Materialising recipients at send time (rather than resolving the audience on every read) gives:
independent read/dismiss per person, a stable audit of who was told, and cheap
`WHERE user_id = ? AND dismissed_at IS NULL` queries. Cost: a user who is added to a role later
doesn't see older notifications, which is the desired behaviour.

**Migration of existing rows:** existing global rows become `audience_type = permission`/`all-admins`
equivalents. Backfill: for each active existing row, create recipient rows for admins (and for
`shipment_request` rows, everyone with `shipments.approve`). Dismissed legacy rows are left
without recipients (history only).

### 2. Audience resolution

One service, `App\Services\Notifications\Notifier`, is the **only** way to create a notification:

```php
Notifier::send(
    type: 'shipment_request', level: 'info', title: ..., message: ..., data: [...], url: '/shipments/queue',
    to: Audience::permission('shipments.approve'),     // or ::user($id), ::users([..]), ::role('manager'),
                                                         //    ::all(), ::admins(), ::union(...)
);
```

- `Audience` is a small value object; `resolve(): Collection<User>` returns **active** users only,
  deduplicated, optionally excluding the actor (`->except($requester)` so people don't notify
  themselves).
- `Notifier::send` creates the notification row and the recipient rows in one transaction, then
  fires `NotificationSent` (queued listener) for the push fan-out. Producers never touch the
  tables directly; migrate the four existing producers to it.
- Audience types:
  | Type | Meaning |
  |---|---|
  | `user` / `users` | explicit user id(s) |
  | `role` | everyone whose `role` matches (admin always matches a permission audience, mirroring `hasPermission`) |
  | `permission` | everyone who has the permission (e.g. `shipments.approve`), evaluated at send time |
  | `all` | every active ForgeDesk user |
  | `field_users` | field-app users (see section 5), explicit ids or all |
- Groups beyond roles: **phase 3 option** `user_groups` + `user_group_user` ("Shipping team",
  "Maintenance on-call") managed in Settings, plus `Audience::group($id)`. Not needed for phase 1
  because role + permission + explicit user list cover current producers. Decide when a real use
  case needs a cross-role group.

### 3. API and in-app UI

- `GET /notifications?unread=1&limit=` : the signed-in user's own recipient rows joined to the
  notification (any user, no more admin gate; an empty list is the normal case).
- `POST /notifications/{id}/read`, `POST /notifications/{id}/dismiss`, `POST /notifications/read-all`:
  act on the caller's recipient row only. Never on someone else's.
- `GET /notifications/unread-count` for a cheap badge poll. Keep the 60 s poll for now; upgrade to
  push/SSE later only if needed.
- Bell is shown to **every logged-in user** (remove the admin/approver `@if`), badge = unread
  count, list shows unread first, "Mark all read", per-item dismiss, tap = mark read + go to `url`.
- Admin-only **Send notification** screen (Settings): title, message, optional link, audience
  picker (everyone / role / pick users), "send push too" checkbox. Gated by new permission
  `notifications.send` (admin by default). Manual announcements are the same pipeline with
  `type = announcement`.
- Retention: scheduled command prunes recipient rows dismissed > 30 days ago and notifications past
  `expires_at` / older than 90 days with no live recipients.

### 4. Preferences (phase 4)

Per user, per notification **type**: in-app on/off, push on/off. Types are registered in one
config array (`config/notifications.php`: key, label, default level, whether a user may mute it,
default audience hint) so the preference screen and the producers share one source. Some types are
**non-mutable** (e.g. shipping request for approvers, backup failures for admins). Quiet hours are
deliberately not included in v1.

### 5. Phone push (Web Push over a PWA)

How it works: the PWA's service worker subscribes via `pushManager.subscribe()` with a VAPID
public key; the browser returns an endpoint on the platform's push service (Apple / Google /
Mozilla) plus encryption keys; our server stores that and later POSTs an encrypted payload to the
endpoint; the OS wakes the service worker, which calls `showNotification()`; tapping it opens the
`url`. Library: `laravel-notification-channels/webpush` (`minishlink/web-push`) on the Laravel
side, `web-push` npm package on the field-app side. No per-message cost, no third-party account.

Platform behaviour to design for:
- **Android / Chrome:** works in the browser and installed; delivered when the app is closed.
- **iPhone (iOS 16.4+):** push only works for a PWA **added to the Home Screen and opened from
  there**, and the permission prompt must be triggered by a user tap. So: an explicit "Turn on
  notifications" button, never an on-load prompt. Detect iOS-not-installed and show "Add to Home
  Screen first" instead.
- Delivery is best-effort (battery saver, Focus modes, revoked permission). Push is a **nudge**;
  the in-app bell stays the source of truth and nothing critical relies on push alone.
- HTTPS is required (production already is).

Two audiences, two plumbing paths, because of the LAN-only / public-field-app boundary:

**A. Field crews (field app)**: the main use case.
- Field users are in the field app's DB, not ForgeDesk's `users`. Their push subscriptions live in
  the field DB (`fa_push_subscriptions`: user_id, endpoint, keys, last_used_at).
- ForgeDesk must not be reachable from the internet and must not receive inbound connections, so
  keep the existing pattern: ForgeDesk **publishes outward**. `fleet:sync-out` (or a sibling
  `notifications:sync-out`) writes notifications addressed to field users into a field-DB table
  `fa_notifications` (id, field_user_id, title, body, url, created_at, sent_at, read_at).
- The field-app server holds the VAPID keys, runs a small worker that sends any row with
  `sent_at IS NULL` to that user's subscriptions, deletes subscriptions that return 404/410, and
  exposes `GET /api/notifications` for the in-app list. VAPID keys only grant the ability to send
  pushes to that app's subscribers, so holding them on the public side is acceptable. They are
  separate from the ForgeDesk keys.
- Read/dismiss in the field app flows back through the existing inbound sync so ForgeDesk can show
  "delivered / read" if wanted (optional).
- Producers worth wiring first: shipment assigned / date changed / cancelled for a driver or crew,
  "delivery check due", inspection rejected / needs re-do, maintenance due for an asset they
  operate. Audience = `Audience::fieldUsers([...])` resolved from the assignment.

**B. ForgeDesk users on their phones (approvers, office)**: later phase.
- Make ForgeDesk itself installable (manifest + a service worker scoped to push only; **no
  offline caching of authenticated pages**, to avoid stale/leaky data). `push_subscriptions`
  table above; subscribe after login via an explicit button in the user menu.
- Constraint: ForgeDesk is LAN-only, so a tapped notification only opens when the phone is on the
  shop Wi-Fi / VPN. Push itself still arrives (the push service is reached outbound from the
  server and the phone's own internet). Make the notification text self-sufficient ("SHP request:
  Job 2001, Nov 20, 3 categories") so the approver can decide whether it needs action before
  being on-network. If remote approval from the phone is desired, that is a separate decision
  about exposing ForgeDesk (or a thin approve endpoint) and is **not** assumed here.
- Sending is ForgeDesk-initiated outbound only, which fits the boundary.

**Payload rules (both paths):** title + short body + `url`; no PII beyond job name/number, no
costs, no tokens in the URL. Push payload is encrypted end-to-end to the device, but treat the
lock-screen as visible to bystanders. Collapse key per notification `type + target id` so repeated
updates replace rather than stack. Set a TTL (e.g. 24 h) so stale "delivery today" pushes aren't
delivered next week.

### 6. Security and privacy

- Recipient scoping enforced **server-side on every notification endpoint** (the recipient row is
  the access check). Frontend hiding is UX only.
- `url` must be a same-origin relative path; validate on create and when rendering.
- Subscription endpoints are write-only secrets: never return them in any API response or log
  them. Remove a subscription on logout from that device, on 404/410 from the push service, and on
  user deactivation.
- Rate-limit `POST /push/subscribe` and the admin send endpoint; cap announcement size.
- Audit: `created_by` + recipient rows are the record of who was notified.

## Phases

| # | Phase | Deliverable | Notes |
|---|-------|-------------|-------|
| 1 | Per-user in-app notifications | Tables + migration/backfill, `Notifier` + `Audience`, per-user endpoints, bell for every user, migrate the 4 producers | Biggest behaviour change; replaces the admin gate and shared dismissal. Tests for audience resolution, per-user dismiss isolation, legacy backfill |
| 2 | Manual announcements | `notifications.send` permission, Settings > Send notification screen (user / role / all) | Uses the same pipeline |
| 3 | Groups (only if needed) | `user_groups`, `Audience::group()`, admin UI | Skip unless role/permission/explicit isn't enough |
| 4 | Preferences + retention | `notification_preferences`, type registry config, prune command | |
| 5 | Field app push | `fa_push_subscriptions`, `fa_notifications`, sync-out, worker, sw `push` + `notificationclick`, "Turn on notifications" button, iOS install hint, first producers (shipment assignment) | Needs VAPID keys in the field-app env; HTTPS prod only |
| 6 | ForgeDesk PWA push (approvers/office) | manifest, push-only service worker, `push_subscriptions`, webpush channel on `Notifier` | Decide first whether remote (off-LAN) approval is wanted |

Phases 1 and 5 are independent in code (5 only needs the field-user audience type), so they can
proceed in parallel; 6 depends on 1.

## Open questions

1. Who gets phone push first: field crews (phase 5) or office approvers (phase 6)? Plan assumes
   field crews because they're already on a PWA and off the shop network.
2. Do approvers need to approve shipping requests from their phone off-network? If yes, that needs
   its own security design (VPN vs exposing a narrow endpoint).
3. Are roles enough for groups, or do you need named cross-role teams (phase 3)?
4. Should field users also be addressable by role (driver, installer) or only by explicit
   assignment? The field app's `fa_users` role field would have to be synced out for this.
5. Should dismissing in the bell mark the notification read for everyone with the same audience
   (a "someone handled it" mode)? For approvals this exists today (clears on decision); the plan
   keeps it as an explicit per-type flag, `resolves_on_action`, rather than the default.

## Testing

- Audience resolution: user / users / role / permission / all, inactive users excluded, actor
  excluded, admin matches permission audiences, dedupe across unions.
- Isolation: user A dismissing doesn't affect user B; user cannot read/dismiss another user's row.
- Backfill: legacy active rows land on the right recipients; dismissed legacy rows don't resurface.
- Producers: each of the four still notifies the right people after the migration.
- Push (phase 5/6): subscription saved/replaced/removed, 410 prunes the subscription, payload
  contains no secrets, collapse key + TTL set, push failure never breaks the originating request
  (sent from a queued listener).
- Manual: install on a real Android phone and a real iPhone (Home Screen) and verify the
  permission flow, delivery with the app closed, and tap-to-open.
