# 02 — In-page "Guide" pop-ups inventory

Written for the branch secretary / branch DB administrator training-of-trainers (TOT), as a source for the training manual. It is based on the codebase as of 2026-09-30 (`main`, `34b445a`). This was a read-only review: no code was changed.

**How to read this document**

- **Guide content** is extracted automatically from the Blade source and converted to Markdown. The wording is unchanged. Formatting was simplified:
  - accordion section titles are shown as **▸ Title**;
  - icons were dropped;
  - bold text in the app is shown as bold here.
- Some parts of a guide only appear for certain permissions. These are marked *[Blade: `@can('…')`] … [Blade: `@endcan`]*. Text inside `{{ … }}` is a live value filled in by the app, such as a count.
- **Roles** use these abbreviations:

| Abbrev. | Role | Access level |
|---|---|---|
| SA | super-admin | national |
| NA | National DB Administrator | national |
| NAs | National DB Assistant (can be given extra direct permissions: *Approve Campaign Requests*, *Print Certificates*, *Print ID Cards*) | national |
| Obs | Observer (national) | national |
| BS | Branch Secretary | branch |
| BA | Branch DB Administrator | branch |
| BAs | Branch DB Assistant | branch |
| DF | Division DB Assistant — Finance | division |
| DO | Division DB Assistant — Operations | division |

  Role → permission data comes from the **local** database (`role_has_permissions`), cross-checked against `PermissionsTableSeeder`. Re-check on the VPS if the seeders have been re-run differently there.
- **Data scope:** a person with branch-level access only sees their own branch's data, and a person with division-level access only their own division's. National roles see all branches. This applies on every page below and is not repeated each time.
- **⚠** marks a place where the guide text and the live screen or code disagree.

---

## 1. How the Guide pop-up is built

| Aspect | Detail |
|---|---|
| Component | `resources/views/components/help-popup.blade.php`, used as `<x-help-popup>` |
| Mechanism | A native HTML `<dialog>`. The trigger button calls `showModal()`. The pop-up closes with the **×** icon, the **Close** button, or a click on the backdrop. Only the accordions inside use Alpine.js. |
| Trigger | The optional `trigger` slot sets the button's label and look. Labels in use: **Guide** (most index pages), **Take action!** (Dashboard lifecycle boxes), **What is this?** (Dashboard housekeeping cards) and **What to do** (planning tools). Without a trigger slot, the button is a grey **?** icon; this happens once, for the Dashboard Statistics Glossary. The `trigger-class="help-btn"` style is defined in `resources/css/app.css` (`.help-btn`). |
| Content | The content is written **inline in each page's Blade view** as the component's slot. There is no config file, JSON, language file or database table for guide text. Most guides share the same pattern: a header ("How do I…" or "<Topic> Guidelines") and an Alpine accordion (`x-data="{ open: null }"`, `x-collapse`) with one collapsible section per task. A small shared component, `<x-wizard-path wizard-name="…">`, supplies the recurring "Use Persons → Campaign filter wizard → …" sentence. |
| Permission-aware text | Some sections are wrapped in `@can` / `@cannot`, so the guide shows different text for users who can approve or print. |
| Changing text | Changing guide text means editing the view and deploying. |
| Count | **31 pop-ups in 25 views** (the Dashboard alone has 7). |

---

## 2. Summary: pages with a Guide, in sidebar order

The sidebar order comes from `resources/views/components/layouts/admin.blade.php`. Pages that are not in the sidebar are listed at the end, with where users reach them from.

| # | Sidebar label | Route name | URL | Page heading (as shown) | Route requires | Roles that can open it |
|---|---|---|---|---|---|---|
| 2.1 | Dashboard | `reports.dashboard` (the menu item goes to `admin.dashboard`, which redirects here) | `/reports` | "<Branch> Statistics" dashboard (no page-header slot) | `view_reports` | All 9 |
| 2.2 | Persons | `users.index` | `/users` | **Persons** — Find & Filter | `view_user` | All 9 |
| 2.3 | Red Cross Units | `red-cross-units.index` | `/red-cross-units` | **Red Cross Units** — Find & Filter | `view_red_cross_unit` | All 9 |
| 2.4 | Task Forces | `task-forces.index` | `/task-forces` | **Task Forces** — Find & Filter | `view_task_force` | All 9 |
| 2.5 | Organisations | `organisations.index` | `/organisations` | **Organisations** — Find & Filter | `manage-admin-panel` only (the menu item needs `view_organisation`) | Menu: NA, NAs, BS, BA, BAs. By URL: all 9 (see §4). |
| 2.6 | Branches | `branches.index` | `/branches` | **Branches** — Find & Filter | `view_branch_information` | NA, NAs, BS, BA, DF, DO |
| 2.7 | Divisions | `divisions.index` | `/divisions` | **Divisions** — Find & Filter | `view_division_information` | NA, NAs, BS, BA, DF, DO |
| 2.8 | Payment Records | `membership-payments.index` | `/membership-payments` | **Payments** — List of recorded payments | `view_payments` | SA, NA, NAs, Obs, BS, BA, BAs, DF (**not DO**) |
| 2.9 | Volunteering Log | `activities.index` | `/activities` | **Volunteer Activity Log** — List of recorded volunteering hours | `view_volunteering` | All 9 |
| 2.10 | Training Records | `trainings.index` | `/trainings` | **Trainings** — List of recorded trainings | `view_trainings` | All 9 |
| 2.11 | Donation Records | `donations.index` | `/donations` | **Donations** — List of recorded donations | `view_donations` | SA, NA, NAs, Obs, BS, BA, BAs, DF (**not DO**) |
| 2.12 | Campaign Management | `campaigns.admin.proposed` | `/campaigns/admin/proposed` | **Campaigns Management** — Overview | `campaign_request_approve` | NA (plus NAs with the direct permission) |
| 2.13 | **<CODE> Campaigns** (e.g. "LAG Campaigns"; "NAT Campaigns" for national) | `campaigns.mine` | `/campaigns/mine` | **<CODE> Campaigns** | `campaign_request_create` | NA, NAs, BS, BA |
| 2.14 | ID Cards | `id-cards.prepare-bulk-print` | `/id-cards/prepare-bulk-print` | **Bulk ID Card Printing** — FILTER & SELECT | `view_idcards` (printing needs `print_idcards`) | View: all 9. Print: NA (plus NAs with the direct permission) |
| 2.15 | Certificates | `certificates.index` | `/certificates` | **Certificates** — Certificate Generator | `view_certificates` (printing needs `print_certificates`) | View: all 9. Print: NA, BS, BA (plus NAs with the direct permission) |
| 2.16 | Archive Tool | `dormant-users.index` | `/dormant-users` | **Archive Tool** | `use_archive_tool` | NA, NAs, BS, BA |
| 2.17 | Authorizations | `users.roles.edit` | `/users/roles` | **Manage User Roles and Permissions** | `manage_roles_and_permissions` | SA, NA, BS, BA |
| 2.18 | Settings | `admin.settings.index` | `/admin/settings` | **National Settings** | `change_settings` + password confirmation | NA |
| 2.19 | Log | `logs.index` | `/logs` | **Audit Log** | `view_log` | NA, NAs, Obs, BS, BA |
| 2.20 | *(Settings → Membership Fees)* | `membership-fees.index` | `/membership-fees` | **Membership Fee Types** | `manage-admin-panel` + password confirmation | Linked only from Settings (NA). By URL: all 9 (see §4). |
| 2.21 | *(Settings → Training Types)* | `training-types.index` | `/training-types` | **Training Types** | `manage-admin-panel` + password confirmation | Linked only from Settings (NA). By URL: all 9 (see §4). |
| 2.22 | *(Dashboard → Planning Tools → Welcome Campaign Planner)* | `reports.campaign-planning.welcome` | `/reports/campaign-planning/welcome` | **Welcome Campaign Planner** | `view_reports` | All 9 |
| 2.23 | *(Planning Tools → Re-engage Dormant)* | `reports.campaign-planning.dormant` | `/reports/campaign-planning/dormant` | **Re-engage Dormant Volunteers** — For re-engaging dormant volunteers | `view_reports` | All 9 |
| 2.24 | *(Planning Tools → Expiring Membership)* | `reports.campaign-planning.expiring-membership` | `/reports/campaign-planning/expiring-membership` | **Expiring Membership Campaign Planner** | `view_reports` | All 9 |
| 2.25 | *(Planning Tools → Training Statistics)* | `reports.trainings.stats` | `/reports/trainings/stats` | **Training Statistics** | `view_reports` | All 9 |

Sidebar pages **without** a Guide: none. Every sidebar item has one.

---

## 3. Page-by-page detail

### 2.1 Dashboard (`/reports`)

Seven pop-ups. They are listed in the order they appear on the page.

**Page actions**
- **Select branch:** dropdown (national users can also choose "National").
- **Refresh now** (refreshes the statistics cache).
- **Show more statistics** (extended statistics mode).
- **Open Tutorials**.
- Lifecycle Overview boxes, each with a **Take action!** Guide.
- **Planning Tools** links: Welcome Campaign Planner, Expiring Membership, Re-engage Dormant, Training Statistics, ID Card Expiry Report, Red Cross Units, Donation Appreciation, All Campaigns.
- **Trends & Statistics** links and **Heat Maps**.
- **Database Administration** section (the **Tutorial Completion** report is shown to branch and national users).
- Pending-approval counts per module.
- **Housekeeping** cards, each with **View** and **What is this?**.

There is no search box, bulk action or export.

#### Pop-up 1 — Statistics Glossary
Trigger: grey **?** icon. Only shown in extended mode, after **Show more statistics** is clicked.

*Source: `resources/views/dashboard.blade.php:815`*

> **Statistics Glossary**
>
> - **Renewal Rate** — Percentage of members whose membership expired in the last 12 months and who renewed afterward. Good: ≥ 70% · Moderate: 50–69% · Poor: < 50%
> - **Membership Revenue** — Total membership fees paid in the last 12 months, compared with the previous 12-month period.
> - **Volunteers** — Persons attached to an active Red Cross unit, with lifecycle status active or dormant. Historical comparisons are based on nightly statistics snapshots. For dates before the snapshot system was introduced, figures are approximated from unit assignment dates, so volunteers who later left a unit are not reflected.
> - **Training & First Aid** — Number of trainings conducted in the last 12 months (all types). First Aid training count is listed separately below.
> - **First Aid training** — Total number of First Aid–related trainings conducted during this period.
> - **Donations** — Total cash donations received in the last 12 months, split into individual (personal) and organisation-sponsored contributions.
> - **Registrations** — People who created a profile in the last 12 months (new member or volunteer registrations).
> - **Red Cross Units** — Units currently marked as active in the database.
> - **Leadership Coverage** — Active units that have at least one team leader or assistant. Units without leadership indicate a gap to follow up.

#### Pop-up 2 — Lifecycle Overview → Pending engagement → **Take action!**

*Source: `resources/views/dashboard.blade.php:885`*

