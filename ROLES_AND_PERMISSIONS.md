# Roles and Permissions — NRCS Membership and Volunteer Database

*Who can do what in the database. Based on a read-only review of the application code on 10 October 2026.*

Every person in the database has a home **branch** and **division**. Volunteers also belong to a
**Red Cross Unit**. Staff who work on the database get one **role**. The role decides what they can
do, and their own branch or division decides **where** they can do it. A person holds one role at
most. People without a role (ordinary volunteers, members and supporting members) can only use
their own profile.

---

## 1. Roles

| Internal name | Display name | What this role is for | Geographic scope | Who can assign it |
|---|---|---|---|---|
| `super-admin` | System Administrator | A safeguard account, not a working account. Its only job is to appoint and remove National Database Administrators. It cannot enter, edit or approve anything, has no personal profile, and otherwise can only look at data like an Observer. | National (view only) | Nobody in the application. These accounts are created by the system's technical set-up from a fixed list of email addresses (the Secretary General and two others). |
| `national_db_administrator` | National Database Administrator | The top working role. Manages records for the whole country, approves records, appoints national and branch leadership roles, runs national messaging campaigns, prints ID cards, changes system settings, and is the only role that can permanently anonymise an archived account. | National | System Administrator only |
| `national_db_assistant` | National Database Assistant | Enters and corrects records nationwide under the National Administrator's direction. Cannot approve records or delete approved financial and activity records. The National Administrator can give an assistant up to three extra abilities: print ID cards, print certificates, and approve messaging campaigns. | National | National Database Administrator |
| `observer_national_level` | National Observer | Read-only access to national data, reports and the audit log. Cannot add, change or delete anything. | National (view only) | National Database Administrator |
| `branch_secretary` | Branch Secretary | Runs the database for one branch: manages records, approves records entered by others, prints certificates, requests messaging campaigns, and appoints the branch's assistants. | Own branch (all its divisions) | National Database Administrator |
| `branch_db_administrator` | Branch Database Administrator | Same abilities as the Branch Secretary. | Own branch (all its divisions) | National Database Administrator |
| `branch_db_assistant` | Branch Database Assistant | Registers people and enters payments, donations, volunteering hours and training records for the branch. Cannot approve, delete approved records, or appoint anyone. | Own branch | Branch Secretary or Branch Database Administrator of that branch |
| `division_db_assistant_finance` | Division Database Assistant (Finance & Operations) | Enters membership payments, donations, volunteering hours and training records for one division. Can look up people in the division but cannot register or edit them. | Own division | Branch Secretary or Branch Database Administrator of that branch |
| `division_db_assistant_operations` | Division Database Assistant (Operations) | Enters volunteering hours and training records for one division. Handles no money. Can look up people in the division but cannot register or edit them. | Own division | Branch Secretary or Branch Database Administrator of that branch |
| *(no role)* | Volunteer, Member, Volunteer & Member, Supporting Member | An ordinary person in the database. Can see and update their own profile, pay their membership fee or donate online, and see their own Red Cross Unit and task forces. | Own record only | — (anyone who registers or is registered by staff) |
| *(not logged in)* | Public | Can read the public information pages, register, and check that an ID card or certificate is genuine. | — | — |

**There is no Red Cross Unit-level role.** Team leaders and assistant team leaders are *positions*
inside a unit, not database roles. They get two extra abilities: paying the unit's annual fee
online and opening the unit's profile page. Otherwise a team leader has the same access as any
other volunteer.

---

## 2. Permission matrix

**Column key:** SA = System Administrator · NDA = National DB Administrator · NAs = National DB
Assistant · OBS = National Observer · BS = Branch Secretary · BDA = Branch DB Administrator · BAs =
Branch DB Assistant · DF = Division Assistant (Finance & Operations) · DO = Division Assistant
(Operations) · Self = logged-in volunteer/member without a role · Public = not logged in.

**Cell key:** **Full** = can do this anywhere in the country · **Own area** = can do this, but only
in their own branch (branch roles) or division (division roles) · **View** = can look but not
change (whole country unless marked "own area") · **Own record** = only for themselves ·
**Request only** = can submit, but someone else must approve · **Approve** = can approve what
others submit · **—** = no access.

