# 05 — Workflows: roles, moves, four-eyes approval, campaigns, sandbox readiness

Written for designing the paired exercises of the branch secretary / branch DB administrator training-of-trainers. It is based on the codebase as of 2026-09-30 (`main`, `34b445a`). This was a read-only review: no code was changed.

- File:line references point to the current source.
- Role → permission facts come from `database/seeders/PermissionsTableSeeder.php`, cross-checked against the local database.
- **Short forms:** NA = National DB Administrator; NAs = National DB Assistant; Obs = Observer (national); BS = Branch Secretary; BA = Branch DB Administrator; BAs = Branch DB Assistant; DF / DO = Division DB Assistant — Finance / Operations; SA = super-admin.

---

## A. Roles

### A.1 All roles

| Role (exact name in code) | Scope (from `User::getAccessLevel()`, `User.php:1574`) | What the scope is based on |
|---|---|---|
| `super-admin` | national | — (its only job is appointing and removing NAs) |
| `national_db_administrator` | national | all branches |
| `national_db_assistant` | national | all branches |
| `observer_national_level` | national | all branches, read-only |
| `branch_secretary` | branch | the person's own `users.branch_id` |
| `branch_db_administrator` | branch | the person's own `users.branch_id` |
| `branch_db_assistant` | branch | the person's own `users.branch_id` |
| `division_db_assistant_finance` | division | the person's own `users.division_id` |
| `division_db_assistant_operations` | division | the person's own `users.division_id` |

**There are no unit-level roles.** A unit's Team Leader and Assistant Team Leader are fields on the unit, not roles.

A branch or division role has no branch of its own: its scope is **wherever the person's record currently sits** (`getScopedId()`, `User.php:1594`). For example, "Branch Secretary of Lagos" simply means "a person whose record is in the Lagos branch and who holds the `branch_secretary` role".

Each person holds **at most one role**: `updateRoles` calls `syncRoles([$incomingRole])` at `UserController.php:1373`.

### A.2 Who can assign which role

The roles you can assign come from your `authorize_<role>` permissions (`User::getAssignableRoles()`, `User.php:1785`):

