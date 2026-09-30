# 04 — Dashboard, planning tools and reports

Written for the branch secretary / branch DB administrator training-of-trainers. It is based on the codebase as of 2026-09-30 (`main`, `34b445a`). This was a read-only review: no code was changed.

**Conventions**

- Labels are shown exactly as users see them.
- **"All roles"** means all 9 roles: `super-admin`, `national_db_administrator`, `national_db_assistant`, `observer_national_level`, `branch_secretary`, `branch_db_administrator`, `branch_db_assistant`, `division_db_assistant_finance`, `division_db_assistant_operations`. Every one of them holds `view_reports`, the permission on every `/reports/*` route.
- Short forms in role lists: **NA** = National DB Administrator, **BS** = Branch Secretary, **BA** = Branch DB Administrator.
- **"Approved only"** means the figure counts only records with `approval_status = approved`. This is a global scope on payments, donations, trainings and volunteering, so records still waiting for four-eyes approval are not counted.
- **Sandbox data:** the practice questions assume the course sandbox has realistic data. On a laptop copy, some figures may be empty. For example, the heat-map scores (`heat_score`) are not computed locally, and there may be no printable ID cards.

---

## 1. The Dashboard (`/reports`)

| | |
|---|---|
| Route | `reports.dashboard` → `Reports\DashboardController@index` → view `dashboard.blade.php`. The sidebar **Dashboard** item and the top-bar **Admin** link both go through `/admin/dashboard`, which redirects here. |
| Access | All roles (`view_reports`) |
| Branch scope | On first load you see **your own branch**: `getScopedBranchId()`, which is the division's branch for division assistants, or National for national roles. The **Select branch:** dropdown lets *every* role switch to **any** branch or to **National**. The choice is remembered in the session. |
| Caching | **Statistic cards and Lifecycle Overview:** cached per branch for **1 hour**, and shared by everyone viewing that branch. The line "Stats update once per hour · Last updated … — **Refresh now**" rebuilds the cache. The cache is also cleared after the nightly `stats:snapshot`. **Housekeeping, 7-day activity and pending-approval counts:** always live. Three slow Housekeeping counts are cached for 5 minutes. |

### 1.1 Widgets in display order

