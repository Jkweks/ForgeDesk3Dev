# ForgeDesk – Office Staff Quick Start

For users with the **Office Staff** role. This role is read‑only for most of the
system: you can look things up and submit reservation *requests* for a manager to
approve, but you can't edit inventory, jobs, or work orders.

---

## 1. Logging in

1. Open ForgeDesk in your browser (bookmark the address your admin gave you, e.g.
   `http://fab.vosglassintra.net`).
2. Enter your **email** and **password**, click **Sign In**.
3. The menu on the left shows only the sections you have access to — expect
   **Dashboard**, **Inventory**, and **Fulfillment**. If you also need
   **Fabrication**, ask an admin to add it to the Office Staff role.

**Forgot your password?**

1. On the login screen click **Forgot Password?**
2. Enter your email and submit. If an account exists you'll get a reset email
   within a minute or two (check spam).
3. Click the link in the email, set a new password (min. 8 characters), and log
   in with it.

**First login:** if you're prompted to change your password, set a new one before
you can continue.

---

## 2. Viewing inventory — Boneyard & Door Shims

Open **Inventory** from the left menu (this is the main dashboard). Above the
product list is a row of tabs:

| Tab | Shows |
| --- | --- |
| **All Inventory** | Every product |
| **Low Stock** | Items at or below their reorder point |
| **Critical** | Items critically low |
| **Boneyard (No Cost)** | Salvaged / no‑cost stock (the "boneyard") |
| **Door Shims** | Products flagged as door shims |
| **Special Order** | Non‑stocked, order‑as‑needed items |

### Boneyard

- Click the **Boneyard (No Cost)** tab to list only boneyard items.
- A product tagged this way shows a grey **Boneyard** badge; normal items show a
  green **Stock** badge.
- Use the **search box** to find a specific part by name or SKU within the tab.

### Door Shims

- Click the **Door Shims** tab for the shim list.
- Shim products carry a blue **Door Shim** badge.

### Reading a product

Click any row to open its detail panel:

- **On Hand / Available** quantities, and where the stock lives
  (bin locations).
- Badges for **Boneyard/Stock** and **Door Shim**.
- Cost/price fields are hidden unless your role includes pricing access.

You can view and search everything here, but the **Edit** and **Adjust** buttons
won't be available to Office Staff.

---

## 3. Viewing work orders & fabrication status

> Requires the **Fabrication** menu. If you don't see it, ask an admin to grant
> the Office Staff role `nav.fabrication` and `fabrication.work-orders.view`.

### Work Orders list — **Fabrication → Work Orders**

A table of releases, ordered by priority:

| Column | Meaning |
| --- | --- |
| **#** | Queue priority (a pin icon means it's manually held at that spot) |
| **Release** | Job number + release, e.g. `4250403-R1` |
| **Job Name / PM** | Project and project manager |
| **Due** | Earliest requested date across the release's elevations (hover the ⓘ for first/last) |
| **Assigned** | Initials of the fabricators on the release |
| **Material** | `In Shop`, `SOF`, or a delivery date |
| **Elevations** | e.g. `3/8 done` |

- Use the **search box** (job # or name) and the **Material** filter to narrow
  the list.
- Toggle **Archived** to see completed/closed releases.
- Click a row to open the detail panel.

### Work Order detail panel

- **Header:** job, due date, priority, estimated time, material delivery, notes.
- **Assigned Workers:** who's on the release.
- **Steps:** the release‑level checklist (Cut List Prepared, Reviewed, etc.) as
  coloured dots.
- **Shop Drawings:** attached PDFs/drawings — click to download.
- **Elevations:** one row per elevation with quantity, joint count, estimated
  time, requested date, completion, and a row of **stage dots**. Click the
  chevron on a row to expand every stage with its status, who's assigned, and
  start/finish dates.

### Stage / step status colours

| Dot | Status |
| --- | --- |
| Grey | Pending |
| Amber | In progress |
| Green | Complete |
| Red | Blocked (waiting on an earlier step) |
| Light blue outline | Not required |
| Orange outline | On hold |

### Work Queue board — **Fabrication → Work Queue**

A live board grouped by operator, one card per stage:

- Filter by **job** and/or **work order** at the top.
- Tick **Hide locked** to hide stages still waiting on an earlier step.
- Each card shows the stage name, release, elevation tag, priority, and due date.
- The board **auto‑refreshes** about every 30 seconds.

Office Staff can watch this board to see what's in progress and who's working on
what; dragging cards to reassign is a fabrication‑lead action.

---

## What Office Staff can't do

- Edit or adjust inventory, jobs, purchase orders, or work orders.
- Approve reservations — you can **request** materials (draft), and a manager
  approves them.
- See pricing/cost fields unless specifically granted.

If you need access to something that's missing, contact an admin.