> **Pending Engagement**
>
> **{{ number_format($dashboardData['lifecycleAwaitingEngagement']) }} persons** have registered but need guidance before they become active.
>
> Volunteers
>
> **{{ number_format($dashboardData['pendingVolunteers']) }}** are interested in volunteering. Place them in a **Red Cross Unit**. Once placed they can train and contribute.
>
> Members
>
> **{{ number_format($dashboardData['pendingMembers']) }}** are interested in membership. Guide them to pay their **membership fee**.
>
> Your mission:
>
> Get them engaged!
>
> **▸ How people leave Pending**
>
> - Assigning them to a **Red Cross Unit** — they become Active right away.
> - A qualifying **membership payment** — once it's approved, they become Active.
>
> **▸ One by one**
>
> - Go to **Persons → Show more filters** → set **Lifecycle Status:** Pending engagement → set **Wants to contribute as** Member or Volunteer → click **Filter**
> - Call each person and find out what they need — a unit placement, payment instructions, or both.
> - For **volunteers**: open their profile → **Edit → Select Red Cross Unit → Update Person**. They move to Active once the record is approved.
> - For **members**: explain how to pay the membership fee. Once payment is recorded and approved, they move to Active automatically.
> - If the person is unreachable after reasonable attempts, consider archiving them to keep the list clean. See instructions below.
>
> **▸ Campaigns**
>
> - Make a campaign. Send a welcome message — one clear message with a simple next step.
> - Use planning tool Welcome Campaign Planner to strategize.
> - Design a message:
>   - For aspiring **members**: Instruct how to make payment.
>   - For aspiring **volunteers**: Instruct how to call the branch. Give telephone number to contact person, and times to call (call window).
> - Use **Persons → Campaign filter wizard → Welcome newly registered persons** to build the list → Optional: Choose segments: Area · Gender · Age → **Make campaign from filter** and follow the wizard.
> - For members: Once payment is recorded and approved, the member returns to Active automatically.
> - For volunteers: When they call, find the person and assign to a Red Cross Unit: **Edit → Select Red Cross Unit → Update Person**
>
> Not reachable? Archive them
>
> - **Persons** → Edit → Scroll down → Tick **Archive this user** → Update Person
> - Or use the **Archive tool** for bulk archiving
>
> Archived users: {{ number_format($dashboardData['lifecycleArchived']) }}

⚠ The guide says **Persons → Show more filters**. The live button on Persons is **Show all filters**.

#### Pop-up 3 — Lifecycle Overview → Active → **Take action!**

*Source: `resources/views/dashboard.blade.php:1042`*

> **Active**
>
> *[Blade: `@php $dormantMonths = \App\Models\Setting::getInt('membership.dormant_after_months', 12); @endphp`]*
>
> **{{ number_format($dashboardData['lifecycleActive']) }} persons** are currently active. For volunteers, active means they are assigned to a Red Cross Unit and have recent activity records — after **{{ $dormantMonths }} months** of inactivity they move to Dormant. For members, active means their membership fee is valid — when it expires and is not renewed, they move to Dormant.
>
> Your mission:
>
> Keep them active!
>
> **▸ How status changes**
>
> Any new record — training, membership, volunteering, or a donation — keeps a person Active.
>
> Go quiet too long, and the overnight check moves them to Dormant automatically.
>
> **▸ Newsletters / Mobilisation**
>
> - Send regular updates — news, achievements, and upcoming events — to keep members and volunteers informed and connected.
> - Use the All Campaigns planning tool to review previously sent newsletters.
> - Use **Persons → Campaign filter wizard → Send a newsletter** to build the list → Optional: Choose segments: Area · Gender · Age → **Make campaign from filter** and follow the wizard.
> - The wizard lets you target volunteers, members, or both.
>
> **▸ Membership renewal**
>
> - **Be proactive!** Send renewal reminders before fees expire.
> - Check Expiring Membership in Planning Tools for members whose fee is about to lapse.
> - One well-timed reminder before expiry is enough — keep the message clear and the next step simple.
> - Use **Persons → Campaign filter wizard → Remind about expiring membership** to build the list → Optional: Choose segments: Area · Gender · Age → **Make campaign from filter** and follow the wizard.
> - Once payment is recorded and approved, the member returns to Active automatically.
>
> **▸ Certificates**
>
> - Print certificates for completed trainings as recognition. Certificates are a simple but effective way to recognise effort and maintain engagement.
> - Check Training Statistics in Planning Tools for an overview of who has and has not received a training certificate.
> - To see which certificates have already been printed: **Persons** → apply a filter → open the Certificates tab.
> - To print individual certificates: **Persons** → Search/filter → View → scroll to the relevant record section → click **Print certificate**.
> - For bulk printing: use **Certificates** in the main menu.
> - Note that certain certificates can only be printed at NRCS Headquarters.
>
> **▸ Training**
>
> - Invite people to First Aid trainings, refreshers, and skills development sessions.
> - Check Training Statistics in Planning Tools for an overview of who has and has not completed a given training type.
> - Depending on your strategy, you can run different types of campaigns — invitations, expiry reminders, or refresher nudges.
> - Use **Persons → Campaign filter wizard → Invite to upcoming training** to build the list → Optional: Choose segments: Area · Gender · Age → **Make campaign from filter** and follow the wizard.
> - Use **Persons → Campaign filter wizard → Remind about expiring training certification** to build the list → Optional: Choose segments: Area · Gender · Age → **Make campaign from filter** and follow the wizard.
> - Use **Persons → Campaign filter wizard → Refresh stale first-aid training** to build the list → Optional: Choose segments: Area · Gender · Age → **Make campaign from filter** and follow the wizard.
> - **Log training records promptly** — this keeps activity timestamps current and prevents volunteers from slipping into Dormant unnecessarily.
>
> **▸ ID Cards**
>
> - Print ID cards for volunteers. A valid ID card signals belonging and supports field operations. The QR code on the back links to a summary of the volunteer's trainings and activity.
> - Check ID Card Expiry Report in Planning Tools for an overview of printed cards and upcoming renewals.
> - Before bulk printing, make sure each volunteer's data is complete. Volunteers can check this themselves via **My Team → ID Card Completeness Report**, which shows whether:
>   - A profile photo is uploaded and not too old.
>   - A signature is uploaded.
>   - A national ID number is entered.
>   - A membership fee has been paid.
> - Once all data is in order, HQ can proceed with bulk printing.
>
> **▸ Fundraiser / Appeals**
>
> - Send an urgent appeal — for an emergency response, a specific need, or a general fundraising push — to a wide audience of members and volunteers.
> - Use the All Campaigns planning tool to review previously sent appeals.
> - Use **Persons → Campaign filter wizard → Fundraising appeal** to build the list → Optional: Choose segments: Area · Gender · Age → **Make campaign from filter** and follow the wizard.
> - The wizard lets you target Volunteers + Members, Members only, high-end members, or previous donors.
>
> **▸ Donation thank-you message**
>
> - Send thank-you messages to recent donors — acknowledged donors are more likely to give again.
> - Check Donation Appreciation in Planning Tools for an overview of who has donated and who has already received an appreciation message.

⚠ The guide says "click **Print certificate**". On the person record the links are type-specific: **Print membership certificate**, **Print volunteering certificate**, **Print attendance certificate**, **Print competence certificate** and **Print donation certificate**.

#### Pop-up 4 — Lifecycle Overview → Dormant → **Take action!**

*Source: `resources/views/dashboard.blade.php:1259`*

> **Dormant**
>
> *[Blade: `@php $dormantMonths = \App\Models\Setting::getInt('membership.dormant_after_months', 12); @endphp`]*
>
> **{{ number_format($dashboardData['lifecycleDormant']) }} persons** were previously active but have not shown activity for a period of time. For volunteers this means no records have been entered for the last **{{ $dormantMonths }} months**. For members it means their membership has expired and not been renewed.
>
> Your mission:
>
> Bring them back!
>
> **▸ How to bring someone back**
>
> - Entering and approving a new record — training, activity, donation, or payment — moves them back to Active.
> - For members, a renewed and approved payment does the same.
>
> **▸ Membership renewal**
>
> - **Be proactive!** Send renewal reminders before fees expire — see the **Active** section for instructions.
> - Run a renewal campaign with one clear message about how to make a payment.
> - Use the Expiring Membership planning tool to identify who to contact.
> - Use **Persons → Campaign filter wizard → Expiring membership** to build the list → Optional: Choose segments: Area · Gender · Age → **Make campaign from filter** and follow the wizard.
> - Once payment is recorded and approved, the member returns to Active automatically.
>
> **▸ Re-engage volunteers**
>
> - Use the Re-engage Dormant planning tool to identify who to contact and where they are.
> - Design a reactivation message — for example, an invitation to an upcoming activity or training.
> - Use **Persons → Campaign filter wizard → Re-engage dormant volunteers** to build the list → Optional: Choose segments: Area · Gender · Age → **Make campaign from filter** and follow the wizard.
> - Once a new record (volunteering, training, or payment) is entered and approved, they return to Active automatically.
>
> Not reachable? Archive them
>
> - **Persons** → Edit → Scroll down → Tick **Archive this user** → Update Person
> - Or use the **Archive tool** for bulk archiving
>
> Archived users: {{ number_format($dashboardData['lifecycleArchived']) }}

#### Pop-up 5 — Housekeeping → Hanging Registrations → **What is this?**

*Source: `resources/views/dashboard.blade.php:1681`*

> **Hanging Registrations**
>
> When **an admin registers a prospective member**, the registration must be **followed by payment**. If payment is not completed, it becomes a 'hanging registration.' Payment should be made as soon as possible.
>
> **{{ number_format($dashboardData['hangingRegistrationCount']) }}** is the number of hanging registrations since **{{ \Carbon\Carbon::parse($dashboardData['dbMigrationDate'])->format('j M Y') }}** (when the new database was launched). **{{ number_format($dashboardData['hangingRegistrationTotalCount']) }}** is the total number, including hanging registrations from the old database.
>
> Payment approval can take a little time, so this number won't always sit at exactly 0 — that's expected. What matters is that it isn't growing over time.
>
> How to find them:
>
> **Persons** → set filter to **Registered by Admin** → **Life-cycle = Pending**.

#### Pop-up 6 — Housekeeping → Unverified Registrations → **What is this?**

*Source: `resources/views/dashboard.blade.php:1737`*

> **Unverified Registrations**
>
> These are persons who **haven't confirmed their email address yet.** This doesn't currently block them from using the system — it's just a signal that their email might be mistyped or their confirmation email may have gone to spam.
>
> How to find them:
>
> **Persons** → filter by **Unverified email**.
>
> 💡 Tip: set **Lifecycle → All** to make sure you see everyone.
>
> What to do:
>
> - If this is a recent registration, it may just need time — some people don't check their inbox right away.
> - If it's been a while, **consider reaching out** to confirm their email is correct. They can update it and re-confirm from their own profile. Also consider **archiving the account.**

#### Pop-up 7 — Housekeeping → Volunteers in Limbo → **What is this?**

*Source: `resources/views/dashboard.blade.php:1775`*

> **Volunteers in Limbo**
>
> These are volunteers who were once assigned to a Red Cross Unit but have since been removed from it, without being assigned to a new one. This usually happens mid-transfer between branches — someone is unassigned from their old unit, but the process stalls before a new branch picks them up.
>
> How to find them:
>
> **Persons** → **Members/Volunteers filter** → **Unassigned**.
>
> What to do:
>
> - A **National DB Administrator** can move the person directly to the correct branch — they aren't limited to their own branch.
> - Or, the volunteer can update their own branch via **My Profile** — but they may need a nudge or a call, since they may not realize the move is still incomplete.
> - Once assigned to a unit again, they'll drop off this count automatically.

---

### 2.2 Persons (`users.index`, `/users`)

**Guide** (trigger **Guide**)

*Source: `resources/views/users/index.blade.php:13`*