| # | Title (exact) | What it shows | How it's calculated | Scoped by selected branch? | Click behaviour | Who sees it |
|---|---|---|---|---|---|---|
| 0 | **Super Administrator Account** | "This account has one purpose: appointing and removing National Database Administrators…" | — | — | **Go to Authorizations** → `/users/roles` | super-admin only |
| 1 | *(Header)* **<your full name>** · **Role:** · **Access:** · **Scope:** | Your role, access level (national / branch / division) and scope path | `role_display_name`, `getAccessLevel()`, `scope_path` | — | — | All |
| 2 | Confidentiality banner | "This database holds confidential personal data. Do not share it with anyone outside the administrative team." | — | — | — | All |
| 3 | **Training** → **Open Tutorials** | "Guided lessons — learn how to use the database." | — | — | → `/learn` | All |
| 4 | **<Branch> Statistics** + **Select branch:** | The branch name ("National" if none), last-updated time, **Refresh now** | — | — | The dropdown reloads the page for the chosen branch | All |
| 5 | **Supporting Members** | Headline count; "±x% since last month" / "±x% since last year"; **Gender distribution** pie (Men / Women / Unknown) | `User::members()` = **no Red Cross Unit** + a valid, approved personal membership payment + lifecycle active or dormant. The trends compare with the nightly `stats_snapshots` totals from 1 month / 12 months ago; the % is hidden if no snapshot exists. | Yes (`users.branch_id`) | None | All |
| 6 | **Retention Rate** *(extended mode only)* | Headline %, "Last 12 months"; **Expired in last 12 months**, **Renewed and still active**, **Did not return** | Cohort = distinct people whose personal membership expired in the last 12 months. Retained = those who now have a later, approved, currently valid personal payment. Rate = retained ÷ cohort. | Yes (`membership_payments.branch_id`) | None | All |
| 7 | **Membership Revenue** *(extended)* | ₦ in the last 12 months (rolling); **12–24 months ago**; **Change** (% and ₦); split into **Member fees**, **Volunteer fees**, **Organisation-sponsored**, **Red Cross Unit fees** | Sum of the **fee amount** (`membership_fees.amount`) for non-deleted, approved payments with a `payment_date` in the window, split by payer type and the fee's `is_volunteer_fee` flag. ID-card surcharges are not included. | Yes | None | All |
| 8 | **Donations** *(extended)* | ₦ "Cash donations last 12 months"; **Cash 12–24 months ago**; **Cash change**; **Individual donations** vs **Organisation-sponsored** | Sum of approved, non-deleted **cash** donations by donation date. In-kind donations are not included. | Yes | None | All |
| 9 | **Volunteers** | Headline count, "including N volunteering members", month and year trends, **Gender distribution** | `User::volunteers()` = assigned to an **active** unit + lifecycle active or dormant. "Volunteering members" = contributor type *Volunteer & Member*. Trends come from `stats_snapshots`. | Yes | None | All |
| 10 | **Training & First Aid** *(extended)* | "Trained in the last 12 months (rolling)"; **12–24 months ago**; **Total change**; a **First aid training** block with Last 12 months / 12–24 months ago / Change | Count of approved, non-deleted training records by `training_date`. First aid = training types flagged `is_first_aid`. This counts **training records**, not distinct people. | Yes (`trainings.branch_id`) | None | All |
| 11 | **Registrations** *(extended)* | "New registrations in the last 12 months"; **12–24 months ago**; **Change** | Users by `created_at`. Includes everyone, even people later archived. | Yes | None | All |
| 12 | **Red Cross Units** *(extended)* | Active-unit count ("Active units nationwide"); **Average members per active unit**; **Leadership coverage**: Units with / without leadership | Active units. Average = non-archived people ÷ active units. "Without leadership" = both Team Leader and Assistant Team Leader empty. | Yes (via the unit's division) | None | All |
| 13 | **Show more statistics** / **?** glossary | The button switches on *extended mode* (cards 6–8 and 10–12). In extended mode, a **?** icon opens the **Statistics Glossary** instead. | — | — | Reloads with `?extended=1` | All |
| 14 | **Lifecycle Overview (<branch>)**: three boxes, **Pending engagement**, **Active**, **Dormant** | Counts, with the texts "Make first contact and guide them to the next step." / "Maintain momentum." / "Previously active people with no recent activity." plus "N Archived" under Dormant | Counts by `users.lifecycle_status` (pending_engagement / active / dormant / archived). These are **all people**, not just members plus volunteers. | Yes | Each box has a **Take action!** guide pop-up (see 02-guides.md §2.1) | All |
| 15 | Lifecycle illustration | Diagram of how people move between statuses | Static image | — | Click it or **View larger** to open a lightbox | All |
| 16 | **Planning Tools** | 8 buttons: **Welcome Campaign Planner**, **Expiring Membership**, **Re-engage Dormant**, **Training Statistics**, **ID Card Expiry Report**, **Red Cross Units**, **Donation Appreciation**, **All Campaigns** | — | — | Opens the tool (§2) | All |
| 17 | **Trends & Statistics** | 8 buttons: **Membership Reports**, **Volunteer Reports**, **Training Reports**, **Registrations Reports**, **Lifecycle Reports**, **Financial Trends**, **Membership Revenue Reports**, **Donation Reports** | — | — | Opens the report (§3). Most open at **National** level; you then drill down. | All |
| 18 | **Heat Maps** | **Volunteer Map**, **First Aid Coverage** | — | — | §3.8 | All |
| 19 | **Database Administration** | **Database Team**, **Admin Activities**; **Tutorial Completion** (branch and national only). A **Migration Report** button exists but is hidden with CSS. | — | — | §3.9 | All; Tutorial Completion: national and branch |
| 20 | Activity cards (no heading) | **Campaign messages sent** (last 7 days) · **ID cards printed** (last 7 days) · **Certificates printed** (last 7 days) · **Logged in** (last 3h) | Messages: recipients with a sent status. ID cards: `id_card_prints` with status "printed". Certificates: `certificates_print`. Logged in: users with `last_login_at` in the last **3 hours**. | ⚠ **Only "Logged in" is scoped to the branch.** The other three are always **national** figures. | None | All |
| 21 | **Housekeeping — <branch or Nationwide>** / "Aim for ZERO!" → pending approvals | Five tiles: **Payments**, **Donations**, **Trainings**, **Volunteering**, **Campaigns**, each with "N pending approval" | Records with approval status *pending*. The Campaigns tile counts campaigns with status *proposed* and is **always national**. | Yes, except Campaigns | **View** → that module's Approvals tab. **View** only appears if you have the approve permission (NA, BS and BA for records; NA only for campaigns). The note "N of these was/were entered by you — another admin must approve." is shown when some pending records are your own. | Tiles: all. **View**: approvers only |
| 22 | **View breakdown by branch** | Link | — | Shown only in the **National** view when anything is pending | → **Pending Approvals** report (§3.9) | National view |
| 23 | **Hanging Registrations** + **What is this?** | "N (M total)": people registered **by an admin** who are still *pending engagement*, i.e. the registration was never followed by a payment. N = since the new database went live (`NRCS_DB_MIGRATION_DATE`); M = including old imported data. Shows "Not configured…" if the date is not set. | `User::adminRegistered()`, pending engagement, not an organisation account, not inactive | Yes | The number and **View** → Persons filtered *Registered by Admin* + *Pending Engagement* | All |
| 24 | **Unverified Registrations** + **What is this?** | Count of people with an email address that was never confirmed | `email` not null and `email_verified_at` null | Yes | **View** → Persons filtered *Unverified email*, lifecycle *All* | All |
| 25 | **Volunteers in Limbo** + **What is this?** | People who were once in a unit but are now in none (usually a transfer that stalled) | `User::unassignedGhost()` with lifecycle active or dormant | Yes | **View** → Persons with *Unassigned (Volunteers in Limbo)* | All |
| 26 | **Your Role** | A role description card | `x-role-description-card` | — | — | Users with a role |
| 27 | **Your place in the team** | The National / Branch / Division role chart with **YOU** highlighted, plus any extra direct permissions | — | — | — | All |

⚠ Dashboard issues to be aware of:
- **Card 12 wording.** It says "Active units **nationwide**" even when a single branch is selected. The figure itself *is* branch-scoped.
- **Glossary vs card title.** The glossary calls card 6 "**Renewal Rate**", but the card title is "**Retention Rate**".
- **Housekeeping View links and branch scope.** The **View** links pass the selected branch, but the Persons page ignores `branch_id` for branch- and division-level users and always shows their own branch. If a branch secretary switches the Dashboard to *another* branch, the Housekeeping numbers are that branch's, but **View** lists their own branch's people.
- **"Logged in" window.** The tile shows the last **3 hours**. In the code the variable is called `loggedInLast24h`; the label on screen is correct.

**Practice questions (Dashboard)**
1. With your own branch selected: how many **Supporting Members** and **Volunteers** does your branch have? What share of your volunteers are women?
2. Click **Show more statistics**. What is your branch's **Retention Rate**, and how many members **Did not return**?
3. How many **Units without leadership** does your branch have? Name one way to fix that (see 02-guides §2.3).
4. How many **Payments** are pending approval in your branch, and how many of those did *you* enter?
5. Switch **Select branch:** to National, then back to your branch. Which Housekeeping number is furthest from "ZERO", and what would you do about it?

---

## 2. Planning tools

The eight **Planning Tools** buttons on the Dashboard. All are GET pages, all roles can open them (`view_reports`), and none has a CSV export. Four of them have a **What to do** guide (see 02-guides.md §2.22–2.25).

**How they scope data.** The planners show **aggregated counts** for all branches, and highlight your own branch's row. Branch users open the **Welcome**, **Re-engage Dormant** and **Expiring Membership** planners already drilled into their own branch. **Training Statistics** is the only tool that *restricts* branch and division users to their own area. The **ID Card Expiry Report** redirects branch and division users to their own branch or division when opened.

**Shared column meanings in the three contact planners:**
- **Not yet contacted** *(freshest targets)*: people who have never been sent a campaign message.
- **Contacted once**.
- **Contacted 2+** *(de-prioritise)*.
- Clicking an area row drills down from Branch to Division.

### 2.1 Welcome Campaign Planner — `reports.campaign-planning.welcome` (`/reports/campaign-planning/welcome`)

| | |
|---|---|
| Purpose | Plan welcome or onboarding campaigns for people stuck in **Pending engagement**. |
| Inputs | **Contribution preference** toggle: **Both** / **Volunteer** / **Member** (the person's "wants to contribute as" flags). **Registered within**: All time / Last 30 / 60 / 90 / 180 days. **Gender**: Both genders / Male / Female. **Age group**. **Filter** / **Clear**. |
| Outputs | Summary counts of pending people (total, wanting to volunteer, wanting membership). A table per area (branch, or division after drilling): **Total pending** · **Not yet contacted** · **Contacted once** · **Contacted 2+** · **Avg days since registered**. "Contacted" here means *any* campaign message, whatever its purpose. |
| Next step | **What to do** → Persons → **Campaign filter wizard** → *Welcome newly registered persons* |
| Branch-secretary question | "Which of my divisions has the most people who registered in the last 90 days but have never been contacted?" |

Practice:
1. In your branch, how many pending people registered in the **last 30 days** and have **not yet been contacted**?
2. Which division has the highest **Avg days since registered**? What does that tell you?

### 2.2 Expiring Membership — `reports.campaign-planning.expiring-membership`

| | |
|---|---|
| Purpose | Plan renewal reminders before (or just after) memberships lapse. |
| Inputs | **Supporting Members/Volunteers**: All / Supporting Members / Volunteers. **Time to expiry**: Expiring within 28 days (default) / Expiring within 14 days / Expired. **Filter** / **Clear** / **All Branches**. |
| Outputs | Per area: **Total** · **Not yet contacted** · **Contacted once** · **Contacted 2+**. Here "contacted" counts only messages sent with the **Membership Pre-Expiry Notice** campaign purpose. Population: active or dormant people whose current personal membership expires in the chosen window. |
| Next step | Persons → **Campaign filter wizard** → *Remind about expiring membership* |
| Branch-secretary question | "How many supporting members in my branch expire in the next 14 days and haven't had a renewal reminder yet?" |

Practice:
1. How many **Volunteers** in your branch have a membership **Expiring within 28 days**?
2. Switch to **Expired**. Which division has the most expired memberships that were never reminded?

### 2.3 Re-engage Dormant — `reports.campaign-planning.dormant` (heading **Re-engage Dormant Volunteers**)

| | |
|---|---|
| Purpose | Plan reactivation campaigns for dormant **volunteers**: dormant people who are in a unit, or who were in a unit and are now "in limbo". |
| Inputs | **Gender**, **Age group**, **Filter** / **Clear** |
| Outputs | Per area: **Total dormant** · **Not yet contacted** · **Contacted once** · **Contacted 2+** · **Avg days since last activity** |
| Next step | Persons → **Campaign filter wizard** → *Re-engage dormant volunteers* |
| Branch-secretary question | "Where in my branch are the most dormant young volunteers (Youth 18–35) whom we have never contacted?" |

Practice:
1. How many dormant volunteers does your branch have, and how many are **Youth (18–35)**?
2. Which division has gone longest without activity (highest **Avg days since last activity**)?

### 2.4 Training Statistics — `reports.trainings.stats` (`/reports/trainings/stats`)

| | |
|---|---|
| Purpose | Find training gaps, expiring certifications, stale First Aid skills and missing certificates. |
| Inputs | Tabs: **Training Coverage**, **Expiry Timeline**, **First Aid Staleness**, **Certificates**. **Branch** → **Division** → **Red Cross Unit** (branch users are fixed to their own branch; division users to their own division), then **Filter**. **Volunteers / All** population toggle. Certificates tab: **Date From** / **Date To**, then **Apply**. Clicking a training type drills down: Training Type → Branch → Division → Unit. |
| Outputs | **Coverage:** per training type or area — **Trained** · **Not Trained** · **Coverage %**, with First Aid and Other trainings listed separately. **Expiry Timeline:** counts per month bucket, from already expired (grey) through the coming months · **Total**. **First Aid Staleness:** people grouped by months since their last First Aid training (12–23m, 24–35m … ≥ 60m). **Certificates:** **Attendance Cert printed** · **Competence Cert printed** · **No Certificate printed yet**. |
| Branch-secretary question | "In which of my divisions do fewer than half the volunteers have any First Aid training?" |

Practice:
1. What is your branch's **Coverage %** for your main First Aid training type? Which division is lowest?
2. On **Expiry Timeline**, how many certifications in your branch expire in the next 2 months?
3. On **Certificates**, how many trainings in your branch still have **No Certificate printed yet**?

### 2.5 ID Card Expiry Report — `reports.id-card-expiry.national` → `.branch` → `.division`

| | |
|---|---|
| URLs | `/reports/id-card-expiry`, `/reports/id-card-expiry/branch/{branch}`, `/reports/id-card-expiry/branch/{branch}/division/{division}` |
| Purpose | See when printed ID cards expire, to plan renewals and print runs. |
| Inputs | No filters. Navigation only: click a row to drill National → Branch → Division, with breadcrumbs. **Branch and division users are redirected straight to their own level.** |
| Outputs | Rows = branches, divisions or **RC Unit**s. Columns: **3mo ago**, **2mo ago**, **1mo ago** (already expired; grey) · **< 1mo**, **2mo** (red) · **3mo**, **4-6mo** (orange) · **7-9mo** (blue) · **Total**. Each person's **latest** ID-card print record is counted by its expiry date. People who have never had a card printed are not counted. |
| Branch-secretary question | "Which divisions have cards expiring in the next 3 months, so I can prepare their data before asking HQ to print?" |

Practice:
1. How many ID cards in your branch expire within the next 3 months (**< 1mo** + **2mo** + **3mo**)?
2. Drill into your largest division. Which RC Unit has the most cards expiring in **4-6mo**?

### 2.6 Red Cross Units (report) — `reports.red-cross-units.index` (`/reports/red-cross-units`)

(This is not the same page as the **Red Cross Units** item in the sidebar.)

| | |
|---|---|
| Purpose | Compare the health of units: demographics, training, activity and digital engagement. |
| Inputs | Tabs: **Demographics**, **Training**, **Volunteering Hours**, **Account Status**. Drill down by clicking rows: Branch → Division → Unit. Your own branch is highlighted. |
| Outputs | **Demographics:** Total Volunteers · Total Units · Volunteers Only · Volunteers & Members · Men · Women · Avg Age · age bands (< 15, 15–24 … 65+). **Training:** % Any Training · % First Aid · Trained Last 12m / 3m / 1m. **Volunteering Hours:** Total Hours · Hours per Volunteer · Hours Last 12m / 3m / 1m. **Account Status:** % Never Logged In · Avg Days Since Login · % With Email · % Has Photo · Avg Days Since DB Entry · % Dormant. |
| Branch-secretary question | "Which of my units has the lowest % Has Photo? That's where ID-card preparation will stall." |

Practice:
1. Which unit in your branch logged the most **Hours Last 3m**?
2. Which unit has the highest **% Never Logged In**? What would you do about it?

### 2.7 Donation Appreciation — `reports.campaign-planning.donation-appreciation` (heading **Donation Appreciation Planner**)

| | |
|---|---|
| Purpose | Plan thank-you campaigns for donors. |
| Inputs | Tab **Appreciation Tracker**. Drill-down via **All Branches** / area rows. |
| Outputs | Heading "Say thank you to your donors". Per area: **All eligible donors** (people with an approved, non-anonymous donation) · **Never thanked** (never sent a *Donation Appreciation* campaign message) · **Donated again since being thanked** (latest donation is after the last thank-you). |
| Branch-secretary question | "How many donors in my branch have never received a thank-you?" |

Practice:
1. How many **Never thanked** donors does your branch have?
2. Which division has donors who **Donated again since being thanked**?

### 2.8 All Campaigns — `reports.campaign-planning.campaigns` (heading **All Campaigns Report**)

| | |
|---|---|
| Purpose | Review every campaign that has actually sent messages, to avoid over-messaging and to reuse good ideas. |
| Inputs | Tabs: **Origin** (who sent it) / **Destination** (whose people received it; one row per campaign × receiving branch). Filters: **Branch**, **Purpose**, **Date From**, **Date To**, then **Filter**. |
| Outputs | Columns: **Campaign** · **Purpose** · **Origin** · **Branch** · **Date Sent** · **Persons Sent**. Only campaigns with at least one message sent are listed. |
| Branch-secretary question | "Which campaigns reached people in my branch last quarter, including ones sent by HQ?" |

Practice:
1. On **Destination**, filtered to your branch: how many campaigns reached your people in the last 3 months?
2. Which **Purpose** has your branch used most as an origin?

---

## 3. Reports

Unless stated otherwise:
- **Access:** all roles (`view_reports`).
- **Export:** CSV (the export link adds `?export=csv`; the file has a UTF-8 BOM and opens directly in Excel) plus a **Print** button (the browser's print function).
- **Charts:** Chart.js.
- **Scope:** the trend reports (§3.1–3.6) have **no branch restriction**. Every role, division assistants included, can view national and any branch's **aggregated** figures and drill down freely. No personal data is shown, only counts and ₦ totals.

### 3.1 Financial

| Report (page header) | Route → URL | Filters / parameters | Output | Export | Access |
|---|---|---|---|---|---|
| **Financial Trends – National** | `reports.financial.national` → `/reports/financial/national` | **Trend range:** Last 2 / 4 / 6 / 8 years; **Select Year:** | Trend line chart of membership revenue (₦); table **Membership Revenue by Branch – Quarterly <year>**: Branch · Q1–Q4 · Total. Click a branch to drill down. **Financial Breakdown** button → Membership Revenue Report. | CSV (Area, Q1–Q4, Total), Print | All |
| **Financial Trends – <Branch>** | `reports.financial.branch` → `/reports/financial/branch/{branch}` | Same, plus `division_id` | Trend chart; **Membership Revenue by Division in <branch> – Quarterly (<year>)** | CSV, Print | All |
| **Membership Revenue Report** ("Membership, volunteer, organisation, and Red Cross Unit revenue") | `reports.financial.index` → `/reports/financial` | **Year**, **Branch** (National or a branch), `tab` | A quarterly table per branch (or per division) with Q1–Q4 · Year Total, split **Mem / Vol / Org / RCU**; plus a by-**Fee Type** table (Q1–Q4). Amounts link to the drill-down lists below. | CSV (payments by payer type per quarter, or fee-type breakdown), Print | All |
| Payments breakdown list | `reports.financial.breakdown` → `/reports/financial/breakdown` | `branch_id`, `level`, `quarter`, `category` (from the links) | **Payer · Reference · Fee Type · Date · Amount** (individual payments) | — | Needs `view_payments` (**not** DO). Scoped to your own area. |
| Payments by fee list | `reports.financial.breakdown-by-fee` → `/reports/financial/breakdown-by-fee` | `fee_id`, `quarter`, `category`, `scope` | **Payer · Reference · Fee Type · Branch · Date · Amount** | — | `view_payments`, scoped |

Practice:
1. Using **Membership Revenue Report**, what was your branch's revenue from **Vol** (volunteer) fees in Q2 of this year? Click the figure and name one payer.
2. On **Financial Trends**, has your branch's membership revenue gone up or down compared with last year? Download the CSV.

### 3.2 Membership and volunteers

| Report | Route → URL | Filters | Output | Export | Access |
|---|---|---|---|---|---|
| **Membership – National Overview** | `reports.members.national` → `/reports/members/national` | Trend: 2 / 4 / 6 / 8 years (`trend_months`); `year` | Line chart of active **supporting** members over time (Total / Male / Female, from the nightly snapshots). Demographics: gender doughnut + age bar chart. Table **Total Supporting Members by Branch**: Branch · Men · Women · Total. | CSV, Print | All |
| **Membership – <Branch>** | `reports.members.branch` | Same | **Total Supporting Members by Division** | CSV, Print | All |
| **Division Membership Report** | `reports.members.division` → `/reports/members/division/{division}` | Same | Chart and demographics for one division | — | All |
| **Volunteers – National Overview** | `reports.volunteers.national` → `/reports/volunteers/national` | Trend 2 / 4 / 6 / 8 years; `year` | Volunteer time series (Total / Male / Female); demographics; **Total Volunteers by Branch**: Branch · Men · Women · Total | CSV, Print | All |
| **Volunteers – <Branch> Branch** | `reports.volunteers.branch` | Same | **Total Volunteers by Division** | CSV, Print | All |
| **Division Volunteer Report** | `reports.volunteers.division` | Same | Chart and demographics | Print | All |

Practice:
1. How many **women volunteers** does your largest division have?
2. Looking at the 2-year trend, when did your branch's volunteer count peak?

### 3.3 Lifecycle

| Report | Route → URL | Filters | Output | Export | Access |
|---|---|---|---|---|---|
| **Lifecycle Report** | `reports.lifecycle.national` → `/reports/lifecycle` (`?branch_id=` drills into a branch) | **Trend** (2 / 4 / 6 / 8 years), **Statuses shown** tick boxes (**Pending**, **Active**, **Dormant**, **Archived**), **Apply** | Multi-line chart of the chosen statuses over time. Table per branch, or per division after drilling down, with one column per status. Breadcrumb navigation. | CSV (area + one column per status), Print | All |

Practice:
1. How many **Dormant** people does your branch have today? Is the line rising or falling over the last 2 years?
2. Untick everything except **Pending**. Which division has the most people pending?

### 3.4 Training

| Report | Route → URL | Filters | Output | Export | Access |
|---|---|---|---|---|---|
| **Trainings – National Overview** | `reports.trainings.national` → `/reports/trainings/national` | **Select Year:**, `trend_years` | Charts: **First Aid Trainings – Last 12 Months**, **Total Trainings – <year>**, **First Aid vs Other – <year>**. Table **Quarterly Trainings by Branch**: Branch · Q1–Q4 · Total · FA · Other | CSV (Q1–Q4 First Aid / Other, Total), Print | All |
| **Trainings – <Branch>** | `reports.trainings.branch` | Same | The same charts; **Quarterly Trainings by Division** | CSV, Print | All |
| **Training Statistics** | `reports.trainings.stats` | See §2.4 | See §2.4 | — | All (area-restricted) |

Practice:
1. How many First Aid trainings did your branch record in Q1 of this year, compared with other trainings?
2. Which division trained the most people this year?

### 3.5 ID card expiry

See §2.5 (**ID Card Expiry Report**). There is no other ID-card report under `/reports`. The **ID Card Prints Report** (`/id-cards/prints-report`, opened with **View Print History** on the ID Cards page) lists individual prints.

### 3.6 Donations

| Report | Route → URL | Filters | Output | Export | Access |
|---|---|---|---|---|---|
| **Donations – National Overview** | `reports.donations.national` → `/reports/donations/national` | **Select year for quarterly summary:**, `trend_years` (2 / 4 / 6 / 8) | Line chart: **Cash Donations (₦)** and **In-kind Donations (count)**. Table **Quarterly Donations Summary by Branch – <year>**: Branch · Q1–Q4 · Total · Cash (₦) · In-kind. Cells link to the breakdown list. **In Kind Donations** button. | CSV (Q1–Q4 Cash / In-kind, totals), Print | All |
| **Donations – <Branch>** | `reports.donations.branch` → `/reports/donations/branch/{branch}` | Same | **Quarterly Donations by Division – <year>** | CSV, Print | All |
| Donation breakdown list | `reports.donations.breakdown` → `/reports/donations/breakdown` | `branch_id`, `level`, `year`, `quarter`, `type` (cash / in-kind) | **Donor · Reference · Date · Amount** (or **Item**) | — | Needs `view_donations` (**not** DO); scoped |
| **Donations – In Kind** | `reports.donations.in-kind` → `/reports/donations/in-kind` | **Branch** (National or branch; branch users are limited to their own) | **Reference · Donor · Date · Number of Items · Item · Purpose** | Print | `view_donations`; branch-scoped |

Practice:
1. What was the total cash donated in your branch last year, and which quarter was strongest?
2. List the in-kind donations your branch received this year. What was the most common item?

### 3.7 Registrations

| Report | Route → URL | Filters | Output | Export | Access |
|---|---|---|---|---|---|
| **Registrations – National Overview** | `reports.registrations.national` → `/reports/registrations/national` | **Select year:**, `trend_years` | Registrations line chart; **Registrations by Branch – <year>**: Branch · Registrations | CSV, Print | All |
| **Registrations – <Branch>** | `reports.registrations.branch` → `/reports/registrations/branch/{id}` | **Trend range:**, **Select year for division summary:** | **Registrations by Division – <year> (<branch> Branch)** | CSV, Print | All |

Practice: How many people registered in your branch this year? Which division is growing fastest?

### 3.8 Maps

| Report | Route → URL | Output | Access |
|---|---|---|---|
| **Volunteers by Branch** / **Volunteers by Division** (dashboard label **Volunteer Map**) | `reports.maps.volunteers.branches` / `.divisions` | Bubble map: "Bubble size = volunteer count · Color = activity intensity". Count = `User::volunteers()`. Colour = the `heat_score` recalculated nightly (`heat:recalculate`, 02:30). **By Branch** / **By Division** toggle. | All |
| **First Aid Coverage by Branch** / **by Division** | `reports.maps.first-aid.branches` / `.divisions` | "Bubble size = number of first aiders · Colour = training freshness (green = recent)". Uses the precomputed `first_aid_count` and `first_aid_avg_days` (`firstaid:recalculate`, nightly at 02:30), with a freshness cap setting (default 1095 days). | All |

Practice: On **First Aid Coverage by Division**, find your branch's divisions. Which has the most first aiders, and which has the "reddest" (stalest) bubble?

### 3.9 Administration and other

| Report | Route → URL | Filters | Output | Export | Access |
|---|---|---|---|---|---|
| **Pending Approvals** ("Outstanding records awaiting approval — all branches") | `reports.pending-approvals` → `/reports/pending-approvals` | — | **Branch · Payments · Donations · Trainings · Volunteering · Total · Oldest Pending** | — | **National and branch only** (division → 403). Branch users see only their own branch. |
| **Database Team** ("Roles · Coverage · Activity") | `reports.database-team.index` → `/reports/database-team` | Tabs **National** / **Branch** / **Activity** / **Statistics**; **Branch** selector; **Scope** (National or a branch, for Activity); **Show profile photos** | Role holders grouped by role (Name / DB#, Membership, Volunteering, Trainings, Donations, Last Activity); **Branch Coverage** matrix (Branch · Secretary · DB Admin · DB Asst · Div Finance · Div Ops · Total) | — | All (branch and division users start on the Branch tab) |
| **Admin Activities** | `reports.admin-activities.index` → `/reports/admin-activities` | Tabs **ID Cards** / **Certificates** / **Messages**; **Branch**; **Trend** (years); **Certificate Type** | Multi-line trend chart and a per-area drill-down table: ID cards printed, certificates printed (including organisation certificates), and messages sent (**Email** / **SMS**) | CSV, Print | All |
| **Tutorial Completion** | `reports.tutorial-completion` → `/reports/tutorial-completion` | **Name**, **DB-number**, **Role**, **Branch** (national users only), **Show only people with no completed lessons**, **Filter** / **Clear** | **Name · DB-number · Branch · Role · Lessons completed · Last completed** (role holders only; super-admins excluded) | — | National and branch (division → 403). Branch users see only their own branch. |
| **Database Access Team** | `reports.database-access.index` → `/reports/database-access` | **Branch** (national only) | National Level Roles; Users with Extra Database Permissions; Branch Level Roles; Division Level Roles: **Name · Role · DB Reference · Telephone · Direct Permissions** | — | All (non-national users are fixed to their own branch). ⚠ **No link to it anywhere in the app**; reachable by URL only. Largely superseded by Database Team. |
| **Migration Report** ("Persons who moved between branches or divisions") | `reports.migration` → `/reports/migration` | **Branch**, **Movement Type** (Between Branches / Between Divisions), **Direction** (Moved in or out / Moved in / Moved out), **From** / **To** dates, `division_id` | **Person · From · To · Moved by · Type · Date** | — | All. ⚠ **Its Dashboard button is hidden.** A code comment says the report may be obsolete because self-service moves are no longer logged; the **Log** page (`member_branch_division_changed`) is the alternative. |

Practice:
1. On **Pending Approvals**, what is the **Oldest Pending** record in your branch? Who should approve it?
2. On **Database Team → Branch**, is every division in your branch covered by at least one division assistant? On **Statistics**, where does your branch sit in the **Branch Coverage** matrix?
3. On **Tutorial Completion**, tick **Show only people with no completed lessons**. Who in your branch still needs to start the tutorials?
4. On **Admin Activities → Messages**, how many SMS versus emails has your branch sent in the last year?

### 3.10 Routes that exist but are not usable

`reports.branches.index`, `reports.branches.comparison`, `reports.branches.growth`, `reports.branches.export/{type}`, `reports.units.index`, `reports.units.distribution`, `reports.units.performance` and `reports.units.export/{type}` are registered, but **their views do not exist**: the `resources/views/reports/branches` and `reports/units` folders are missing, and the export methods return a placeholder path. Nothing links to them. Leave them out of the training.

---

## 4. Summary for the course leader

- **Where branch staff can see beyond their own branch.** Every role can switch the Dashboard to any branch or to National, and every trend report (§3.1–3.4, §3.6–3.8) shows national and per-branch **aggregates**. Anything that lists **named people** is restricted to your own area: the financial and donation breakdown lists, In Kind, Pending Approvals, Tutorial Completion and Training Statistics.
- **Division Operations assistants** can open all the Trends & Statistics reports, but not the payment or donation breakdown lists (403).
- **Exports:** CSV exists for Financial Trends, Membership Revenue, Membership, Volunteers (national and branch), Lifecycle, Trainings, Donations, Registrations and Admin Activities. The planning tools, Training Statistics, ID Card Expiry, Pending Approvals, Database Team, Tutorial Completion and Migration have **no export**.
- **Figures don't all use the same definitions.** "Members" in the reports means *supporting* members (no unit). "Volunteers" means people in an active unit with status active or dormant. The Welcome-page map uses looser definitions (see 03-navigation.md §1.3). Only **approved** records count towards any total.