| Functional area | SA | NDA | NAs | OBS | BS | BDA | BAs | DF | DO | Self | Public |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Register a new volunteer or member (staff form) | — | Full | Full | — | Own area | Own area | Own area | — | — | — | Own record ¹ |
| Search and view person records | View | Full | View | View | Own area | Own area | View (own area) | View (own area) | View (own area) | Own record | — |
| Edit person records | — | Full ² | Full ³ | — | Own area ² | Own area ² | Own area ³ | — | — | Own record ⁴ | — |
| Move a person to another branch or division | — | Full | Full | — | Own area ⁵ | Own area ⁵ | Own area ⁵ | — | — | Own record ⁴ | — |
| Archive a person (one at a time) | — | Full | Full | — | Own area | Own area | Own area | — | — | Own record ⁶ | — |
| Archive tool (bulk-archive long-inactive people) | — | Full | Full | — | Own area | Own area | — | — | — | — | — |
| Permanently anonymise an archived account | — | Full ⁷ | — | — | — | — | — | — | — | — | — |
| Branch and division details (contacts, map info) | — | Full | Full | — ⁸ | Own area | Own area | — ⁸ | View (own area) | View (own area) | — | View ⁹ |
| Red Cross Units — create, edit, close | — | Full | Full | — | Own area | Own area | — | — | — | — | — |
| Red Cross Units — view | View | Full | Full | View | Own area | Own area | View (own area) | View (own area) | View (own area) | Own record ¹⁰ | — |
| Courses — create, edit, cancel, close ²³ | — | Full | Full | — | Own area | Own area | — | — | — | — | — |
| Courses — add participants and facilitators, record results ²³ | — | Full | Full | — | Own area | Own area | Own area | Own area | Own area | — | — |
| Courses — view ²³ | View | Full | Full | View | Own area | Own area | Own area | Own area | Own area | — | — |
| Task forces — create, edit, manage members | — | Full | Full | — | Own area | Own area | — | — | — | — | — |
| Task forces — view | View | Full | Full | View | Own area | Own area | View (own area) | View (own area) | View (own area) | Own record ¹¹ | — |
| Organisations (corporate members) | — | Full | Full | View | Own branch | Own branch | Own branch (no archive/restore) | — | — | Own record ¹² | — |
| Membership payments — enter | View | Request only | Request only | View | Request only | Request only | Request only | Request only | — | Own record ¹³ | — |
| Donations — enter | View | Request only | Request only | View | Request only | Request only | Request only | Request only | — | Own record ¹³ | — |
| Volunteering hours — enter | View | Request only | Request only | View | Request only | Request only | Request only | Request only | Request only | — | — |
| Training records — enter | View | Request only | Request only | View | Request only | Request only | Request only | Request only | Request only | — | — |
| Approve payments, donations, volunteering and training | — | Approve | — | — | Approve (own area) | Approve (own area) | — | — | — | — | — |
| Delete an approved payment, donation, volunteering or training record | — | Full | — | — | Own area | Own area | — | — | — | — | — |
| Messaging campaigns (email / SMS) — create and submit | — | Request only | Request only | — | Request only (own area) | Request only (own area) | — | — | — | — | — |
| Messaging campaigns — approve and send | — | Approve | Approve ¹⁴ | — | — | — | — | — | — | — | — |
| Unsubscribe from email / SMS | — | — | — | — | — | — | — | — | — | Own record | Own record ¹⁵ |
| ID cards — view print lists and print history | View | Full | View | View | View (own area) | View (own area) | View (own area) | View (own area) | View (own area) | — | — |
| ID cards — print, reject a member's signature | — | Full | Full ¹⁴ | — | — | — | — | — | — | Own record ¹⁶ | — |
| Certificates — view lists and print history | View | Full | View | View | View (own area) | View (own area) | View (own area) | View (own area) | View (own area) | — | — |
| Certificates — print | — | Full | Full ¹⁴ | — | Own area | Own area | — | — | — | — | — |
| Verify an ID card or certificate (QR code) | — | — | — | — | — | — | — | — | — | — | View |
| Reports — statistics and maps | View | View | View | View | View ¹⁷ | View ¹⁷ | View ¹⁷ | View ¹⁷ | View ¹⁷ | Own record ¹⁸ | View ⁹ |
| Reports — lists of named people and financial detail | View | Full | Full | View | Own area | Own area | Own area ¹⁹ | Own area ¹⁹ | — ¹⁹ | — | — |
| Reports — export to spreadsheet (CSV) | View | Full | Full | View | Own area | Own area | Own area | Own area | Own area | — | — |
| System settings (fee list, training types, signatures, Data Protection Officer details, site text) | — | Full ²⁰ | — | — | — | — | — | — | — | — | — |
| Appoint and remove role holders | Full ²¹ | Full ²¹ | — | — | Own area ²¹ | Own area ²¹ | — | — | — | — | — |
| Audit log | — | View | View | View | View (own area) | View (own area) | — | — | — | — | — |
| Tutorials (training lessons for staff) | — ²² | Full | Full | Full | Full | Full | Full | Levels 0–2 | Levels 0–2 | — | — |