> **How do I...**
>
> **▸ Assign person to Red Cross Unit**
>
> - Find the person using Search. **Edit → Red Cross Unit → Select in dropdown**.
> - If you don't find the correct Red Cross Unit, you might need to change the Division first.
> - Scroll down → click **Update Person**.
> - Once assigned, the person automatically moves out of Pending Engagement and becomes Active.
> - Assigning someone to a Red Cross Unit is what makes them a volunteer in the system — it's the defining step.
>
> **▸ Why can't I find a person?**
>
> - The default view only shows people who are Active or Dormant.
> - To search everyone: **Show all filters → Lifecycle → All**.
> - Then search again using name, email, or DB number.
>
> **▸ A person can't log in — what do I do?**
>
> - Ask: does the person have an email on file?
> - **Yes:**
>   - Tell them to log in with their email.
>   - Forgot the password? Use **Forgot your password?** on the login page.
>   - Reset email never arrives? Check spam/junk first. Still nothing? The email may be wrong. Search → **Edit** → fix the email → click **Update Person**. Then try **Forgot your password?** again.
> - **No:**
>   - They log in with their phone number and password.
>   - If the number matches more than one account, the system asks a few questions: DB number (on their ID card — they can skip it), then first and last name, then maybe birth year. This is normal.
>   - **Best fix:** ask if they have an email. If yes, add it now: Search → **Edit** → enter the email → click **Update Person**. Next time they can reset their own password.
> - **To find the account:**
>   - Search by name or phone number.
>   - Not found? **Show all filters → Lifecycle → All**.
>   - Open the account with **View**, then click **Edit** to make changes.
>
> **▸ Move user to another branch/division**
>
> - **Same branch:**
>   - Search → **Edit** → select new **Division**
>   - Click **Update Person**
> - **Different branch:**
>   - First **unassign** from their Red Cross Unit
>   - A unit belongs to one branch only
> - **Then, either:**
>   - Person updates branch via **My Profile** → new branch assigns a unit
>   - Or **National HQ** moves them directly
> - **Branch admins:** can only move people within their **own branch**
> - **Admin role?** Must be removed first. Contact your Branch or HQ.
>
> **▸ What is a Volunteer in Limbo?**
>
> - Someone who was once assigned to a Red Cross Unit, but is no longer assigned to any unit.
> - This usually happens mid-move — someone was unassigned from their old unit, but the process stalled before a new branch picked them up.
> - They show up here because **they need to be re-assigned to a unit**, or their situation needs a second look.
>
> **▸ Add photo/signature image**
>
> - Find the person using Search, then click **Edit**.
> - Under the photo: **Choose File → Update Profile Photo**.
> - Under the signature: **Choose File → Update Signature**.
> - (You do not need to click the Update Person button for these).
> - You can also capture both images directly using the built-in camera.
> - A recent, clear photo is required for printed ID cards — use the **Profile Photo & Signature** filter to find persons missing one.
>
> **▸ Start a campaign from Persons**
>
> - Set a filter — e.g. Branch, Division, Volunteers only, Age group — then click **Filter** and check the number of records found.
> - Not sure how to filter? Try **Campaign filter wizard** for ready-made strategies like Welcome newly registered persons or Re-engage dormant volunteers.
> - If the group isn't too large, click **Make campaign from filter** — this hands off to the campaign wizard (Purpose → Audience → Throttling → Message → Review).
> - If the audience is too big, narrow the filter first — a focused message reaches people better than a broad one.
>
> **▸ Archive user**
>
> - Find the person using Search, then click **Edit**.
> - Scroll down → **Tick 'Archive this user'**.
> - Click **Update Person**.
> - Use this for someone who has permanently left the organisation, rather than just gone quiet for a while.
> - Archived users are hidden from the list — use the **Show archived** button to find them again.
> - You cannot archive your own account.
>
> **▸ Activate archived user**
>
> - Archived users are hidden from the list — use the **Show archived** button to find them again.
> - Find the person using **Search → click Edit**
> - Scroll down → **Untick 'Archive this user'**.
> - Click **Update Person**.
> - The person returns to Active status

**Page actions**

| Type | Details |
|---|---|
| Buttons | **Register New Person** (`add_user`: NA, NAs, BS, BA, BAs). **Filter**. **Clear**. **Show all filters** / **Hide filters**. **Campaign filter wizard** and **Make campaign from filter** (both need `campaign_request_create`: NA, NAs, BS, BA; **Make campaign from filter** stays disabled until a filter is set). Per row: **View** only. **Edit** is on the person's record page, not on the list. |
| Search | One **Search** box, placeholder "Name, DB, email, phone, NIN". It matches: **first name / last name** (partial; with several words, each word must match the first or last name), **email** (partial), **Telephone 1 / Telephone 2** (partial), the **person ID** (exact, numeric only, i.e. the number part of the DB-number) and the **NIN** (exact 11-digit match only, via a hash). It does **not** match middle name, a full DB-number string such as "DB-7442-LAG-Ikeja", or the branch/unit name. |
| Filters | **Branch** → **Division** → **Red Cross Unit** (cascading; Branch is national-only). **Task Force**. **Gender** (All / Male / Female). **Age group** (All ages, Under 18, Adults only (18+), Youth (18–35), Adults (36–59), Elderly (60+) and finer bands). **Supporting Members / Volunteers** (All / Supporting Members / Volunteers / Unassigned (Volunteers in Limbo)). **Lifecycle Status** (Operational (Active+Dormant) [default] / Pending Engagement / Active / Dormant / Archived / All (Including Archived)). **Profile Photo & Signature**. **Payments**. **Wants to contribute as**. **Org Representatives**. **Team Leaders**. **National ID (NIN)**. **Digital Activity**. **Email**. **Email Verification**. **Registration Source**. **Database Roles**. **Trainings**. **Training Expiry**. **First Aid Refresher**. **Donations**. **Campaign Messages**. |
| Sort | **Sort By**: Registration Date (Newest First) [default] / Registration Date (Oldest First) / First Name (A-Z) / First Name (Z-A). |
| View modes (tabs) | **Standard View**, **Volunteering**, **Trainings**, **Donations**, **Certificates**, **Campaigns**. |
| Display | **Show profile photos** tick box (remembered in the browser). The result count reads "Found N results", followed by a plain-language description of the active filters. |
| Campaign filter wizard goals | Welcome newly registered persons · Re-engage dormant volunteers · Remind about expiring membership · Invite to upcoming training · Refresh stale first-aid training · Remind about expiring training certification · Appreciate donors · Send a newsletter · Fundraising appeal. Each goal ends with **Apply filter & close**. |
| Bulk actions / exports | None. |

⚠ Guide vs. live screen:
- "use the **Show archived** button". There is no such button on Persons. Use **Lifecycle Status → Archived** or **All (Including Archived)** instead.
- "**Show all filters → Lifecycle → All**". The filter is labelled **Lifecycle Status**, and the option is **All (Including Archived)**.
- "Find the person using Search. **Edit** …". The list only offers **View**; open the record first, then click **Edit**.
- "search again using name, email, or DB number". Only the numeric part of the DB-number works.

---

### 2.3 Red Cross Units (`red-cross-units.index`, `/red-cross-units`)

**Guide** (trigger **Guide**)

*Source: `resources/views/red-cross-units/index.blade.php:25`*

> **Red Cross Unit Guidelines**
>
> **▸ General information**
>
> - A **RC Unit** is the permanent home for volunteers.
> - When a person is assigned to a RC Unit, they are automatically moved to **Active** status.
> - A unit belongs to a Division, which belongs to a Branch — use the filters above to narrow down by either.
> - Each unit can have a Team Leader and an Assistant Team Leader, shown in the table.
>
> **▸ Add / edit a unit**
>
> - Press **Add New Unit** to create one.
> - Press **View → Edit** on an existing unit to change its details.
>
> **▸ Add / remove persons**
>
> - Go to **Persons → Edit**, then choose the unit from the dropdown.
> - To remove someone from a unit, change their dropdown selection to a different unit, or clear it.
>
> **▸ Leadership assignment**
>
> - Open the unit's **View → Edit** page.
> - Under **Leadership Assignment**, set the Team Leader and Assistant Team Leader.
> - Only persons already assigned to the unit can be set as leaders.
>
> **▸ Archive / Reactivate**
>
> - A unit no longer in use can be archived from its **View → Edit** page.
> - An archived unit can be reactivated later from the same page, if needed.

**Page actions**

| Type | Details |
|---|---|
| Buttons | **Add New Unit** (`add_red_cross_unit`: NA, NAs, BS, BA). **Filter**. **Clear**. Per row: **View**. |
| Search | **Search** (placeholder "Search by unit name..."). Matches the **unit name** only (partial). |
| Filters | **Branch**. **Division**. **Status** (Active / Archived). **Annual Fee** (All / Paid / Expiring in 28 days / Not paid). |
| Sort | None (always by name). |
| Table | Name · Branch / Division · Members · Leadership · Annual Fee · Actions. |
| Bulk / export | None. |

---

### 2.4 Task Forces (`task-forces.index`, `/task-forces`)

**Guide** (trigger **Guide**)

*Source: `resources/views/task-forces/index.blade.php:26`*

> **Task Force Guidelines**
>
> **▸ General information**
>
> - A **task force** is a focused team created to carry out a specific mission or activity.
> - It may be **temporary** (for a one-time project) or **permanent** (for ongoing work).
> - A person can belong to more than one task force at the same time — there is no limit.
> - Any registered volunteer can join any task force — across branches or divisions. For example, a person from Borno can join a task force based in Lagos.
>
> **▸ Add / edit a task force**
>
> - Press **Add New Task Force** to create one.
> - Press **>View → Edit** on an existing task force to change its details.
> - Use clear names and descriptions so others understand the team's purpose.
>
> **▸ Add / remove members**
>
> - Open the task force's **>View → Edit** page.
> - Use the search box to find and add a person as a member.
> - Only active volunteers show up in search — persons assigned to an active Red Cross Unit. Members who haven't been assigned to a unit, or whose unit is inactive, won't appear.
> - Click **Remove** next to a member's name to take them off the task force.
>
> **▸ Leadership assignment**
>
> - Open the task force's **>View → Edit** page.
> - Set the **Team Leader** and **Assistant Team Leader** from the members already assigned.
> - Only members of the task force can be set as leaders.
>
> **▸ Archive / Reactivate**
>
> - Archive task forces that are inactive. Use the 'Archive' button.
> - An archived task force can be reactivated at any time. Set filter **Status → Archived** to find them.
> - Deactivate inactive team members to keep the database organised. Use **>View → Edit → Task Force Members → Remove.**

**Page actions**