| Assigner | Can assign / remove | Source |
|---|---|---|
| `super-admin` | `national_db_administrator` **only** | `PermissionsTableSeeder.php:319-320` |
| `national_db_administrator` | `branch_secretary`, `branch_db_administrator`, `national_db_assistant`, `observer_national_level` | `PermissionsTableSeeder.php:107-110` |
| `branch_secretary` | `branch_db_assistant`, `division_db_assistant_finance`, `division_db_assistant_operations` | `PermissionsTableSeeder.php:151-154` |
| `branch_db_administrator` | the same three | `PermissionsTableSeeder.php:183-186` |
| everyone else | nothing (they don't hold `manage_roles_and_permissions`) | — |

**A National DB Administrator cannot appoint Branch DB Assistants or either kind of Division Assistant.** The seeder says so at lines 111–112:

```php
// Branch/division assistants are appointed at branch level only
// (branch_secretary, branch_db_administrator) — by design.
```

(A recent commit, `29adeb2 Revert national admin grants for branch/division assistants (branch-level only)`, confirms this is deliberate.)

It follows that:
- **NA also cannot remove those three roles**, because of the "current role" check in rule 4 below.
- **An NA cannot appoint or remove another NA.** Only super-admin can.

**Checks in `UserController@updateRoles`** (`POST /users/roles`, `UserController.php:1270`):

1. `authorize('manage_roles_and_permissions')` (line 1272).
2. **A branch-level actor cannot touch a national-level target** (lines 1285–1287): *"Branch-level administrators cannot modify the roles of national-level users."*
3. **Nobody can change their own role** (lines 1294–1298): *"You cannot modify your own role or permissions."*
4. **Branch actors cannot change a peer BS or BA** (lines 1308–1311): *"Branch-level administrators cannot modify the role of another branch_secretary or branch_db_administrator. Contact a National DB Administrator."* This is checked against the target's **current** role.
5. **You must be allowed to grant both the new role and the target's current role** (lines 1321–1329): *"You are not authorized to assign or remove this role (…)."*
6. **The last National DB Administrator cannot be removed** (lines 1334–1340).
7. **Direct permissions** (`print_idcards`, `print_certificates`, `campaign_request_approve`; `UserController.php:45-49`) may only be given with the `national_db_assistant` role (lines 1356–1361).
8. **Every change is written to the audit log** as `user_roles_updated` (line ~1412), which shows in **Log**.

**Who you can find in the Authorizations search** (`UserController@searchUsersForRoles`, lines 1435ff.):
- Super-admins and **yourself** are never listed (lines 1449, 1452).
- A branch actor only finds people **in their own branch** who hold **no national role** (line 1463) and are **not a BS or BA** (line 1474).

⚠ **Not checked on the server:** `updateRoles` does not verify that the target is in the actor's branch. That restriction only lives in the search box. The form can still be posted directly with `?user_id=`. It is not a risk in training, but worth knowing.

⚠ **Not checked either:** assigning a division role does not check that the person has a division. In practice every person has a division, because the Edit form requires one.

### A.3 UI path: assigning and revoking a role

The page requires **password confirmation**. Laravel asks for your password again if you haven't confirmed it recently, by default within the last 3 hours.

**Assign**
1. Sidebar → **Authorizations** (`/users/roles`, heading **Manage User Roles and Permissions**). Enter your password if asked.
2. In **Search for a User**, type a name, email or ID and pick the person from the drop-down.
3. The form shows **Current role:** (or "No role assigned").
   - If the person is still *Pending Engagement*, a warning appears and the browser asks you to confirm on save. Assigning a unit first is recommended.
4. Choose a role in **Assign Role**. Branch actors only see the three assistant roles.
5. National actors only: tick **Direct Permissions** when the role is National DB Assistant.
6. Click **Update Roles & Permissions**. You should see "Successfully updated roles and permissions for <name>."
7. On first login after getting a role, the person must accept the data-handling policy (`/policy/accept`) before they can use anything.

**Revoke**
- Same page. Choose **"-- No Role --"** and click **Update Roles & Permissions**. Removing the role also removes any direct permissions.
- Shortcut: in **Review Users by Role** (below the form), click **Edit** on a row to open that person's form.

---

## B. Moving people

### B.1 UI path (staff)

1. **Persons** → search → **View** → **Edit**. The Edit page is `/users/{id}/edit`.
2. Change what you need:
   - **Branch**, then **Division** (the division must belong to the branch, checked at `UserController.php:860-870`);
   - and/or **Red Cross Unit** (it must be *active*, unless you are keeping the current one; lines 871–890).
3. Scroll down and click **Update Person**.

Rules along the way:
- **Units** are assigned only on the person's Edit page.
- **Task-force membership** is managed on the task force's own Edit page, under **Task Force Members**.
- A person can **move themselves**: **My Profile** → edit → Branch / Division (`ProfileController@update`). This is only allowed if they have **no unit and no branch- or division-level role** ("location locked"). Self-moves are **not** written to the Log.

### B.2 Who may move whom (`UserPolicy@update`, `app/Policies/UserPolicy.php:85-115`, plus `UserController@update`)

| Actor | Can edit / move | Limits |
|---|---|---|
| NA | Anyone | Can move people **between branches**. |
| BS, BA | Anyone in their own branch who is not national-level (policy lines 95–104) | Division and unit moves inside their branch are fine. **They cannot move anyone to another branch**: *"You can only move users within your own branch."* (`UserController.php:1074-1081`). They also cannot pull someone in from another branch, because they can't open that person's Edit page. |
| NAs | Anyone, **as long as the target holds no role** (policy lines 107–113) | Can move role-less people across branches. |
| BAs | People in their own branch with no role | Within their branch only. |
| DF, DO | Nobody (they don't have `edit_user`) | — |

**Branch moves and roles** (`UserController.php:1057-1071`). A person who holds any **branch- or division-level role** cannot be moved to another branch:

> "This person has an administrative role (…). Remove the role via Authorizations before moving them to a different branch."

- **National roles are exempt.**
- **Division changes inside the same branch are not blocked.** A division assistant moved to another division keeps the role and now covers the **new** division.

**No approval is needed.** A move takes effect immediately; there is no four-eyes step.

**Audit.** Every admin branch or division change is logged as `member_branch_division_changed` (`UserController.php:1047`).
- ⚠ The log entry is written **before** the role check and the own-branch check (lines 1057–1081). A move that is **refused** therefore still leaves a "Member location updated" entry in the Log. Warn participants when they look at the Log after a failed attempt.

**What happens to the person's roles:** the person keeps their role; the move is simply refused. To move a BS, BA or assistant to another branch:
1. The right person removes the role in **Authorizations**. BS and BA can only be removed by an NA; assistants by the branch's BS or BA.
2. An **NA** does the cross-branch move.
3. The role is re-assigned in the new branch: BS and BA by an NA; assistants by that branch's BS or BA.

**Moving someone into the Red Cross Unit of another branch:** a unit belongs to one branch. For a volunteer, first clear their unit (it can't be kept across branches), then an NA moves them, then the new branch assigns a unit. The Persons guide describes the same sequence.
- ⚠ The server does **not** check that the chosen unit belongs to the chosen division. The Edit form's cascading drop-downs are what normally prevent a mismatch.

---

## C. Four-eyes (submit → approve) workflow

### C.1 What goes through approval

Records in four modules use the `Approvable` trait (`app/Models/Concerns/Approvable.php`). New records are stored with `approval_status = 'pending'`, which is the column default (migration `2026_06_22_120000_…`, line 32).

| Record | Includes | Submitter column | Approve permission |
|---|---|---|---|
| **Membership payment** | personal, **organisation** and **Red Cross Unit** payments | `submitted_by_user_id` | `approve_payments` |
| **Donation** | personal and organisation; cash and in-kind | `entered_by_user_id` (`Donation::submitterColumn()`) | `approve_donations` |
| **Training** | — | `submitted_by_user_id` | `approve_training` |
| **Volunteering activity** | — | `submitted_by_user_id` | `approve_volunteering` |
| **Campaign** (separate mechanism) | — | `messaging_campaigns.submitted_by` | `campaign_request_approve` |

**Not** approved by a second person:
- person registrations and edits;
- moves;
- role changes;
- unit, task-force and organisation changes;
- certificate and ID-card printing.

**Exception:** a member's own **online Paystack payment** (`/make-payment`) is marked approved automatically once the gateway confirms it (`Approvable::markApprovedViaGateway()`, line 293). No human approves it.

### C.2 Who submits, who approves

| Module | Can submit (add_… permission) | Can approve |
|---|---|---|
| Payments | NA, NAs, BS, BA, BAs, DF | NA (any branch); BS and BA (**records whose `branch_id` is their branch**) |
| Donations | NA, NAs, BS, BA, BAs, DF | the same |
| Trainings | NA, NAs, BS, BA, BAs, DF, DO | the same |
| Volunteering | NA, NAs, BS, BA, BAs, DF, DO | the same |
| Campaigns | NA, NAs, BS, BA (`campaign_request_create`) | **NA only**, plus an NAs who has been given the *Campaign Request Approve* direct permission |

- **Which branch a record belongs to:** a record's `branch_id` is the **person's** branch (or the unit's branch for a unit payment), filled in by the entry form. Entry-form person search is limited to the submitter's own branch or division (for example `MembershipPaymentController@searchUsers`).
- **Approval scope** (`HandlesRecordApproval::authorizeApprovalScope`, lines 310–331):
  - national approvers cover every record;
  - branch approvers need `record.branch_id` = their branch: *"This record is outside your branch."*
- **No division-level role can approve** (`Approvable.php:118ff`).

### C.3 Where pending items appear

| Where | What | For whom |
|---|---|---|
| Record list pages (**Payment Records**, **Volunteering Log**, **Training Records**, **Donation Records**) | **Records** / **Approvals** tabs. The **Approvals** tab shows a yellow count badge. | Only people with that module's approve permission see the tab. |
| **Approvals** page (`/membership-payments/approvals`, `/activities/approvals`, `/trainings/approvals`, `/donations/approvals`) | Heading **"<Module> Approvals"**, "Pending <modules> awaiting your review". Columns: Member · Details · Location · Submitted by · Submitted · Actions. National users get **Branch** / **Division** filters. **The list already hides your own submissions** (`scopeEligibleForApproval`, `Approvable.php:118-147`). | approvers |
| Dashboard → **Housekeeping** | **Payments / Donations / Trainings / Volunteering / Campaigns** tiles showing "N pending approval". **View** opens the Approvals tab. The note "N of these was/were entered by you — another admin must approve." appears when some pending records are yours. | all (View: approvers only) |
| Dashboard → **View breakdown by branch** → **Pending Approvals** report | Pending counts per branch, with **Oldest Pending** | national and branch |
| **Campaign Management** → **Proposed** tab | Proposed campaigns | NA |
| Notification bell | **Nothing** is sent to approvers when a new record or campaign is submitted. | — |

The sidebar itself shows no badges.

### C.4 How to approve or reject

- **Approve:** on the Approvals page, click **Approve** on the row.
  - If the member is archived, a modal asks **"Reactivate archived member?"** → **Confirm & reactivate**.
  - If the person the record is for is also the person who entered it, a modal asks **"Approve a self-submitted record?"** → **Confirm & approve**.
- **Bulk approve:** tick rows, then **Approve selected (N)**.
  - Records that are yours, out of scope, archived, or already decided are skipped, and the reason for each is shown.
- **Reject:** click **Reject**. A modal "Reject this <module>" asks for a reason (placeholder "Reason for rejection (required)"; text: "The submitter will be notified with the reason you give."). Then **Confirm rejection**.
- A single record can also be opened on its review page (`/{module}/{id}/review`).
- **Campaigns:** **Campaign Management** → **Proposed** → **Review** → **Approve** or **Reject** (with feedback). After approval: **Queue** → **Build** → **Start sending** (§D.4).

### C.5 What the submitter sees afterwards

| Outcome | What happens |
|---|---|
| Still pending | The record appears in the "recent entries" list under the entry form (the button subtitle reads "View Your Entries"). It has a **Pending** badge and a **Withdraw** button, available only to the submitter while pending (`canBeWithdrawnBy`, `Approvable.php:194`). It does **not** count in any list total, report or statistic. |
| Approved | **No notification.** The record starts counting, and the person's lifecycle is recalculated: a *dormant* or *pending engagement* person becomes *active* (donations excepted for pending). The record shows as approved. |
| Rejected | A **bell notification**: "Your <module> #<id> was rejected" plus "Reason: …" (`RecordRejected`, a database notification only, no email). The reason is also shown in the entries list. The record stays as *rejected*; it is not deleted. |
| Campaign approved / rejected | Bell notification "Campaign approved: <title>" or "Campaign rejected: <title>" plus the reason (`CampaignDecided`). It moves to the **Approved/Sending** or **Rejected** tab in **<CODE> Campaigns**. A rejected campaign can be edited with **Edit campaign** and resubmitted. |

### C.6 How self-approval is prevented

Four layers of checks, all comparing the **submitter column** with the approver's user id:

1. **List filter:** `scopeEligibleForApproval()` excludes `submitter = me` (`Approvable.php:143-146`). Your own records never appear in your Approvals list.
2. **Controller guard:** `HandlesRecordApproval::guardFourEyes()` (lines 338–345) returns 403 with *"You cannot approve or reject a record you submitted yourself."*
3. **Model guard:** `Approvable::guardNotSelf()` (lines 453–461) is called inside both `approve()` (line 226) and `reject()` (line 415). It throws *"A record cannot be approved/rejected by the same user who submitted it."*
4. **Bulk approve** skips your own submissions: "Outside your scope, or your own submission".

**Campaigns:** `CampaignAdminController@approve` and `@reject` (lines 198–208 and 241–251) abort with *"You cannot approve or reject a campaign you submitted yourself."*

Notes:
- The check is about the **submitter**, not the **person the record is for**. An approver *can* approve a record about themselves if someone else entered it; they only get the "self-submitted record" confirmation when the person the record is for is also its submitter.
- A **Branch Secretary's own entries** can only be approved by their branch's **Branch DB Administrator** or by an **NA**, and vice versa.

### C.7 Pairing for the course (12 branches)

**The constraint that drives everything.** A branch approver can only approve records whose `branch_id` is **their own branch** (C.2). A participant can only create records for people **in their own branch** (entry search is scoped). So the two people in an approval pair must hold **BS or BA roles in the same branch**, which means their person records must sit in the same branch.

**Case 1: two participants per branch (BS + BA in each of the 12 branches; about 24 people).**
- **Minimum: 0 moves and 0 role changes**, if their accounts already hold those roles in those branches.
- Pair each branch's BS with its BA:
  - A enters a payment, training, volunteering record or donation for a branch member; B approves it.
  - B enters one; A approves it.
- Everyone both submits and approves, with no national involvement.

**Case 2: one participant per branch (12 people, each already BS or BA of a *different* branch).** Nobody has a same-branch partner, so the only approver for their records is an NA. The minimum set-up to make them self-sufficient is:

- **6 pairs, 6 moves, 12 role operations**, all done by a trainer holding **`national_db_administrator`**:
  1. For each pair (A in branch X, B in branch Y): **Authorizations** → remove B's role (**-- No Role --**). Needed because a role blocks a branch move, and only an NA can remove a BS or BA.
  2. **Persons** → B → **Edit** → set **Branch** = X and a **Division** in X → **Update Person**. Only an NA can do a cross-branch move.
  3. **Authorizations** → give B the complementary role in X: if A is BS, make B `branch_db_administrator`, and vice versa. The two roles have identical permissions, so either combination works. Two BSs in one branch is also technically allowed.
- **Result:** A and B are BS and BA of branch X and can approve each other. Per pair: 1 move + 1 role removal + 1 role assignment, so **6 moves and 12 role operations** in total.
- **Undo after the course:** repeat the same three steps in reverse.

**Recommended alternative: dedicated training accounts, no moves at all.**
- Instead of moving participants' real accounts, create **12 × 2 = 24 training accounts** with **Register New Person** (§E.2), directly in 12 chosen branches (two per branch). An NA then gives one `branch_secretary` and the other `branch_db_administrator` in each branch.
- **Cost:** 24 creations + 24 role assignments. No moves, no real accounts touched, easy to clean up.

**Pairs across branches can't approve each other.** Only an NA can approve for another branch.

**Exercise ideas per pair:**
- Each partner submits one **payment**, one **training**, one **volunteering** record and one **donation** (a DF-level set of records) for a member of their shared branch.
- The partner approves three of them and **rejects** one with a reason.
- The submitter finds the rejection in the bell, **Withdraws** a still-pending record, and re-enters it correctly.
- Try to approve your own record: it isn't in your list, and a direct URL gives 403.
- The trainer (NA) approves one record from each pair, to show the national escalation path.

**Campaigns cannot be paired.** Only an **NA** can approve them. A trainer with the NA role (not one who submitted the campaign) must approve every participant's campaign.

---

## D. Campaigns

### D.1 Entry points

- **Persons**: set filters, click **Filter**, then **Make campaign from filter**. Or use **Campaign filter wizard** → pick a goal → answer the questions → **Apply filter & close** → **Make campaign from filter**.
- **Organisations**: set filters, then **Make campaign from this filter**.
- **Task Forces**: **Msg** on a row (the audience is that task force's members).

Only people with `campaign_request_create` see these buttons: **NA, NAs, BS, BA**. Division assistants and Branch DB Assistants cannot create campaigns.

The goals in the Campaign filter wizard are: *Welcome newly registered persons*, *Re-engage dormant volunteers*, *Remind about expiring membership*, *Invite to upcoming training*, *Refresh stale first-aid training*, *Remind about expiring training certification*, *Appreciate donors*, *Send a newsletter*, *Fundraising appeal*.

### D.2 Audience filters available

Everything on **Persons**:
- **Search**; **Branch** / **Division** / **Red Cross Unit**; **Task Force**; **Gender**; **Age group**;
- **Supporting Members / Volunteers**; **Lifecycle Status**; **Profile Photo & Signature**; **Payments**; **Wants to contribute as**;
- **Org Representatives**; **Team Leaders**; **National ID (NIN)**; **Digital Activity**; **Email**; **Email Verification**; **Registration Source**;
- **Database Roles**; **Trainings**; **Training Expiry**; **First Aid Refresher**; **Donations**; **Campaign Messages**.

For organisations: **Branch**, **Status**, **Membership**.

The filter is saved with the campaign and shown at the top of step 1.

### D.3 The wizard (`CampaignWizardController`; views `campaigns/wizard/step-1…5`)

| Step | Exact labels | Notes |
|---|---|---|
| **1 Purpose** | **Purpose** (— Select a purpose —) · **Channel** · **Description of Campaign** · **Continue** | Purposes come from `CampaignPurposesSeeder` (below). Choosing a purpose pre-fills the message in step 4 and sets its default channel. **Channel options:** **Email (fallback to SMS)** (default) · **SMS only** · **Email only** · **Email and SMS (both)**. |
| **2 Audience** | **Delivery Check** (how many get email, and how many get SMS fallback), **Also target org representatives** (tick box), **Sample recipients** (Name · Email · Phone) · **Back** / **Continue** | If the audience is too big, go back to Persons and narrow the filter. |
| **3 Throttling** | **This campaign encourages people to call the branch** (tick box) · **Send window start** · **Send window end** · **Daily cap (optional)** | This step is the only "scheduling" there is. There is **no send-at date or time**: sending starts when HQ presses **Start sending**, and is then limited to the send window and daily cap. The default sending hours when no window is set are **08:00–20:00**. National daily caps for email and SMS also apply (Settings → Campaign Settings). |
| **4 Message** | **Email Content**: **From name**, **Reply-to email**, **Email subject**, **Email body** (**Write** / **Code** / **Preview**), **Insert placeholder…** · **SMS Content**: body with character and SMS-part counter, plus **Encourage recipient to visit the NRCS website (inserts a line at the end of the SMS body)** | **Placeholders:** `{{user.first_name}}`, `{{user.last_name}}`, `{{user.full_name}}`, `{{user.phone}}`, `{{user.email}}`, `{{user.branch}}`, `{{user.division}}`, `{{user.red_cross_unit}}`, `{{user.db_code_short}}`, `{{user.lifecycle}}`, `{{user.donations_summary}}`, `{{user.current_membership}}`, `{{user.time_since_last_first_aid}}`, `{{app.url}}` (`CampaignWizardController.php:388-402`). Unknown placeholders are rejected. Links may only point to the **Allowed link domains** set in Settings. A footer is added automatically: profile and login link plus unsubscribe text for email, an opt-out link for SMS. |
| **5 Review** | Summary (Audience, Channel, Send window, Daily cap) · **Preview as** (renders the message for a real recipient) · **Email** / **SMS** previews · **Readiness** · **What happens next** · **Final checklist**: "I have selected AUDIENCE (and filter), CHANNEL (email/sms), SEND WINDOW and DAILY CAP carefully." / "I have reviewed a few sample messages carefully. I think the message tone is good." / "I have considered the call window (call times) and confirm it's appropriate." · **Submit for approval** | Submitting sets the status to *proposed*. |

**Purposes** (`CampaignPurposesSeeder`; all 11 are active in the local DB):

| # | Name | Default channel |
|---|---|---|
| 1 | Membership Pre-Expiry Notice | Email (fallback to SMS) |
| 2 | Membership Post-Expiry Notice | Email (fallback to SMS) |
| 3 | Training Expiry Notice | Email (fallback to SMS) |
| 4 | Training Invitation | Email (fallback to SMS) |
| 5 | First Aid Refresher | Email (fallback to SMS) |
| 6 | Donation Appreciation | Email (fallback to SMS) |
| 7 | Fundraising Appeal | Email (fallback to SMS) |
| 8 | Newsletter | **Email only** |
| 9 | Welcome & Onboarding | Email (fallback to SMS) |
| 10 | Re-engagement — Dormant Users | Email (fallback to SMS) |
| 11 | Other | Email (fallback to SMS) |

**How the channel decides who gets what** (`app/Campaigns/Recipients/RecipientContact.php:30-59`):

| Channel | Who gets email | Who gets SMS |
|---|---|---|
| **Email (fallback to SMS)** | Everyone with a usable email (not opted out of email) | **Only** people without a usable email (none on file, or opted out of email) who have a valid Nigerian mobile number and haven't opted out of SMS |
| **Email and SMS (both)** | Everyone with a usable email | **Everyone** with a number (so possibly two messages per person) |
| **Email only** / **SMS only** | the one channel | — |

People who opted out of every channel the campaign uses are skipped.

### D.4 Approval and sending

- **Approval is required.** Only an **NA** (or an NAs with the direct permission) can approve, and never for their own campaign (§C.6). Nobody at branch level can approve campaigns.
- **The approval flow is national-only** (**Campaign Management**):
  1. **Proposed** → **Review** → **Approve** or **Reject**.
  2. **Approved/Queued** tab → **Queue** → **Build** (creates the recipient rows) → **Start sending**.
  3. The scheduler command `campaigns:send --batch=50` runs **every minute** (`routes/console.php:17-19`). It sends up to 50 recipients per campaign per run, within the send window and caps.
  4. There is also **Stop sending**, **Monitor** and a manual **run once** action.
- **Delivery tracking:**
  - **Monitor** page (NA).
  - **<CODE> Campaigns** cards: *Queued*, *Sending…*, *X / Y sent*, failures in red.
  - Dashboard: "Campaign messages sent — last 7 days".
  - Each person's **MESSAGES SENT** list.
  - **All Campaigns** report.
  - **Admin Activities → Messages**.

### D.5 What "send" does outside production

| Channel | What is used | Actually delivered? |
|---|---|---|
| **Email** | `LogEmailChannel`, **hard-wired** in `config/campaigns.php:7`, whatever the environment or `MAIL_MAILER` | **No.** Each email is written to `storage/logs/campaign_deliveries-YYYY-MM-DD.log` with a fake id `log-email-<uuid>`, and counted as a **success**. |
| **SMS** | `LogSmsChannel` when `CAMPAIGN_SMS_CHANNEL=log` (the default in `.env.example` and locally). `SmsLive247Channel` only when `CAMPAIGN_SMS_CHANNEL=smslive247`, and even then it stays in **dry run** unless `SMSLIVE247_DRY_RUN=false`. | **No**, unless both settings are deliberately switched. |

Consequences for training:
- Every recipient row is marked **sent**. Those people count as "contacted" in the planning tools and appear in their **MESSAGES SENT** list. **Training campaigns will change the numbers in the planning tools.**
- On a server where the scheduler cron is **not** running (for example a laptop), messages stay **Queued** until someone uses the manual **run once** action or runs `php artisan campaigns:send`.

---

## E. Sandbox and training readiness

### E.1 Demo data

- **There are no demo-data seeders or factories wired in.** `DatabaseSeeder` only seeds reference data:
  - branches, roles, permissions, super-admin, report months, settings, campaign purposes, division coordinates and First Aid training types;
  - `UserSeeder` and `UserTokenSeeder` are commented-out stubs.
  - Factories (`database/factories`) exist for tests only.
- **All person data comes from the one-off legacy import** (`app/Console/Commands/OldDbMigration/Migrate*`). The local database holds:

| Data | Count |
|---|---|
| People | **~303,000** |
| Branches | 37 |
| Divisions | 776 |
| Units | 2,659 |
| Payments | ~101,000 |
| Trainings | ~27,700 |
| Donations | ~3,900 |
| Volunteering records | ~118,000 |
| Campaigns | 3 |

- So the data is **fully realistic because it is real**: real names, phone numbers, emails, addresses and NINs.
  - ⚠ **Data-protection point to settle before the course.** Participants on a sandbox copy will see real personal data from every branch they have access to.
  - The Dashboard banner already says "This database holds confidential personal data…".
  - Either get approval for training on real data, or anonymise a copy. There is no anonymisation command in the codebase.
- **Useful fixture:** "NYSC Unit" (unit 1039, FCT branch) has 5,143 members (see CLAUDE.md).

### E.2 Creating accounts and credentials

- **Self-registration** (`/register`): the person sets their own password, and a **verification email** is sent. People who have an email but haven't verified it are **locked out** of every page by `EnsureEmailIsVerifiedOrAbsent` (`bootstrap/app.php`, alias `verified.or.absent`) until they verify.
- **Admin-created** (**Persons** → **Register New Person**, `users.create`; needs `add_user`: NA, NAs, BS, BA, BAs):
  - the admin types the password;
  - the email is marked **verified automatically** (`UserController@store`, "Mark email as verified since admin is entering it");
  - **no email is sent**, so the admin must pass on the credentials themselves.
- **Bulk creation:** **none.** There is no import command, CSV upload or batch seeder for accounts. Options for about 24 accounts:
  1. **By hand:** 24 × **Register New Person** (about 2–3 minutes each), then 24 × role assignment by an NA.
  2. **Scripted:** a one-off `php artisan tinker` script (or a small seeder written for the purpose) that creates the users with a known password, `email_verified_at = now()`, `policy_accepted_at` left empty, the branch and division set, then calls `assignRole(...)`. This would be new code or a manual step, not something that exists today.
- **Useful existing command:** `php artisan users:verify-email {id}` (`SimulateEmailVerification`) sets a user's email as verified, or clears it with `--unverify`.

### E.3 What can break or behave oddly in a training room

| Issue | Detail | Mitigation |
|---|---|---|
| **Login rate limit per IP** | `POST /login` has `throttle:5,1` (`routes/web.php:99`), i.e. **5 login attempts per minute per IP address** for everyone not yet logged in. A classroom behind one Wi-Fi or NAT shares **one IP**, so if 24 people log in at once most will get "Too Many Attempts" (HTTP 429). Registration is also 5 per minute per IP (`AppServiceProvider.php:55-57`). Phone-login steps allow 10 per minute per IP. | Stagger logins (about 5 per minute), or temporarily raise the limit on the training server. |
| **Email verification** | Self-registered users with an email can't get past the verification page. | Create accounts via **Register New Person** (auto-verified) or run `users:verify-email {id}`. |
| **Changing a password on Edit person** | Saving a new password (or a new email) on the **Edit** form **clears the verification flag** (`UserController@update`: "Reset email verification if email or password changed"). The person is then blocked until they verify by email. | After resetting a password this way, run `users:verify-email {id}`, or let the person use **Forgot your password?** if email delivery works. |
| **Verification and password-reset emails** | They go through a custom **SendGrid** notification channel (`ResetPassword` and `VerifyEmailNotification` → `['sendgrid']`), **not** through `MAIL_MAILER`. `SendGridService` reads `env('SENDGRID_API_KEY')` directly (`app/Services/SendGridService.php:16`). With no key, the email is **only written to the log and reported as sent**. ⚠ If the server has run `php artisan config:cache` (listed in `docs/DEPLOYMENT_CHECKLIST.md`), `env()` returns null outside config files, so **the key is ignored and no reset or verification email is ever delivered**, even when a key is set. | Test **Forgot your password?** on the training server beforehand. Plan to hand out passwords rather than rely on reset emails. |
| **Data-handling policy** | Anyone with a role is sent to `/policy/accept` on first use (`RequiresPolicyAcceptance`, appended to the `web` group). | Tell participants to expect it. |
| **Password confirmation** | **Authorizations**, **Settings**, **Membership Fees** and **Training Types** ask for the password again (`password.confirm`). | — |
| **2FA** | **None** in the codebase. | — |
| **Maintenance login gate** | `MAINTENANCE_LOGIN_GATE=true` lets only `MAINTENANCE_ALLOWED_USER_IDS` log in; others are sent to `/maintenance`. `php artisan maintenance:force-logout` ends everyone else's sessions. | Make sure it is **off**, or that all training account ids are on the allow-list. |
| **Campaigns** | Nothing is delivered (§D.5). The messages count as "sent", so they change the planning-tool numbers. Sending needs the scheduler cron, or the manual run-once. | Good for practice. Warn that the counts will change. |
| **Paystack** (member self-payment, `/make-payment`) | Needs `PAYSTACK_SECRET_KEY` and `PAYSTACK_PUBLIC_KEY`; they are empty locally, so starting a payment fails. With Paystack **test keys** (`sk_test_…`), the test checkout and callback work, and the payment is **auto-approved** with no four-eyes step. The server webhook (`/webhooks/paystack`) only works on a publicly reachable URL. | Use test keys only. Never live keys on a training server. |
| **Branch approval scope** | Pairs must share a branch (§C.7). | Set up pairs before the course. |
| **Dashboard caching** | The statistics cards are cached for an hour and shared by everyone viewing that branch, so new approvals won't show at once. | Click **Refresh now**. Housekeeping counts are live. |
| **Two people editing the same record** | Nothing specific found. | — |
| **Queue** | `QUEUE_CONNECTION=database`. Nothing in the flows above depends on a queue worker, except whatever the scheduler runs. | Make sure `schedule:run` is in cron on the training server. |