**Footnotes**

1. Anyone can register themselves on the public form, as a volunteer or as a member. They must accept the Code of Conduct and the data-protection commitments.
2. Administrators can edit anyone in their scope. Branch administrators can never edit a national-level role holder.
3. Assistants cannot edit a person who holds any role. That protects administrators' accounts from being changed by staff below them.
4. A person can update their own contact and personal details and change their own password. They can change their own branch and division only if they are not yet in a Red Cross Unit and hold no branch or division role.
5. Branch staff can move a person between divisions of their own branch, but cannot move anyone to another branch. A person who holds a branch or division role cannot be moved to another branch until that role is removed.
6. A person can archive their own account by typing "archive" to confirm. Role holders cannot archive themselves, and nobody can archive a role holder, until the role is removed.
7. Only for accounts that are already archived and hold no role. The administrator must confirm that they have consulted the Data Protection Officer and must re-enter their password. Separately, the system anonymises accounts automatically once they have been archived for 7 years.
8. The National Observer, the System Administrator and the Branch DB Assistant are not given access to the branch and division administration pages. They still see branch and division figures in reports.
9. The public home page shows a map with branch and division contact details and some public summary figures.
10. Every unit member can open "My unit": the unit's members (names and photos, no phone numbers), a unit report and maps. Only the team leader and assistant team leader can open the unit's profile page and pay its annual fee.
11. A volunteer can open the task forces they belong to and see their fellow members.
12. People linked to an organisation can open that organisation's page and pay its membership fee or make a donation on its behalf.
13. Self-service online payment through Paystack. A person can pay their own membership fee (optionally with an ID card), donate, or pay for an organisation they are linked to. There is no guest payment or donation: the payer must be logged in and have an email address. Online payments are approved automatically once Paystack confirms the money was received.
14. Only if the National DB Administrator has given that assistant this extra ability. These three extras (print ID cards, print certificates, approve campaigns) can be given to National DB Assistants only.
15. Each message contains a personal unsubscribe link that works without logging in.
16. A person uploads their own photo and signature for the ID card, and can order an ID card together with their online membership payment.
17. Statistics, maps and comparison reports show figures for **all** branches to every staff role, as totals only (counts, sums, trends). Comparing branches is intended.
18. Volunteers see statistics and maps for their own unit.
19. Lists that show individual payments or donations need finance access: the Division Assistant (Operations) cannot open them.
20. Changing settings needs the password to be re-entered.
21. See section 4 for exactly who can appoint whom.
22. The System Administrator sees the tutorials page, but all lessons are locked.
23. Courses (being built, since 2026-10-23): permissions `add_courses`, `edit_courses`, `manage_course_participants`, `view_courses`. A course has an organising branch, or none for a national course, and optionally an organising division. **View:** national staff see all courses; branch staff their branch's courses; division staff their branch's courses except those organised by another division. Everyone sees national courses. **Create:** national staff for any branch or nationally; branch staff only for their own branch. **Edit and cancel:** national staff any course; branch staff only their own branch's courses, not national ones. Closed and cancelled courses cannot be changed. Participants may come from any branch. Closing a course creates a training record for each participant who passed; these go through the normal training approval (section 3.1). **Participants and facilitators:** everyone who sees a course sees all its people (name, branch and division only). The organisers may add anyone from any branch; other staff with the permission add and remove only people from their own branch or division. The rules for results follow in the next build step.
**Organisations** (since 2026-10-11): each organisation belongs to a branch and a division. Branch staff manage their own branch's organisations and may move one between its divisions; only national staff move one to another branch. Division staff have no organisation access, and organisation payments and donations are hidden from them in lists, pages, reports and the dashboard.