| Type | Details |
|---|---|
| Buttons | **Add New Task Force** (`add_task_force`: NA, NAs, BS, BA). **Filter**. **Clear**. Per row: **View**; **Msg** (starts a campaign to that task force's members; `campaign_request_create`); **Reactivate** (`edit_task_force`) / **Archive** (`remove_task_force`). **Create First Task Force** (shown when the list is empty). |
| Search | **Search** (placeholder "Search by task force name..."). Matches the **task force name** only. |
| Filters | **Branch** (branch-select). **Status** (Active / Archived). **Type** (All Types / task-force types). |
| Sort | None (by name). |
| Bulk / export | None. |

⚠ The guide text contains stray ">" characters ("**>View → Edit**"). It also says any registered volunteer can join, but its own later section says the member search only finds people in an active Red Cross Unit.

---

### 2.5 Organisations (`organisations.index`, `/organisations`)

**Guide** (trigger **Guide**)

*Source: `resources/views/organisations/index.blade.php:20`*

> **Organisation Guidelines**
>
> **▸ General information**
>
> - An **organisation** is a company or institution that belongs to a branch.
> - It can pay membership fees, make donations, receive certificates, and receive campaign messages.
> - Unlike persons, an organisation **cannot register itself** — it can only be added by a branch.
> - An organisation must have at least one linked person before it can make payments or donations.
>
> **▸ Add organisation**
>
> - The **contact person(s)** must first register an account on the homepage.
> - Then **register the organisation** here.
> - Finally, **link the contact person(s)** to the organisation.
> - If more than one person is linked, mark **one as the primary contact**.
> - Linked persons do **not need to be members or volunteers themselves** — but it is encouraged, since it keeps them active in the system.
>
> **▸ Add payments / donations**
>
> - To add a **membership payment**: Search for organisation → View → Add Payment.
> - To add a **donation**: Search for organisation → View → Add Donation.
> - If no persons are linked, the organisation **cannot make donations or membership payments**.
>
> **▸ Campaigns**
>
> - Organisations can be included in campaigns.
> - Set a filter above — e.g. Branch or Membership status — then click **Make campaign from this filter** to launch the campaign wizard with that audience pre-loaded.
> - When a campaign reaches an organisation, it is sent to **both** the organisation's own email/SMS contact details **and** its linked contact person(s).
> - If no contact persons are linked, only the organisation's own email/SMS (if provided) will receive the message.
>
> **▸ Archive**
>
> - Find the organisation using Search, then click **View**.
> - Scroll down to **Danger Zone → Archive Organisation**.
> - Use this for an organisation that has permanently ended its relationship with the branch.
> - Archived organisations are hidden from active lists but can be restored later.
>
> **▸ Reactivate**
>
> - Find the organisation using Search, with **Show archived** enabled.
> - Click **View**, then click **Restore**.
> - The organisation returns to the active list immediately.

**Page actions**

| Type | Details |
|---|---|
| Buttons | **Add Organisation**. **Filter**. **Make campaign from this filter** (`campaign_request_create`). Per row: **View**; **Link persons**; **Restore** (archived list). |
| Search | **Search** (placeholder "Name, email, phone, reg. number..."). Matches **name, short name, registration number, email, phone** (partial) and the **organisation ID** (exact, numeric). |
| Filters | **Branch**. **Status** (Active / Archived). **Membership** (All / Members / Expiring in 14 days / Expiring in 28 days / Non-members). |
| Sort | **Sort By**: Name A–Z [default] / Name Z–A / Newest first / Oldest first. |
| Bulk / export | None. |

⚠ The guide's "Reactivate" section says "with **Show archived** enabled". The live control is **Status → Archived**.

---

### 2.6 Branches (`branches.index`, `/branches`)

**Guide** (trigger **Guide**)

*Source: `resources/views/branches/index.blade.php:12`*

> **How do I...**
>
> **▸ Read the branches table**
>
> - Rows highlighted in **red** are missing key information — a physical/postal address, phone, email, or public contacts.
> - **Divisions**, **RC Units**, **Volunteers**, and **Members** show live counts for each branch.
> - Click **View** to see the branch's full details, or **Edit** to update its information.
>
> **▸ Edit branch information**
>
> - **Name**, **Code**, and **Zone** are fixed and can't be edited here.
> - You can update **Physical Address**, **Postal Address**, **Telephone**, and **Email**.
> - **Projects** is just a statistic — it's shown in the map popup on the public welcome page, and doesn't affect anything else in the system.
>
> **▸ Set up public contact persons**
>
> - You can assign up to **6 contact persons** for a branch, shown on profile and public-facing views.
> - These give the public a real point of contact for the branch — someone to reach out to, rather than just a generic office phone number.
> - Pick a person from the dropdown for each slot, and optionally give them a **Position/Title**, e.g. "Branch Secretary" or "Branch Chairperson".
> - Only people who already hold a role in this branch or its divisions can be selected as a contact.
> - Leave a slot as **"— None —"** if you don't need all 6.

**Page actions**

| Type | Details |
|---|---|
| Buttons | Per row: **View** only. |
| Search / filters / sort | None on screen. The controller accepts `?search=` (name, code, physical or postal address), but there is no search box. The list is sorted by name. |
| Table | Name · Code · Physical Address · Postal Address · Contact · Contacts · Divs/Projects · RC Units · Volunteers · Members · Actions. Rows with missing information are highlighted in red. |
| Bulk / export | None. |

⚠ The guide says "Click **View** … or **Edit**". The list has no Edit button; Edit is reached from the branch's **View** page.

---

### 2.7 Divisions (`divisions.index`, `/divisions`)

**Guide** (trigger **Guide**)

*Source: `resources/views/divisions/index.blade.php:12`*

> **How do I...**
>
> **▸ Search & filter divisions**
>
> - Use **Search** to find a division by name — matching text is highlighted in the results.
> - Use **Branch** to narrow the list down to one branch's divisions.
> - Click **Clear** to reset both filters.
>
> **▸ Read the divisions table**
>
> - **RC Units**, **Volunteers**, and **Members** show live counts for each division.
> - Click **View** to see the division's full details, or **Edit** to update its information.
>
> **▸ Edit division information**
>
> - You can update a division's **Physical Address**, **Postal Address**, **Telephone**, and **Email**.

**Page actions**

| Type | Details |
|---|---|
| Buttons | **Filter**. **Clear**. Per row: **View**. |
| Search | **Search** (placeholder "Search divisions..."). Matches the **division name** only. |
| Filters | **Branch**. |
| Sort | None (by branch name, then division name). |
| Bulk / export | None. |

---

### 2.8 Payment Records (`membership-payments.index`, `/membership-payments`)

**Guide** (trigger **Guide**)

*Source: `resources/views/membership-payments/index.blade.php:41`*

> **How do I...**
>
> **▸ Register a payment**
>
> - Click **Add Payments**, then find the person using **Search → Select**.
> - Volunteers (assigned to a Red Cross Unit) see **volunteer fees only**.
> - Others (NOT assigned to a Red Cross Unit) see **member fees only.**.
> - Fill in **Payment Date** and **Reference**, then click **Register Payment**.
> - 🔶 New payments go through the approval workflow before they count as active — see "Understand payment status" below.
>
> **▸ Understand payment status & approvals**
>
> - Every payment starts as **Pending** until an approver reviews it.
>
> *[Blade: `@can('approve_payments')`]*
>
> - Use the **Records / Approvals** tabs at the top to switch between your submitted payments and payments awaiting your approval.
>
> *[Blade: `@endcan`]*
>
> - If rejected, you'll get a notification, and the reason appears in your entries list.
> - While a payment is still **Pending**, you can click **Withdraw** to cancel it yourself.
> - Once approved, a payment cannot be withdrawn — contact an admin to reverse it if needed.
>
> **▸ Filter & find payments**
>
> - Click **Filter & Sort** to search by name, DB-code, or reference.
> - Narrow down by **Branch → Division → Red Cross Unit** — each level unlocks the next.
> - Use **Status** to isolate Valid, Expiring Within 30 Days, or Expired memberships.
> - Tick **Entered by me** to see payments you registered that have since been approved.
>
> **▸ Find deleted payment records**
>
> - Open **Filter & Sort → Include deleted?** and choose **Deleted** or **All**.
> - Deleted rows are highlighted in red with a **DELETED** tag next to the reference.

**Page actions**

| Type | Details |
|---|---|
| Buttons | **Add Payments** (subtitle "View Your Entries"; `add_payments`: NA, NAs, BS, BA, BAs, DF). **Add RCU Payment** (subtitle "Choose a unit"). **Filter & Sort** (shows an "Active" badge when filters are on). **Filter**. **Clear**. Per row: **View** / **View Details**. Tabs: **Records** \| **Approvals** (with a pending count; `approve_payments`: NA, BS, BA). |
| Search | **Search** (placeholder "Name, ID, Ref..."). Matches the **payment ID** (exact), the **person's ID** (exact), the **person's first / middle / last / full name** (partial), the **submitter's ID or name**, and the **reference** (partial). It does not match a full DB-code string or an organisation name. |
| Filters | **Branch** → **Division** → **Red Cross Unit**. **Membership Type**. **Status** (All Memberships / Valid / Expiring Within 30 Days / Expired). **Type** (All / Person / Organisation / Red Cross Unit). **Include deleted?** (Active / Deleted / All). **Entered by me** / **Entered by all**. |
| Sort | **Sort By**: Payment Date (Newest First) [default] / Payment Date (Oldest First) / Expiry Date (Oldest First) / Expiry Date (Newest First). |
| Bulk | On the **Approvals** tab: bulk approve (`/membership-payments/approvals/bulk-approve`). |
| Export | None. |

⚠ The guide says "search by name, **DB-code**, or reference". Only the numeric person ID works, not the full DB-code.

---

### 2.9 Volunteering Log (`activities.index`, `/activities`)

**Guide** (trigger **Guide**)

*Source: `resources/views/activities/index.blade.php:23`*

> **How do I...**
>
> **▸ Log volunteering hours**
>
> - Click **Add Volunteer Log**, then find the person using **Search → Select**.
> - Only persons assigned to a **Red Cross Unit** can appear in search results.
> - Fill in **Activity Type**, **Date**, and **Hours**, plus a Reference.
> - Click **Create Activity Log** to submit.
> - New logs go through the approval workflow before they count as active — see "Understand log status" below.
>
> **▸ Assign to a Red Cross Unit or Task Force**
>
> - If the person has a Red Cross Unit, the log is **pre-assigned to that unit by default**.
> - To assign to a **Task Force** instead, tick **Assign to Task Force** and pick one from the dropdown.
> - Tick **Do not assign this to a RC unit or task force** if neither applies.
>
> **▸ Understand log status & approvals**
>
> - Every log starts as **Pending** until an approver reviews it.
>
> *[Blade: `@can('approve_volunteering')`]*
>
> - Use the **Records / Approvals** tabs at the top to switch between your submitted logs and logs awaiting your approval.
>
> *[Blade: `@endcan`]*
>
> - If rejected, you'll get a notification, and the reason appears in your entries list.
> - While a log is still **Pending**, you can click **Withdraw** to cancel it yourself.
> - Once approved, a log cannot be withdrawn — contact an admin to reverse it if needed.
>
> **▸ Filter & find volunteering records**
>
> - Click **Filter & Sort** to search by name or reference.
> - Narrow down by **Branch → Division → Red Cross Unit** — each level unlocks the next.
> - Use **Activity Type** to isolate a specific kind of volunteering.
> - Sort by **Date**, **Hours**, or **Activity Type**, ascending or descending.
> - Tick **Entered by me** to see logs you registered that have since been approved.
>
> **▸ Find deleted volunteering records**
>
> - Open **Filter & Sort → Include deleted?** and choose **Deleted** or **All**.
> - Deleted rows are highlighted in red with a **DELETED** tag next to the reference.

**Page actions**

| Type | Details |
|---|---|
| Buttons | **Add Volunteer Log** (subtitle "View Your Entries"; `add_volunteering`: NA, NAs, BS, BA, BAs, DF, DO). **Filter & Sort**. **Filter**. **Clear**. Per row: **View** / **Show Details**. Tabs: **Records** \| **Approvals** (`approve_volunteering`: NA, BS, BA). |
| Search | **Search** (placeholder "Name, ID, Ref..."). Each word must match at least one of: the **person's** first / middle / last / full name or ID; the **submitter's** name or ID; **activity type**; **branch** name; **division** name; **Red Cross Unit or Task Force** name; **reference**; submission name; or the **log ID**. |
| Filters | **Branch** → **Division** → **Red Cross Unit**. **Activity Type**. **Include deleted?** (Active / Deleted / All). **Entered by me** / **Entered by all**. |
| Sort | **Sort By**: Activity Date (Newest First) [default] / Activity Date (Oldest First) / Hours (Low to High) / Hours (High to Low) / Activity Type (A-Z) / Activity Type (Z-A). |
| Bulk | Bulk approve on the **Approvals** tab. |
| Export | None. |

---

### 2.10 Training Records (`trainings.index`, `/trainings`)

**Guide** (trigger **Guide**)

*Source: `resources/views/trainings/index.blade.php:23`*

> **How do I...**
>
> **▸ Register a training**
>
> - Click **Register Training**, then find the person using **Search → Select**.
> - Choose a **Training Type** — types are grouped by category in the dropdown.
> - Fill in **Training Date** and **Duration**, plus Reference.
> - Click **Create Training** to submit.
> - New trainings go through the approval workflow before they count as active — see "Understand training status" below.
>
> **▸ Understand validity & expiry**
>
> - When you pick a Training Type, a hint appears showing whether it **expires after a set number of years** or has **no expiry**.
> - A training's **Status** badge reflects this: Valid, Expiring Soon, Expired, or No expiry.
> - Expiry is calculated from the **Training Date**, not the date it was registered.
>
> **▸ Understand training status & approvals**
>
> - Every training starts as **Pending** until an approver reviews it.
>
> *[Blade: `@can('approve_training')`]*
>
> - Use the **Records / Approvals** tabs at the top to switch between your submitted trainings and trainings awaiting your approval.
>
> *[Blade: `@endcan`]*
>
> - If rejected, you'll get a notification, and the reason appears in your entries list.
> - While a training is still **Pending**, you can click **Withdraw** to cancel it yourself.
> - Once approved, a training cannot be withdrawn — contact an admin to reverse it if needed.
>
> **▸ Filter & find trainings**
>
> - Click **Filter & Sort** to search by name, reference, or training type.
> - Narrow down by **Branch → Division → Red Cross Unit** — each level unlocks the next.
> - Use **Status** to isolate Valid, Expiring in 2 Weeks, Expiring in 4 Weeks, or Expired trainings.
> - Sort by **Training Date** or **Training Type**, ascending or descending.
> - Tick **Entered by me** to see trainings you registered that have since been approved.
>
> **▸ Find deleted training records**
>
> - Open **Filter & Sort → Include deleted?** and choose **Deleted** or **All**.
> - Deleted rows are highlighted in red with a **DELETED** tag next to the reference.

**Page actions**

| Type | Details |
|---|---|
| Buttons | **Register Training** (subtitle "View Your Entries"; `add_trainings`: NA, NAs, BS, BA, BAs, DF, DO). **Filter & Sort**. **Filter**. **Clear**. Per row: **View** / **View Details**. Tabs: **Records** \| **Approvals** (`approve_training`: NA, BS, BA). |
| Search | **Search** (placeholder "Name, ID, Ref, Type..."). Each word must match at least one of: the **person's** first / middle / last / full name; the **person ID** (numeric); **training type**; **branch** name; **division** name; the person's **Red Cross Unit** name; **reference**; or the generated **TRN-<id>** reference. The submitter's name is not searched. |
| Filters | **Branch** → **Division** → **Red Cross Unit**. **Training Type**. **Status** (All Statuses / Valid / Expiring in 2 Weeks / Expiring in 4 Weeks / Expired). **Include deleted?**. **Entered by me** / **Entered by all**. |
| Sort | **Sort By**: Training Date (Newest First) [default] / Training Date (Oldest First) / Training Type (A-Z) / Training Type (Z-A). |
| Bulk | Bulk approve on the **Approvals** tab. |
| Export | None. |

---

### 2.11 Donation Records (`donations.index`, `/donations`)

**Guide** (trigger **Guide**)

*Source: `resources/views/donations/index.blade.php:23`*

> **How do I...**
>
> **▸ Register a donation**
>
> - Click **Add Donation**, then find the donor using **Search → Select**.
> - Fill in **Donation Date**, and add **Reference** and **Purpose**.
> - Click **Create Donation** to submit.
> - New donations go through the approval workflow before they count as active — see "Understand donation status" below.
>
> **▸ Log a cash or in-kind donation**
>
> - By default, donations are logged as **Cash** — enter the **Amount** in Naira.
> - Tick **In-Kind Donation** to switch the form: enter the **Donation Item** and the number of items instead.
> - The donation type shown in the records list (Cash / In Kind) reflects this choice.
>
> **▸ Log an anonymous donation**
>
> - Tick **Anonymous donation** to hide the donor's DB reference in the records list.
> - Anonymity only affects what's displayed afterward, not who is on file.
>
> **▸ Understand donation status & approvals**
>
> - Every donation starts as **Pending** until an approver reviews it.
>
> *[Blade: `@can('approve_donations')`]*
>
> - Use the **Records / Approvals** tabs at the top to switch between your submitted donations and donations awaiting your approval.
>
> *[Blade: `@endcan`]*
>
> - If rejected, you'll get a notification, and the reason appears in your entries list.
> - While a donation is still **Pending**, you can click **Withdraw** to cancel it yourself.
> - Once approved, a donation cannot be withdrawn — contact an admin to reverse it if needed.
>
> **▸ Filter & find donations**
>
> - Click **Filter & Sort** to search by donor name, reference, or purpose.
> - Narrow down by **Branch → Division → Red Cross Unit** — each level unlocks the next.
> - Sort by **Date** or **Amount**, ascending or descending.
> - Tick **Entered by me** to see donations you registered that have since been approved.
>
> **▸ Find deleted donation records**
>
> - Open **Filter & Sort → Include deleted?** and choose **Deleted** or **All**.
> - Deleted rows are highlighted in red with a **DELETED** tag next to the reference.

**Page actions**

| Type | Details |
|---|---|
| Buttons | **Add Donation** (subtitle "View Your Entries"; `add_donations`: NA, NAs, BS, BA, BAs, DF). **Filter & Sort**. **Filter**. **Clear**. Per row: **View** / **View Details**. Tabs: **Records** \| **Approvals** (`approve_donations`: NA, BS, BA). |
| Search | **Search** (placeholder "Name, ID, ref, purpose…"). Each word must match at least one of: **reference**, **purpose** or **donation item** (partial); the **donation ID** or **person ID** (numeric); **branch** name; **division** name; the **donor's** first / middle / last / full name; the donor's **Red Cross Unit** name; or the word **"anonymous"** (finds anonymous donations). The organisation name is not searched. |
| Filters | **Branch** → **Division** → **Red Cross Unit**. **Type** (All / Person / Organisation). **Include deleted?**. **Entered by me** / **Entered by all**. |
| Sort | **Sort By**: Date (Newest First) [default] / Date (Oldest First) / Amount (Highest First) / Amount (Lowest First). |
| Bulk | Bulk approve on the **Approvals** tab. |
| Export | None. |

---

### 2.12 Campaign Management (`campaigns.admin.proposed`, `/campaigns/admin/proposed`)

National only. It is included for completeness, because branch trainees will see its effects.

**Guide** (trigger **Guide**)

*Source: `resources/views/campaigns/admin/index.blade.php:12`*

> **How do I...**
>
> **▸ Where campaigns come from**
>
> - Campaigns aren't created here — they're built using the **Campaign Wizard**, starting from the Users index page.
> - This page is for **approving and sending** campaigns that have already been proposed.
> - New campaigns arrive here in the **Proposed** tab, waiting for review.
>
> **▸ Understand campaign status tabs**
>
> - **Proposed** — newly created campaigns awaiting approval.
> - **Approved/Queued** — approved and waiting to be sent.
> - **Sending** — currently going out to recipients.
> - **Sent** — fully delivered, with sent/failed counts shown per campaign.
> - **Cancelled** / **Rejected** — stopped before completion, or turned down at proposal stage.
> - The count badge on each tab updates live as campaigns move through the pipeline.
>
> **▸ Approve, reject, queue & send a campaign**
>
> - In the **Proposed** tab, click **Review** on a campaign.
> - Read the review page CAREFULLY — check the channel, audience, and recipient count before deciding.
> - Click **Approve** or **Reject**. If rejecting, provide feedback to the sender.
> - Once approved, the campaign moves to the **Approved/Queued** tab.
> - From there, the sequence is: click **Queue** → **Build** → **Send**.
> - Once sent, click **Monitor** to track delivery progress.
>
> **▸ Read the Messages Pipeline summary**
>
> - This bar at the top shows real-time counts across **all** campaigns, not just the active tab.
> - **Queued** — messages waiting to be sent.
> - **Sending** — messages actively being delivered right now.
> - **Failed** — messages that didn't go through.
> - **Sent today** — total successfully delivered since midnight.
> - Use this to spot a stuck queue or a spike in failures before checking individual campaigns.
>
> **▸ Search & find campaigns**
>
> - Use the search box to find a campaign by **title** or **campaign ID**.
> - Search stays scoped to whichever status tab you're currently on.
> - Click **Clear** to reset the search and see all campaigns in that tab again.

**Page actions**

| Type | Details |
|---|---|
| Tabs | **Proposed**, **Approved/Queued**, **Sending**, **Sent**, **Cancelled**, **Rejected** (each with a live count). |
| Search | Search box (placeholder "Search title or campaign id…"), then **Search**. Matches the **campaign title** (partial) and the **campaign ID** (exact, digits only), within the current tab. |
| Filters | **Origin** (All origins / National / each branch). |
| Row actions | **Review**, **Monitor**; **Queue** (approved campaigns); **Build**, then start sending (queued campaigns). |
| Bulk / export | None. |

⚠ The guide says campaigns start "from the **Users** index page". In the menu that page is called **Persons**.

---

### 2.13 <CODE> Campaigns (`campaigns.mine`, `/campaigns/mine`)

**Guide** (trigger **Guide**)

*Source: `resources/views/campaigns/mine/index.blade.php:15`*

> **How do I...**
>
> **▸ Start a new campaign**
>
> - Campaigns aren't created here — build one using the **Campaign Wizard**, started from the **Users** or **Organisations** index page.
> - This page is where you come back afterward to **monitor progress, finish drafts, and manage** what you've already started or sent.
>
> **▸ Understand the tabs**
>
> - **Drafts** — started in the wizard but not yet submitted for approval.
> - **Submitted** — sent for approval and awaiting a decision.
> - **Rejected** — turned down, with feedback available on the campaign.
> - **Approved/Sending** — approved and currently being queued, built, or sent.
> - **Sent** — fully delivered, with sent/failed counts shown per campaign.
>
> **▸ Finish a draft or fix a rejected campaign**
>
> - On a **Draft** campaign, click **Continue editing** to pick up where you left off in the wizard.
> - On a **Rejected** campaign, click **Edit campaign** to make changes and resubmit.
> - Click **View** on any campaign to see its full details.
>
> **▸ Delete a campaign**
>
> - The **Delete** button only appears on **Draft** and **Rejected** campaigns.
> - Once a campaign is submitted, approved, sending, or sent, it can no longer be deleted from here.
> - Deleting is permanent and cannot be undone.
>
> **▸ Track delivery progress**
>
> - Each card shows a live delivery line, e.g. **Queued**, **Sending…**, or **X / Y sent**.
> - If any messages failed, the failed count shows in red next to the sent count.
> - Click **View** for full details on a specific campaign's delivery.

**Page actions**

| Type | Details |
|---|---|
| Tabs | **Drafts**, **Submitted**, **Rejected**, **Approved/Sending**, **Sent**. |
| Card actions | **View**. **Continue editing** (Drafts). **Edit campaign** (Rejected). **Delete** (Draft and Rejected only; permanent). |
| Search / filters / sort / bulk / export | None. |

⚠ The guide says campaigns are started "from the **Users** or **Organisations** index page". The menu calls the first page **Persons**.

---

### 2.14 ID Cards (`id-cards.prepare-bulk-print`, `/id-cards/prepare-bulk-print`)

**Guide** (trigger **Guide**). Users without `print_idcards` see the "Prepare cards for printing" section. Users who can print see the printing sections instead.

*Source: `resources/views/id-cards/prepare-bulk-print.blade.php:8`*

> **How do I...**
>
> *[Blade: `@cannot('print_idcards')`]*
>
> **▸ Prepare cards for printing**
>
> - Printing only happens at **HQ** — this page is where you check whether your people's cards are **ready** for it.
> - A card with a **red border** is missing something — usually a photo, signature, or National ID number.
> - A card with a **blue border** is complete and ready to print.
> - Use the filters below to narrow down to your **Division** or **Red Cross Unit**, then fix any missing data before requesting a print run.
> - Only ask HQ to print for a Division or RC Unit once **all its cards** are ready — this avoids partial, repeated print requests.
>
> *[Blade: `@endcannot`]*
>
> **▸ Search & filter cards**
>
> - Use **Search** to find one specific person by name or User ID — handy if you just need to print for a single card.
> - Use **Branch → Division → Red Cross Unit** to bulk-select an entire group at once.
> - **Expires In** narrows the list to cards expiring within a set number of months.
> - Tick **Printable cards only** to hide anyone missing photo, signature, or National ID.
> - Tick **ID paid but not printed** to find people who've paid for a card but don't have one yet.
>
> *[Blade: `@can('print_idcards')`]*
>
> **▸ Select cards for printing**
>
> - Tick the checkbox on a card to select it — only cards that are **complete** (photo, signature, National ID, membership) can be selected.
> - Click **Select All** to select every printable card on the page, or **Deselect All** to clear your selection.
> - The counter at the top shows how many users are currently selected.
>
> **▸ Set the validity period**
>
> - Each card's **Validity (months)** is pre-filled from the fee paid — 12 for a 1-year fee, 36 for a 3-year fee — or 12 if there's no payment.
> - Adjust the field on an individual card to override it; leaving it blank uses that same default.
> - The **New ID expiry** date updates live as you change the validity.
>
> **▸ Read the timeline graph**
>
> - The **top bar (Memb)** shows membership validity — green if valid, orange if expiring soon, red if expired.
> - The **bottom bar (ID)** shows the current ID card's validity in blue, or orange if it's expired.
> - The dashed red line marks **today**; the purple triangle marks where the new expiry will land based on your chosen validity.
>
> **▸ Print & record cards**
>
> - Once you've selected your cards, click **Print Selected** — this opens the print job in a new tab.
> - 🔶 **Do not leave this page** after printing — you still need to confirm it worked.
> - Click **Mark as Printed** to record the print in the database, along with each card's new expiry date.
> - Without this step, the system won't know the cards were printed, even if they physically were.
>
> **▸ Check print history & fix mistakes**
>
> - Click **View Print History** to see everything that's been marked as printed.
> - If a card was **accidentally marked as printed**, you can correct that mistake from this history page.
>
> *[Blade: `@endcan`]*

**Page actions**

| Type | Details |
|---|---|
| Buttons | **Filter**. **View Print History** (goes to `/id-cards/prints-report`). Per card: **View**. If you can print (NA, or NAs with the direct permission): **Select All**, **Deselect All**, **Print Selected**, **Mark as Printed**, a **Validity (months):** field per card, and **✕ Reject signature** / **Undo** on a card's signature. |
| Search | **Search** (placeholder "Name, User ID..."). Matches **first / last name** (partial) and the **user ID** (exact, numeric). |
| Filters | **Branch** → **Division** → **Red Cross Unit**. **Expires In** (Any Time / N Months). **Printable cards only**. **ID paid but not printed**. |
| Sort | None. |
| Bulk | Select → **Print Selected** → **Mark as Printed**. |
| Export | None (printing opens a print view in a new tab). |

---

### 2.15 Certificates (`certificates.index`, `/certificates`)

**Guide** (trigger **Guide**). The whole section from "Select & deselect certificates" onward is only shown to users with `print_certificates` (NA, BS, BA, and NAs with the direct permission).

*Source: `resources/views/certificates/index.blade.php:14`*

> **How do I...**
>
> **▸ Understand certificate types**
>
> - Training certificates come in two kinds: **Attendance** confirms someone was present.
> - **Competence** is a certification — it confirms they've been assessed and met the standard.
> - Membership, Volunteering, and Donation certificates are separate types, selected from the same **Certificate Type** dropdown.
>
> **▸ Search & filter certificates**
>
> - Choose a **Certificate Type** first — Membership, Training, Volunteering, or Donation.
> - Use **Search User** to find one specific person by name — handy if you just want to print for a single person.
> - Use **Branch → Division → Red Cross Unit** to bulk-select an entire group at once.
> - Use **Certificate Status** to isolate Already Printed or Never Printed records.
>
> *[Blade: `@can('print_certificates')`]*
>
> **▸ Select & deselect certificates**
>
> - Tick the checkbox on a card to select it for printing.
> - Click **Select All** to select every card on the page, or **Deselect All** to clear your selection.
> - The counter at the top shows how many are currently selected.
> - Some certificates show **"Printed at HQ"** instead of a checkbox — these can only be printed centrally and aren't selectable here.
>
> **▸ Set up signatures**
>
> - You can preset the **Title** and **Name** for up to two signatures that appear on the printed certificate.
> - Choose **Line only** if you want a blank signature line with no title, or leave Signature 2 as **No second signature** if only one is needed.
> - **Pre-printed Signature** (an actual signature image) is only available at **HQ** — branch-level users only see the Title and Name fields.
>
> **▸ Print & record certificates**
>
> - You can print on **blank paper** or **pre-printed paper**: choose **Print for pre-printed paper** if you're using paper with the logo/frame already on it, or **Print with logo & frame** for plain paper.
> - For pre-printed paper, use the **layout editor** (top right) to adjust text position — your setting is remembered for next time.
> - 🔶 **Do not leave this page** after printing — you still need to confirm it worked.
> - Click **Mark as Printed** to record the print in the database.
> - Without this step, the system won't know the certificates were printed, even if they physically were.
>
> **▸ Check print history & fix mistakes**
>
> - Click **View Print History** to see everything that's been marked as printed.
> - If a certificate was **accidentally marked as printed**, you can correct that mistake from this history page.
>
> *[Blade: `@endcan`]*

**Page actions**

| Type | Details |
|---|---|
| Buttons | **Certificates for organisations**. **Certificates for Red Cross Units**. **Filter**. **Clear**. **View Print History** (goes to `/certificates/prints-report`). If you can print: **Select All**, **Deselect All**, **Print for pre-printed paper**, **Print with logo & frame**, **Mark as Printed**, and the layout editor (top right). |
| Search | **Search User** (placeholder "Name..."). Matches the **user ID** (exact) and **first / last / "first last" name** (partial). |
| Filters | **Certificate Type** (Membership / Training (attendance or competence) / Volunteering / Donation). **Branch** → **Division** → **Red Cross Unit**. **Training Type**. **Certificate Status** (All / Already printed / Never printed). |
| Signature set-up | Signature 1 and Signature 2: **Title** (— Select title — / Line only (no title) / titles; Signature 2 also has "— No second signature —"), **Name**, and **Pre-printed Signature** image (HQ only). |
| Bulk | Tick cards, then print, then **Mark as Printed**. |
| Export | None. |

⚠ The guide says **Search User** finds "one specific person by name". It also matches the numeric user ID.

---

### 2.16 Archive Tool (`dormant-users.index`, `/dormant-users`)

**Guide** (trigger **Guide**)

*Source: `resources/views/dormant-users/index.blade.php:7`*

> **How do I...**
>
> **▸ Understand Dormant vs Pending Engagement**
>
> - **Dormant** — was active at some point, but has had no recorded activity for the selected threshold.
> - **Pending Engagement** — never engaged at all since registering.
> - Active members and admin users are automatically excluded from both lists.
>
> **▸ Set the inactivity threshold**
>
> - Use **Inactivity Threshold** to choose how many years of no activity qualifies someone as dormant.
> - National-level admins can also filter by **Branch**; branch-level admins are automatically scoped to their own branch.
> - Click **Clear** to reset all filters back to default.
>
> **▸ Read the activity & status columns**
>
> - Use the table columns below to quickly evaluate whether a user should be archived.
> - **Activity** shows last activity, registration date, and last login — a green highlight on last login means they logged in within the past year.
> - **Campaigns** lists every campaign message this person has been sent, with how long ago it went out.
> - **Status** shows donation, training, and first-aid badges at a glance.
> - **Opt-outs** flags anyone who has opted out of email or SMS, and when.
> - Toggle **Show profile photos** at the top to display or hide photos in the list — this preference is remembered on your browser.
>
> **▸ Select & archive users**
>
> - Tick the checkbox in the **Actions** column to select a person, or use **Select All** / **Deselect All**.
> - Click **View** on any person to double-check their record before archiving.
> - Once you're ready, click **Archive Selected Users** and confirm.
> - Archiving can be reversed individually from a person's profile if needed.

**Page actions**

| Type | Details |
|---|---|
| Buttons | **Filter**. **Clear**. **Select All**. **Deselect All**. **Archive Selected Users** (asks for confirmation). Per row: **View**, plus a checkbox. |
| Search | None. |
| Filters | **User Type** (radio: **Dormant (was active, now inactive)** / **Pending engagement (never engaged)**). **Inactivity Threshold** (N years). **Branch** (national only). |
| Display | **Show profile photos** toggle. Columns: Person · Location/Contact · Activity · Campaigns · Status · Opt-outs · Actions. |
| Bulk | **Archive Selected Users**. |
| Export | None. |

---

### 2.17 Authorizations (`users.roles.edit`, `/users/roles`)

**Guide** (trigger **Guide**)

*Source: `resources/views/users/edit-roles.blade.php:25`*

> **How do I...**
>
> **▸ Understand system governance**
>
> - The **NRCS President and Secretary General** hold overall super-admin authority in this system, using their official NRCS email accounts.
> - Day-to-day user authorization is normally handled by the appointed **National DB Administrator(s)** and **Branch DB Administrator(s)**.
> - This is an important responsibility — handle it with care.
>
> **▸ Authorize a branch-level role**
>
> - **Branch Secretary** — branch administration.
> - **Branch DB Administrator** — in the database, holds the same authority as Branch Secretary.
> - **Branch DB Assistant** — branch data entry.
> - **Division Assistant — Finance** — handles Payments, Donations, Trainings & Volunteering.
> - **Division Assistant — Operations** — handles Trainings & Volunteering.
> - A **Division Assistant** sees only their own division.
> - Search for the person, then use the **Assign Role** dropdown to give — or remove — a role.
> - Select **"-- No Role --"** to remove authorization entirely.
>
> **▸ Authorize a national-level role**
>
> - **National DB Administrator** — authorizes & oversees the whole system.
> - **National DB Assistant** — national data entry. Can be given any combination of extra **Direct Permissions**: Approve Campaign Requests, Print Certificates, Print ID Cards.
> - The Direct Permissions checkboxes only become enabled once **National DB Assistant** is selected as the role.
> - **Observer** — read-only reports; full access to statistics, but cannot change anything in the database.
>
> **▸ Search for a person & assign a role**
>
> - Use **Search for a User** to find the person by name, email, or ID.
> - Their **current role** is shown at the top of the form once selected.
> - Choose a new role from **Assign Role** and click **Update Roles & Permissions** to save.
> - Click **Clear / Search for another user** to start over with someone else.
>
> **▸ Review Users by Role**
>
> - The table below shows everyone currently authorized, grouped by role.
> - Any extra **Direct Permissions** a person holds are shown as red tags next to their name.
> - Click **Edit** on any row to jump straight to that person's role form.
> - National DB Administrators can only be edited by a super-admin.

**Page actions**

| Type | Details |
|---|---|
| Search | **Search for a User** (type-ahead, placeholder "Start typing to search by name, email, or ID..."). It matches **first / last / full name** (including middle name), **email** (partial) and the **user ID** (exact), and returns 10 results at most. It excludes super-admins and yourself. **Branch users only find people in their own branch, and never anyone holding a national role, a Branch Secretary role or a Branch DB Administrator role.** |
| Form | Current role. **Assign Role** dropdown (**-- No Role --** plus the roles *you* are allowed to assign; labels are auto-title-cased, e.g. "Branch Db Assistant"). Direct-permission checkboxes (national DB assistant only). **Update Roles & Permissions**. **Clear / Search for another user**. |
| Table | **Review Users by Role**: Name & Permissions · Branch (national) / Division (branch) · DB-Code · Actions (**Edit**). |
| Bulk / export | None. |

⚠ **Important for this audience.** The guide section "Authorize a branch-level role" lists Branch Secretary and Branch DB Administrator among the roles to assign. For BS and BA, the **Assign Role** dropdown only offers **Branch DB Assistant, Division DB Assistant — Finance and Division DB Assistant — Operations**. BS and BA are appointed by the National DB Administrator (`PermissionsTableSeeder`, `User::getAssignableRoles()`).

---

### 2.18 Settings (`admin.settings.index`, `/admin/settings`)

National only (NA). Your trainees won't see this page.

**Guide** (trigger **Guide**)

*Source: `resources/views/settings/index.blade.php:18`*

> **How do I...**
>
> These settings affect the **whole organisation**. Change with care.
>
> **▸ National Database Settings**
>
> - **Months of inactivity before dormant** — controls how long a user must be inactive before becoming Dormant.
> - **Site Motto** — displayed in the footer and on public pages.
> - **Social Share Snippet** — HTML snippet used for social sharing meta tags.
>
> **▸ Campaign Settings**
>
> - These control how campaigns are sent, **system-wide**.
> - **Allowed link domains** — a comma-separated list of domains allowed in campaign content links, e.g. example.org, example.com. Case-insensitive.
> - Keep this list deliberate — it's a safeguard on what links can go out to members and volunteers.
> - **Daily email sending cap** — maximum campaign emails sent per day before pausing.
> - **Daily SMS sending cap** — maximum campaign SMS sent per day before pausing.
>
> **▸ Signatures & Documents**
>
> - **ID Card Signature** — upload a PNG with a transparent background. Used as the Secretary General's signature on printed ID cards. The filename is fixed — uploading a new file replaces the existing one.
> - **Signature Images** — used on certificates, as pre-printed signatures to save time. PNG only, transparent background. Use a descriptive filename, like charles-smith-signature.png.
> - **Signature Titles** — a list of titles shown on certificates, for example Branch Chairman or Branch Health Coordinator.
>
> **▸ Operational Settings**
>
> - **Membership Fees** — change fee amounts, or add new membership fee types.
> - **Training Types** — manage the kinds of trainings, and mark whether certificates for a type are HQ-print only.
>
> **▸ Campaign Purposes**
>
> - These are the default message templates behind every campaign.
> - Each purpose — like **Membership Pre-Expiry Notice**, **Training Invitation**, or **Welcome & Onboarding** — has a default email subject, email body, and SMS body.
> - These are pre-filled when a branch selects a purpose in the campaign wizard — saving them from writing a message from scratch.

**Page actions:** **Open National Database Settings**, **ID Card Signature**, **Signature Images**, **Signature Titles**, **Membership Fees**, **Training Types**, **Campaign Purposes**, **Task Force Types**. There is no search, filter, bulk action or export.

---

### 2.19 Log (`logs.index`, `/logs`)

**Guide** (trigger **Guide**)

*Source: `resources/views/logs/index.blade.php:9`*

> **How do I...**
>
> **▸ Understand what the log tracks**
>
> - This log focuses on **deletions** and **administrative changes** — not on ordinary day-to-day activity.
> - Every deletion is kept — nothing disappears silently.
> - It records **admin-initiated** branch/division moves — when an administrator moves someone. Self-service moves a person makes to their own profile are not logged here.
> - It records **role and special permission changes** — who was assigned or removed from a role, and which special permissions were granted or revoked.
> - It records **National Settings changes** — including settings values, signatures, membership fees, training types, campaign purposes, and task force types.
> - Approved records are **not** logged separately — the approval itself (who, when) is already stored on the record, e.g. the payment, donation, activity, or training.
>
> **▸ Search & filter the log**
>
> - **Search** matches description, action, or subject type/ID — and if you type a number, it also searches for that **User ID** as the actor, submitter, or entered-by person.
> - Use **Action** to isolate a specific type of event, e.g. payment_deleted, member_branch_division_changed, user_roles_updated, or setting_changed.
> - Narrow down by **Branch** and/or **Division** — national admins see both, branch admins see Division only (scoped to their own branch).
> - Use **From date** / **To date** to bound the results to a specific period.
>
> **▸ Read the log table**
>
> - **Actor** is who performed the action — shown as "System / N/A" if no user is attached.
> - **User** shows the person the record relates to, e.g. the member on a payment, donation, activity, or training.
> - **Submitted by** shows who originally entered that record, where applicable.
> - **Subject** shows the record type and ID affected, e.g. MembershipPayment #123.
> - The **description** spells out what changed where possible — e.g. old and new role, old and new fee amount, or which fields were edited.
>
> **▸ Track branch/division migrations**
>
> - Search or filter by the action member_branch_division_changed to see who an administrator has moved between branches or divisions.
> - This only covers moves made by an admin — a person changing their own branch/division via self-service is not recorded here, by design.
>
> **▸ Roles, permissions & settings**
>
> - user_roles_updated — shows the new role, the previous role, and any special permissions granted or revoked.
> - setting_changed — any National Database Setting, e.g. dormancy period, site motto, or campaign sending caps.
> - Signature, Membership Fee, Training Type, Campaign Purpose, and Task Force Type changes are logged under their own matching action names — use the **Action** filter to find them.
> - These are the most sensitive entries in the log — they show changes to who can do what, and how the system behaves for everyone.

**Page actions**

| Type | Details |
|---|---|
| Buttons | **Apply filters**. **Reset**. |
| Search | Labelled **Search (description, action, subject type/id, or user ID)**. Matches **description, action, subject type, subject ID** (partial). If the text is numeric, it also matches the **user ID** as actor, as the subject, or inside the before/after snapshots. |
| Filters | **Branch** (national only). **Division**. **Action** (All / each action name). **From date**. **To date**. |
| Table | Time · Actor · User · Submitted by · Action · Subject · Branch / Division · Description. |
| Sort / bulk / export | None. |

Note: the menu says **Log**; the page heading says **Audit Log**.

---

### 2.20 Membership Fee Types (`membership-fees.index`, `/membership-fees`)

Reached via Settings → **Membership Fees**. Needs password confirmation.

**Guide** (trigger **Guide**)

*Source: `resources/views/membership-fees/index.blade.php:9`*

> **Membership Fee Guidelines**
>
> **▸ General information**
>
> - On this page you can **update a fee amount** or **deactivate a fee**.
> - Updating the amount does **not** edit the existing fee — it creates a new fee record and automatically deactivates the old one.
> - This keeps past and ongoing payments correctly linked to the fee amount that was active when they were made.
> - For anything else — like adding a brand-new fee category — use **Add New Membership Category** on this page.
>
> **▸ Create a new fee type**
>
> - Click **Add New Membership Category**.
> - Fill in the name, amount, ID card fee, and validity period.
> - Choose whether it's for **Individuals**, **Organizations** or **Red Cross Units**, and whether it's a **Volunteer Fee**.
>
> **▸ Change the amount of an existing fee**
>
> - Click **Edit** on the fee, and change the amount and/or ID card fee.
> - Click **Update Membership Fee**.
> - This automatically creates a new fee record with the new amount, and deactivates the old one — you'll see the old fee reappear as inactive.
> - Existing members already on the old fee keep their original amount — this only affects new and future payments.
>
> **▸ Remove a membership fee type**
>
> - Click **Edit** on the fee.
> - Untick **Status Active**.
> - Click **Update Membership Fee**.
> - The fee is deactivated, not deleted — it stays linked to any past payments, and the **Edit** button disappears from the list since inactive fees can no longer be edited.
>
> **▸ Volunteer & Organization fees**
>
> - **Type** shows whether a fee applies to individuals or organizations.
> - **Volunteer Fee** marks a fee as the one used specifically for Vol. & Membeers.

**Page actions:** **Add New Membership Category**. Per row: **Edit** (active fees only). Columns: Name · Amount · ID Card Fee · Validity · Type · Status · Vol. & Member Fee · Actions. There is no search, filter, sort, bulk action or export.

⚠ Typo in the guide: "Vol. & Membeers". The guide calls the flag **Volunteer Fee**; the column is headed **Vol. & Member Fee**.

---

### 2.21 Training Types (`training-types.index`, `/training-types`)

Reached via Settings → **Training Types**. Needs password confirmation.

**Guide** (trigger **Guide**)

*Source: `resources/views/training-types/index.blade.php:11`*

> **Training Type Guidelines**
>
> **▸ General information**
>
> - A **training type** defines a kind of training that can be recorded against a person, e.g. First Aid, Disaster Management, or Leadership.
> - Each type can have a **validity period** — after this many years, a completed training expires.
> - Click **Add New Training Type** to create one.
>
> **▸ Editing an existing type**
>
> - Only correct small errors here — spelling or capitalization, e.g. Basic frst aid → Basic First Aid.
> - Don't change what a type actually means, e.g. Community First Aid → Child Protection — this would misrepresent trainings already recorded under it.
> - If you need a genuinely different training type, create a new one instead of repurposing an existing one.
>
> **▸ Deactivate a training type**
>
> - Click **Edit**, untick **Active**, then click **Update Training Type**.
> - This removes it from the list used when recording new trainings — past trainings recorded under it are unaffected.
>
> **▸ First Aid & HQ Only flags**
>
> - **First Aid training** marks this type as a First Aid course — used to identify first-aid-related trainings across filters and reports.
> - **Certificate can only be issued by HQ** restricts printing this type's certificate to headquarters — branches won't see a print option for it.

**Page actions:** **Add New Training Type**. Per row: **Edit**. Columns: Name · Group · Validity · Status · HQ Only · First Aid · Trainings · Actions. There is no search, filter, sort, bulk action or export.

---

### 2.22 Welcome Campaign Planner (`reports.campaign-planning.welcome`)

**Guide** (trigger **What to do**)

*Source: `resources/views/reports/campaign-planning/welcome.blade.php:11`*

> **Pending Engagement**
>
> Your mission:
>
> Get them engaged!
>
> **▸ Plan Campaigns**
>
> - Make a campaign. Send a welcome message — one clear message with a simple next step.
> - Design a message:
>   - For aspiring **members**: Instruct how to make payment.
>   - For aspiring **volunteers**: Instruct how to call the branch. Give telephone number to contact person, and times to call (call window).
> - Use **Persons → Campaign filter wizard → Welcome newly registered persons** to build the list → Optional: Choose segments: Area · Gender · Age → **Make campaign from filter** and follow the wizard.
> - For members: Once payment is recorded and approved, the member returns to Active automatically.
> - For volunteers: When they call, find the person and assign to a Red Cross Unit: **Edit → Select Red Cross Unit → Update Person**
>
> **▸ Segment your audience**
>
> If the total number of pending persons is manageable, one campaign may be enough. If it is large, split the audience into segments — one campaign per segment. Use the filters on this page to explore the numbers and develop a strategy.
>
> Each segment becomes one campaign
>
> Strategy A — by geography
>
> Roll out division by division. Finish one area before moving to the next. Useful when local coordinators handle follow-up calls.
>
> 1. Division A — all pending
> 2. Division B — all pending
> 3. Division C — all pending
> 4. Remaining branches — clean-up
>
> Strategy B — by demographic
>
> Target by gender and age group. Useful when the message or channel differs by audience.
>
> 3. Division A · Women · Age 15–25
> 4. Division A · Women · Age 26+
> 1. Division A · Men · Age 15–25
> 2. Division A · Men · Age 26+
>
> There is no single right strategy — experiment with the filters, look at the numbers, and decide what fits your situation.
>
> **▸ Who is behind the numbers?**
>
> - The planning tool shows only statistics. If you want to see exactly the individuals behind the numbers, do this:
> - Go to **Persons → Show more filters** → set **Lifecycle Status:** Pending engagement → set **Wants to contribute as** Member or Volunteer → click **Filter**

**Page actions**
- **Contribution preference** toggle: **Both** / **Volunteer** / **Member**.
- Filters: **Registered within** (All time / Last 30 / 60 / 90 / 180 days), **Gender** (Both genders / Male / Female) and **Age group**.
- **Filter** and **Clear**.
- Click an area row to drill down (branch → division → unit).
- Columns: Total pending · Not yet contacted *(freshest targets)* · Contacted once · Contacted 2+ *(de-prioritise)* · Avg days since registered.
- No search box or export.

⚠ The guide says "**Persons → Show more filters**". The live button is **Show all filters**. In "Strategy B" the numbering reads 3, 4, 1, 2 in the source. This is kept verbatim above; it may be deliberate, but check it before printing.

---

### 2.23 Re-engage Dormant Volunteers (`reports.campaign-planning.dormant`)

**Guide** (trigger **What to do**)

*Source: `resources/views/reports/campaign-planning/dormant.blade.php:13`*

> **Dormant Persons**
>
> Your mission:
>
> Bring them back!
>
> **▸ Plan Campaign**
>
> - Design a reactivation message — for example, an invitation to an upcoming activity or training.
> - Use **Persons → Campaign filter wizard → Re-engage dormant volunteers** to build the list → Optional: Choose segments: Area · Gender · Age → **Make campaign from filter** and follow the wizard.
> - Once a new record (volunteering, training, or payment) is entered and approved, they return to Active automatically.
>
> **▸ Segment your audience**
>
> If the total number of pending persons is manageable, one campaign may be enough. If it is large, split the audience into segments — one campaign per segment. Use the filters on this page to explore the numbers and develop a strategy.
>
> Each segment becomes one campaign
>
> Strategy A — by geography
>
> Roll out division by division. Finish one area before moving to the next. Useful when local coordinators handle follow-up calls.
>
> 1. Division A — all pending
> 2. Division B — all pending
> 3. Division C — all pending
> 4. Remaining branches — clean-up
>
> Strategy B — by demographic
>
> Target by gender and age group. Useful when the message or channel differs by audience.
>
> 3. Division A · Women · Age 15–25
> 4. Division A · Women · Age 26+
> 1. Division A · Men · Age 15–25
> 2. Division A · Men · Age 26+
>
> There is no single right strategy — experiment with the filters, look at the numbers, and decide what fits your situation.
>
> **▸ Who is behind the numbers?**
>
> - The planning tool shows only statistics. If you want to see exactly the individuals behind the numbers, do this:
> - Go to **Persons → Show more filters** → set **Lifecycle Status: Dormant** → Member/Volunteer: Volunteers → click **Filter**

**Page actions**
- Filters: **Gender** and **Age group**.
- **Filter** and **Clear**.
- Drill-down by area.
- Columns: Total dormant · Not yet contacted · Contacted once · Contacted 2+ · Avg days since last activity.
- No search box or export.

⚠ The guide says "Show more filters". The live button is **Show all filters**. Strategy B has the same 3, 4, 1, 2 numbering. The segmenting text also says "total number of **pending** persons", copied from the Welcome planner.

---

### 2.24 Expiring Membership Campaign Planner (`reports.campaign-planning.expiring-membership`)

**Guide** (trigger **What to do**)

*Source: `resources/views/reports/campaign-planning/expiring-membership.blade.php:13`*

> **Expiring Memberships**
>
> Your mission:
>
> Get them to renew!
>
> **▸ Plan Campaign**
>
> - Send a renewal reminder — one clear message with a simple next step.
> - Prioritise persons who have **not yet been contacted** (green column). They are the freshest targets.
> - Design your message:
>   - For **members**: remind them of the renewal deadline and instruct how to pay.
>   - For **volunteers**: remind them to contact their branch to renew their volunteer fee.
> - Use **Persons → Campaign filter wizard → Remind about expiring membership** to build the list → Optional: Choose segments: Area · Gender · Age → **Make campaign from filter** and follow the wizard.
>
> **▸ Segment your audience**
>
> If the total number of expiring memberships is manageable, one campaign may be enough. If it is large, split the audience into segments — one campaign per segment. Use the filters on this page to explore the numbers and develop a strategy.
>
> Each segment becomes one campaign
>
> Strategy A — by geography
>
> Roll out division by division. Useful when local coordinators handle follow-up.
>
> 1. Division A — all expiring
> 2. Division B — all expiring
> 3. Remaining branches — clean-up
>
> Strategy B — by urgency
>
> Contact those closest to expiry first, then follow up with the rest.
>
> 1. Expiring within 14 days
> 2. Expiring within 28 days
> 3. Already expired — last chance
>
> There is no single right strategy — experiment with the filters, look at the numbers, and decide what fits your situation.
>
> **▸ Who is behind the numbers?**
>
> - This planning tool shows only statistics. To see the individuals behind the numbers:
> - Go to **Persons → Show more filters** → set **Payments** to the relevant window → click **Filter**

**Page actions**
- Filters: **Supporting Members/Volunteers** (All / Supporting Members / Volunteers) and **Time to expiry** (Expiring within 28 days / Expiring within 14 days / Expired).
- **Filter** and **Clear**.
- **All Branches** (back to the national view) and drill-down by area.
- No search box or export.

⚠ The guide says "Show more filters". The live button is **Show all filters**.

---

### 2.25 Training Statistics (`reports.trainings.stats`)

**Guide** (trigger **What to do**)

*Source: `resources/views/reports/trainings/stats.blade.php:10`*

> **Training Statistics**
>
> Your mission:
>
> Keep everyone trained and certified!
>
> **▸ Training Coverage**
>
> - See how many volunteers (or, with the "All" toggle, volunteers and members) have completed each training type — and how many haven't.
> - Click a training type to drill down by branch → division → Red Cross Unit, and find exactly where the gaps are.
> - Use this to plan targeted training sessions in the areas with low coverage.
> - First Aid trainings are grouped separately from other trainings, since they're the most safety-critical.
> - Use **Persons → Campaign filter wizard → Invite to upcoming training** to build the list → Optional: Choose segments: Area · Gender · Age → **Make campaign from filter** and follow the wizard.
>
> **▸ Expiry Timeline**
>
> - See how many certifications are expiring soon (or have already expired) for each training type, month by month.
> - Trainings expiring in the next 1–2 months need urgent attention — plan refresher sessions before the deadline.
> - Click a training type to drill down by branch → division → Red Cross Unit and target the refresher campaign geographically.
> - Trainings that already expired months ago are shown in grey — a backlog worth clearing.
> - Use **Persons → Campaign filter wizard → Remind about expiring training certification** to build the list → Optional: Choose segments: Area · Gender · Age → **Make campaign from filter** and follow the wizard.
> - **💡 Tip:** For a more detailed picture, go to **Donation Records**, filter by training type and expiry date.
>
> **▸ First Aid Staleness**
>
> - This tab calculates the time since each person's most recent First Aid training of any kind — not a specific course, but whichever First Aid training they last completed.
> - See how long it's been since each person's last First Aid training, grouped into time bands (12–23 months, 24–35 months, and so on).
> - The further right the count, the staler the skill — someone at "≥ 60m" hasn't had a refresher in five years or more.
> - Use the Volunteers/All toggle to decide whether you're tracking active volunteers only, or the whole membership base.
> - Drill down by branch → division → Red Cross Unit to find where refresher training is most overdue.
> - Use **Persons → Campaign filter wizard → Refresh first-aid training** to build the list → Optional: Choose segments: Area · Gender · Age → **Make campaign from filter** and follow the wizard.
>
> **▸ Certificates**
>
> - See how many attendance and competence certificates have been printed for each training type — and how many trainings still have none.
> - Use the Date From / Date To fields (top right of this tab) to narrow certificate counts to a specific period, such as a reporting quarter.
> - Click a training type to drill down by branch → division → Red Cross Unit and follow up where certificates are still missing.
> - A high "No Certificate" count usually means the training happened but the paperwork hasn't caught up yet — a good administrative follow-up task.

**Page actions**
- Tabs: **Training Coverage**, **Expiry Timeline**, **First Aid Staleness**, **Certificates**.
- **Volunteers / All** toggle.
- Filters: **Branch** → **Division** → **Red Cross Unit**, then **Filter**.
- On the Certificates tab: **Date From** / **Date To**, then **Apply**.
- Click a training type to drill down.
- No search box or export.

⚠ Guide errors:
- The tip says "go to **Donation Records**, filter by training type and expiry date". It should say **Training Records**.
- The wizard goal is written "**Refresh first-aid training**". The goal is actually named **Refresh stale first-aid training**.

---

## 4. Important pages with **no** Guide pop-up

| Page | Route / URL | Heading | Who uses it | Why a guide would help |
|---|---|---|---|---|
| **Approvals** tab (payments / volunteering / trainings / donations) | `membership-payments.approvals` `/membership-payments/approvals`; `activities.approvals`; `trainings.approvals`; `donations.approvals` | "<Module> Approvals" | NA, BS, BA | This is the core four-eyes screen (approve, reject, bulk approve). It is only described indirectly, in the list-page guides. |
| Person record | `users.show` `/users/{id}` | Persons | All | This is where the add buttons, print-certificate links and history lists are. |
| Edit person | `users.edit` `/users/{id}/edit` | Persons | NA, NAs, BS, BA, BAs | Unit assignment, moves, photo and signature, and archiving all happen here. They are only described in the Persons guide. |
| Register New Person | `users.create` `/users/create` | Persons | NA, NAs, BS, BA, BAs | Creating a new person has no guide anywhere, and no tutorial either (see 01-tutorial.md). |
| Certificate Prints Report | `certificates.prints-report` `/certificates/prints-report` | Certificate Prints Report | All (view) | This is where a wrong **Mark as Printed** gets corrected. |
| ID Card Prints Report | `id-cards.prints-report` `/id-cards/prints-report` | ID Card Prints Report | All (view) | Same purpose, for ID cards. |
| Campaign review / monitor | `campaigns.admin.show` `/campaigns/admin/{id}`, `campaigns.admin.monitor` | Campaigns Management | NA | Used for approving and sending campaigns (national only). |
| Pending Approvals report | `reports.pending-approvals` `/reports/pending-approvals` | Pending Approvals | All (`view_reports`) | Linked from the Dashboard approval counts. |
| Other planning tools: All Campaigns, Donation Appreciation, ID Card Expiry Report, Red Cross Units report | `reports.campaign-planning.campaigns`, `…donation-appreciation`, `reports.id-card-expiry.national`, `reports.red-cross-units.index` | All Campaigns Report / Donation Appreciation Planner / ID Card Expiry Report / Red Cross Units | All | Four of the eight Planning Tools have a **What to do** guide; these four don't. |
| Tutorial Completion report | `reports.tutorial-completion` `/reports/tutorial-completion` | Tutorial Completion | Branch and national roles (division roles get 403) | Relevant to the TOT follow-up. |
| Database Team / Database Access reports | `reports.database-team.index`, `reports.database-access.index` | Database Team / (dynamic title) | `view_reports` | Useful for checking who holds roles in a branch. |
| My Red Cross Unit | `red-cross-units.my-unit` `/my-unit` (+ `/my-unit/report`, `/my-unit/tables`) | My Red Cross Unit | Unit members / leaders | This is the volunteer-facing page, including the "ID Card Completeness Report" that the Dashboard guide refers to. |
| Task Force Types, Activity Types, Signature Titles, Campaign Purposes, National Database Settings | `task-force-types.index`, `activity-types.index`, `signature-titles.index`, `admin.settings.campaign-purposes.index`, `admin.settings.edit` | — | NA (settings area) | National only. These are covered in summary by the Settings guide. |

---

## 5. Observations for the course leader (outside the guide text)

1. **Guide text contradicts the screen in several places.** These are marked ⚠ above. The most common case is the non-existent **Show archived** button on Persons, which appears in both the Persons guide and the Organisations guide. There is also "Show more filters" instead of **Show all filters**, and the Donation/Training Records mix-up in the Training Statistics tip. The Authorizations guide suggests branch staff can appoint Branch Secretaries and Branch DB Administrators; they can't.
2. **DB-number search.** Guides and placeholders suggest you can search by DB-number. In practice only the numeric part works (the person or user ID). Teach participants to type **7442**, not **DB-7442-LAG-Ikeja**.
3. **Access gaps, for the developer rather than the course.** `organisations.index`, `membership-fees.*`, `training-types.*`, `task-force-types.*` and `activity-types.*` only check the general `manage-admin-panel` permission (plus password confirmation for fees and training types). Any admin role, including division assistants and observers, can therefore open them by typing the URL, even though they are hidden from the menu. I didn't test whether their create, update or delete actions have further checks.
4. **Exports:** none of the 25 pages with a guide has an export or download button.