---

## 3. Approval workflows

### 3.1 Payments, donations, volunteering hours and training records ("four-eyes rule")

Every new membership payment, donation, volunteering entry and training record starts as
**pending**. Pending records do not count anywhere: they are not in totals, reports or
certificates, and do not change a person's membership status, until a second person approves them.

| | Who |
|---|---|
| **Can submit** | Anyone with data-entry rights for that kind of record: National DB Administrator and Assistant, Branch Secretary, Branch DB Administrator, Branch DB Assistant, Division Assistant (Finance) (all four kinds), Division Assistant (Operations) (volunteering and training only). |
| **Can approve or reject** | Branch Secretary and Branch DB Administrator for records in **their own branch**. National DB Administrator for records in **any branch**, so a branch with only one approver is never stuck. |
| **Cannot approve** | All assistants, the National Observer and the System Administrator. |

**What prevents self-approval**
- The system refuses to let anyone approve or reject a record they submitted themselves. This is
  checked on the approval list, again when the button is pressed, and again when the record is
  saved.
- A record outside the approver's branch is refused.
- The System Administrator is deliberately outside the approval chain and has no "override"
  power, so the four-eyes rule cannot be bypassed from above.

**Other rules**
- A rejection needs a written reason. Rejected records stay hidden from totals.
- The person who submitted a record can **withdraw** it while it is still pending.
- Approved records cannot be edited. A mistake is corrected by deleting the record and entering
  it again, and the new entry needs approval again. Only Branch Secretaries, Branch DB
  Administrators (own branch) and the National DB Administrator can delete approved records.
- Approving a record for an **archived** person asks the approver to confirm before the person is
  reactivated. Bulk approval never reactivates archived people; they are listed for individual
  handling. Anonymised accounts are never reactivated.
- **Online payments** made by members through Paystack are approved automatically once Paystack
  confirms the money was received. The payment provider's confirmation stands in for the second
  person.

### 3.2 Messaging campaigns (email and SMS)

| | Who |
|---|---|
| **Can request** | National DB Administrator, National DB Assistant (national audience); Branch Secretary and Branch DB Administrator (audience limited to their own branch). |
| **Can approve, reject and send** | National DB Administrator, and any National DB Assistant given the "approve campaigns" ability. |

**What prevents self-approval:** nobody can approve or reject a campaign they submitted
themselves. Approval, starting, stopping and test sends are recorded in the audit log, together
with the number of recipients and the projected SMS cost.

### 3.3 Actions with other safeguards instead of a second approver
- **Anonymisation:** confirmation that the Data Protection Officer was consulted, plus the administrator's password.
- **Settings, fee list, training types, Authorizations page:** the password must be re-entered.

---

## 4. Role assignment rules

Roles are given and removed on the **Authorizations** page. Opening it requires the password to be
re-entered.

| Who | Can appoint and remove |
|---|---|
| **System Administrator** (Secretary General and two others) | National Database Administrators, and nothing else. |
| **National Database Administrator** | National Database Assistants, National Observers, Branch Secretaries, Branch Database Administrators. Can also give National DB Assistants the three extra abilities (print ID cards, print certificates, approve campaigns). |
| **Branch Secretary / Branch Database Administrator** | Branch Database Assistants, Division Database Assistants (Finance and Operations), in their own branch only. |
| **Everyone else** | Nobody. |

**Deliberate restrictions**
- **Branch and division assistants are appointed by the branch, not nationally.** The National DB
  Administrator cannot appoint, change or remove them. The branch knows its own staff.
- **To remove or change a role, you must be allowed to grant it.** For example, the System
  Administrator can only remove National DB Administrators, and National DB Administrators cannot
  remove each other: that goes through a System Administrator.
- **Nobody can change their own role.**
- **Branch leaders cannot change each other's roles.** A Branch Secretary cannot demote a Branch
  DB Administrator, or the other way round. Only the National DB Administrator can.
- **Branch administrators cannot touch national-level role holders.**
- **The last National DB Administrator cannot be removed.** Another one must be appointed first.
- **One role per person.**
- **System Administrator accounts cannot be created or granted from inside the application.** They
  are created only by the technical set-up, from a fixed list of email addresses. If such an
  account's email is changed to one not on the list, the System Administrator power is removed
  automatically.
- Every role change is written to the audit log: who changed it, the old role and the new role.

---

## 5. Notable restrictions and safeguards

**Protection against people giving themselves more power**
- The System Administrator has no hidden "can do everything" switch. It exists only to appoint
  National DB Administrators.
- Assistants cannot edit the accounts of people who hold a role, and branch staff cannot edit
  national administrators.
- Role holders cannot be archived or anonymised while they hold a role.

**Geographic limits**
- Branch staff see and change only people and records in their own branch. Division assistants
  see only their own division.
- Branch staff cannot move people out of their branch. People holding a branch or division role
  cannot be moved to another branch until the role is removed.
- Branch campaigns can only reach people in that branch.
- Certificates can only be printed for records in the printer's own area, and certificates for a
  training type marked "HQ only" only by national roles. This is checked on the server when
  printing and when marking as printed (since 2026-10-23).
- Only branch-wide totals (statistics) are shown across branches. Lists of named people stay within
  the viewer's area.

**Data protection (NDPA 2023)**
- **Consent:** every user must confirm the Code of Conduct and the data-protection commitments
  before using the system. This includes people who were imported from the old system.
- **Staff data-handling policy:** every role holder must accept the staff data-handling policy
  before they can work.
- **Data minimisation:**
  - Phone numbers are hidden on the "My unit" page that all unit members can see.
  - Photos are shown only to staff with access to that person, to fellow unit or task-force
    members, and to members of a branch for that branch's public contacts.
  - Message logs mask phone numbers and email addresses and never store message text.
- **Encryption:** the National Identification Number and the free-text "personal information"
  are stored encrypted.
- **Retention:** archived accounts are anonymised automatically after 7 years (name, contacts, ID
  numbers, photos and signatures removed; anonymous statistics kept). Manual anonymisation earlier
  than that requires consulting the Data Protection Officer.
- **Right to leave:** members can archive their own account.
- **Audit log:** records administrative changes such as role changes, archiving, anonymisation,
  setting changes, deletions and campaign sends. Personal identifiers are automatically removed
  from the logged details.
- **Data Protection Officer** contact details are kept in the settings and shown on the public
  Privacy Policy page and the Authorizations page.

**Sensitive actions need the password again:** opening the Authorizations page, changing settings,
fee lists and training types, and anonymising an account.

**Login protection:** limits on repeated login attempts and repeated registrations. Users with an
email address must verify it.

---

## 6. Inconsistencies or gaps found

*(For the database owner — not for IFRC.)*

**Likely security gaps**

1. **National admin can set the password of a System Administrator account.**
   `UserPolicy::update` returns `true` for any target when the actor is a
   `national_db_administrator`. `UserController::update` accepts `password` and `email`.
   System Administrator accounts are hidden from the user list, but `/users/{id}/edit` is
   reachable by URL. A National DB Administrator could therefore set a System Administrator's
   password, log in as them and appoint further National DB Administrators. That would bypass the
   rule that national admins cannot appoint or remove each other. The same applies to other
   National DB Administrators' accounts. *(Read from code; not tested.)*
2. **Branch admins can edit, and set the password of, a branch peer.** `UserPolicy::update` lets
   `branch_secretary` / `branch_db_administrator` edit anyone in their branch who is not national,
   including the other branch leader. This sits awkwardly with the rule in `updateRoles` that
   blocks branch peers from changing each other's roles.
3. **Role assignment is not limited to the actor's branch on the server.**
   `UserController::updateRoles` checks the role being granted, but not that the target is in the
   actor's branch. Only `searchUsersForRoles` filters by branch. A Branch Secretary posting another
   branch's `user_id` directly could appoint a Branch/Division Assistant in another branch.
   `editRoles?user_id=` also shows any user's current role and permissions.
4. **New records are not checked against the clerk's area on save.** `store()` in
   `MembershipPaymentController`, `ActivityController`, `TrainingController` and
   `DonationController` takes `user_id`, `branch_id` and `division_id` from the request. It does
   not check that the person is inside the clerk's scope. Only the form's person search is scoped.
   Approval (scoped to the record's `branch_id`) is the only backstop. *(Not tested.)*
5. **Settings sub-pages are open to every admin-panel role.** `membership-fees`, `training-types`
   (only `password.confirm`), `activity-types` and `task-force-types` (nothing extra) sit inside
   `can:manage-admin-panel` only. They are linked only from the Settings page (`change_settings`),
   but any role holder, including Observers and division assistants, can reach them by URL and
   create, edit or delete fee types or training types.
6. ~~**Organisation permissions are seeded but not enforced.**~~ Fixed 2026-10-11: every
   organisation route needs its own permission (`view_organisation` for the list and page,
   `add_`/`edit_`/`archive_`/`restore_organisation` for the actions, `add_donations` for the
   donation form), and `Organisation::isInScopeOf()` limits every page to national staff (all) and
   branch staff (own branch). Division staff get 403. `observer_national_level` has
   `view_organisation` only (read-only); super-admin has no organisation access.

**Inconsistencies**

7. **`remove_user` route has no controller method.** `users.destroy` points to
   `UserController::destroy`, which does not exist (a 500 error if called). Nothing links to it.
8. **`edit_payments`, `edit_volunteering`, `edit_trainings`, `edit_donations` are unused.** No
   edit routes exist for those records, and `Approvable::resetApprovalOnEdit()` is never called.
   Correcting means delete and re-enter.
9. **Red Cross Units menu item** is gated by `@can('view_user')`, but the route needs
   `view_red_cross_unit`. No current role has one without the other, so this is harmless today.
10. **Observer, System Administrator and Branch DB Assistant lack `view_branch_information` /
    `view_division_information`.** So they have no Branches/Divisions pages, although they can see
    unit and user data for those areas. Probably unintended for a "national observer".
11. **Branch role descriptions say "reports for your branch"** (`config/role_descriptions.php`),
    but aggregate reports (members, volunteers, financial trends, lifecycle, admin activities,
    branches, units, maps) are unscoped for every `view_reports` holder. The division descriptions
    correctly say "all NRCS branches". `AdminActivityReportController` and
    `LifecycleReportController` say this is intentional. Make the wording consistent.
12. **Tutorial level 2 ("Branch Administration")** is open to division users in
    `config/tutorials.php` (`min_access` includes `division`). Decisions.md says level 2 is for
    branch and national only.
13. **`/lifecycle/*` pages** need only login, so any volunteer can open them. They show national
    counts of active, dormant and archived people (comment says "v1: global count. We'll scope
    later").
14. **`/users/search` (JSON)** needs only `manage-admin-panel` and returns names and DB numbers of
    all non-archived people nationwide, to division assistants as well.
15. **Approval escalation:** the role description says national approves "records submitted by
    other staff". In fact `approve_*` is a routine national capability across all branches, not
    escalation-only (documented as accepted in Decisions.md 2026-06-22).
16. **Division-role holders can be moved between divisions within a branch** without removing the
    role. Known and accepted in Decisions.md (2026-07-20), but worth remembering.
17. `User::isBranchAdmin()` / `isDivisionAdmin()` still check legacy role names `branch_admin` /
    `division_admin`, which no longer exist (harmless, but dead).

---

## 7. Sources

**Section 1 — Roles**
- `database/seeders/RolesTableSeeder.php`, `database/seeders/PermissionsTableSeeder.php`, `database/seeders/SuperAdminSeeder.php`
- `config/role_descriptions.php`, `config/superadmin.php`
- `app/Models/User.php` (`NATIONAL_ROLES`, `BRANCH_ROLES`, `DIVISION_ROLES`, `getAccessLevel()`, `getScopedId()`, `getAssignableRoles()`)
- `app/Models/RedCrossUnit.php` (`isLedBy()`, `isViewableBy()`)
- `Decisions.md` (2026-06-10 restricted super-admin model; 2026-09-29 entries)

**Section 2 — Permission matrix**
- `routes/web.php` (all `can:` middleware, public routes)
- `app/Policies/UserPolicy.php`
- `app/Http/Controllers/UserController.php`, `ProfileController.php`, `PaystackPaymentController.php`, `MembershipPaymentController.php`, `ActivityController.php`, `TrainingController.php`, `DonationController.php`, `RedCrossUnitController.php`, `TaskForceController.php`, `OrganisationController.php`, `BranchController.php`, `DivisionController.php`, `IdCardController.php`, `CertificateController.php`, `DormantUserController.php`, `LogController.php`, `PhotoController.php`, `TutorialController.php`, `UnsubscribeController.php`, `Auth/RegisterController.php`
- `app/Http/Controllers/Reports/*` (scoping in each report controller)
- `app/Services/OnlinePaymentEligibility.php`, `app/Services/UserAnonymizer.php`
- `resources/views/components/layouts/admin.blade.php` (menu), `resources/views/settings/index.blade.php`
- `config/tutorials.php`

**Section 3 — Approval workflows**
- `app/Models/Concerns/Approvable.php`
- `app/Http/Controllers/Concerns/HandlesRecordApproval.php`
- `app/Http/Controllers/CampaignAdminController.php`, `MessagingCampaignController.php`, `CampaignMyController.php`, `CampaignWizardController.php`
- `app/Http/Controllers/PaystackPaymentController.php` (`markApprovedViaGateway`)
- `Decisions.md` (2026-06-22 two-step approval workflow)

**Section 4 — Role assignment rules**
- `app/Http/Controllers/UserController.php` (`editRoles`, `updateRoles`, `searchUsersForRoles`)
- `database/seeders/PermissionsTableSeeder.php` (`authorize_*` permissions)
- `app/Observers/UserObserver.php`, `database/seeders/SuperAdminSeeder.php`
- `Decisions.md` (2026-09-29: role removal needs authority; super-admin only by seeder; assistants appointed at branch level)

**Section 5 — Restrictions and safeguards**
- `app/Http/Middleware/EnsureConsentConfirmed.php`, `RequiresPolicyAcceptance.php`, `RedirectSuperAdmins.php`, `EnsureEmailIsVerifiedOrAbsent.php`
- `bootstrap/app.php`
- `app/Http/Controllers/UserController.php` (branch-move rules, archiving, anonymisation)
- `app/Policies/UserPolicy.php` (photo visibility exceptions)
- `COMPLIANCE.md`, `Decisions.md` (2026-07-20 branch moves; 2026-09-29 delivery logs; 2026-10-02 audit-log redaction; 2026-10-08 NDPA entries)

**Section 6 — Inconsistencies and gaps**
- `app/Policies/UserPolicy.php` (`update`)
- `app/Http/Controllers/UserController.php` (`update`, `updateRoles`, `editRoles`, `search`; no `destroy`)
- `app/Http/Controllers/MembershipPaymentController.php`, `ActivityController.php`, `TrainingController.php`, `DonationController.php` (`store`)
- `app/Http/Controllers/MembershipFeeController.php`, `TrainingTypeController.php`, `ActivityTypeController.php`, `TaskForceTypeController.php`, `OrganisationController.php`, `LifecycleController.php`
- `routes/web.php`, `resources/views/components/layouts/admin.blade.php`
- `config/role_descriptions.php`, `config/tutorials.php`, `Decisions.md`
