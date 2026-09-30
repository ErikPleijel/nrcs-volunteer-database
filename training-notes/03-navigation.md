# 03 — Welcome page, menus and navigation

Written for the branch secretary / branch DB administrator training-of-trainers. It is based on the codebase as of 2026-09-30 (`main`, `34b445a`). This was a read-only review: no code was changed.

Labels are given exactly as users see them. Role names are the real names from the code (`RolesTableSeeder`). Which role holds which permission was read from the **local** database (`role_has_permissions`); re-check on the VPS if its seeders have diverged.

---

## 1. Welcome page (`/`)

| | |
|---|---|
| Route | `welcome` — `GET /` → `WelcomeController@index` → view `welcome.blade.php` (public layout `components/layouts/app.blade.php`) |
| Middleware | `web` only. It is public, and the same URL is used before and after login. |
| Data | Controller: `WelcomeController`. Services: `MembershipStatsService`, `RedCrossUnitStatsService`, `TaskForceStatsService`, `ActivityStatsService`, `BranchStatsService`. The map uses the Leaflet 1.9.4 library with OpenStreetMap tiles. |

### 1.1 Before vs. after login

The page is the **same for guests and logged-in users**, except for three things:

| Part | Guest | Logged in |
|---|---|---|
| Top bar (see §4) | **Login** / **Register** | Notifications bell, "Welcome <full name>", **Logout**, and **Admin** if the user has a role |
| Super-admin banner | — | A **super-admin** sees: "You are signed in as a Super Administrator. Your task is appointing National Database Administrators. Please proceed to the Admin page." and a **Go to Admin page** button (→ `/reports`). |
| Personal welcome section | — | "Welcome <First> <Last>!", then one status box, then "Take a moment to check your profile…" and a **Go to My Profile** button (→ `/profile`). The status box is chosen in this order: **Registration pending** (lifecycle is pending engagement) → **You're a volunteer** + unit name (contributor type Volunteer or Volunteer & Member) → **Your membership:** + fee name (valid personal membership) → **Your membership has expired** + expiry date → **Become a member or volunteer** ("You've marked that you're interested in membership… please make your membership payment") → **Membership status: Not a member.** A purple **You're a contact for:** <organisations> box is added if the person is linked to an organisation. |

### 1.2 Sections, top to bottom

1. **Banner**: "⚠️ Development Version — This site is for testing only — the real database will be live on the NRCS website soon." Two more boxes, "RESTRICTED ACCESS…" and "👋 Welcome to the Sandbox!", are in the page but hidden (`display:none`).
2. **Heading**: "Welcome to the Nigerian Red Cross Volunteer Management System", followed by an intro paragraph.
3. *(Super-admin banner: logged-in super-admins only.)*
4. **Our Branch Network**: the text "We have branches in all 37 states of Nigeria, the Federal Capital Territory, and are present in all 774 local government areas across the federation.", then the **map** (§1.3), then a hint box: "**Find your local Red Cross** — tap a marker to see branch details. Explore stats, contacts, and divisions near you."
5. *(Personal welcome section: logged-in users only, see §1.1.)*
6. **Ready to Make a Difference?** (§1.4).
7. **Our Impact**: four national statistics tiles (§1.5).
8. **How We Help**: three fixed text cards: *Emergency Response*, *First Aid Training*, *Community Support*.
9. **Spread the Word**: share buttons for WhatsApp, Facebook and X. They share the Welcome page URL with the text "Red Cross Nigeria".
10. **Footer**: emblem, the motto (setting `site.motto`, default "Serving Humanity"), "Motto of the Nigerian Red Cross Society", **Contact Information** and **Quick Links** (both HTML blocks edited in National Settings: `site.footer_contact_html` and `site.footer_quick_links_html`), and "Copyright © Nigerian Red Cross Society. All Rights Reserved."

### 1.3 The map

**How markers are generated**

- **Branch markers.** The controller loads `Branch::active()->withCoordinates()`, i.e. active branches whose latitude and longitude are both filled in. The local database has 37. Each branch gets:
  - a round **NRCS logo** marker (`images/NRCS_logo.jpg`);
  - a floating red **branch-name label** above it.
- **Starting view.** The map is centred on Nigeria at 9.08, 8.68. The starting zoom is 6, or 5 on screens 768 px wide or smaller.
- **Activating the map.** Scroll-wheel zoom is off on desktop, and dragging is off on mobile, until the user clicks or taps the map. A hint reads "Click to activate scroll" (desktop) or "Tap to interact" (mobile). Leaving the map switches it off again.
- **Zoom buttons** are in the bottom-right corner.
- **Branch statistics** are computed on every page load by `BranchStatsService::getAllBranchesStats()` and embedded in the page. They are not cached.

**Clicking a branch marker** opens a popup titled **Branch details**:

| Line | Source / definition |
|---|---|
| Branch name | `branches.name` |
| **Volunteers:** N | Counts **every user whose Red Cross Unit belongs to a division of this branch**. This is deliberately looser than the standard "volunteer" definition: it ignores lifecycle status, so **archived people are counted too**, and it ignores whether the unit is active. |
| **Members:** N | Distinct users with an **approved, not deleted, unexpired** membership payment recorded in this branch. This **includes volunteers who also pay**, so it is **not** the same as "Supporting Members" in Our Impact. |
| **Projects:** N | The manually entered `branches.projects` field, edited on the branch's Edit page. It is a plain number, not a calculation. |
| **N Divisions:** + list of names | All divisions of the branch, comma-separated, or "No divisions available". |
| **Zoom to divisions** button | See drill-down below. |
| Telephone / email | `branches.telephone` and `branches.email`, shown only if filled in. |

The popup does **not** show Red Cross Unit, task force or activity-hour counts, although the service calculates them (`red_cross_units`, `task_forces` and `activity_hours` are computed but unused).

**Drill-down from branch to division** (clicking **Zoom to divisions**):
1. The map zooms to level 12 on the branch's own coordinates.
2. Division markers from any previously opened branch are removed.
3. The page fetches `GET /api/branches/{branch}/divisions` (`DivisionController@getDivisionsForBranch`; public, no login). It returns each division's id, name, physical address and coordinates. The result is cached in the browser for that page view.
4. Each division that has coordinates gets a marker: a small red cross in a circle next to the division name. The local database has coordinates for 774 of 776 divisions; divisions without coordinates are skipped. The map then zooms to fit all the division markers.

**Clicking a division marker** opens a popup with:
- the **division name**;
- its **physical address**, if filled in;
- a list of **Red Cross Units**, loaded on demand from `GET /api/divisions/{division}/units` (`DivisionController@getDivisionWithUnits`; public). Each line is the **unit name** and "**N volunteers**", where N = users in that unit whose lifecycle status is **not archived** (active, dormant or pending). Units with 0 are left out, and the list is sorted by count, highest first.
  - The unit list does **not** check whether a unit is archived; an archived unit that still has people will appear.
  - If nothing is returned, the popup says "No units found in this division." After more than a few units the list scrolls.

**Unit level:** units have no markers and no popup of their own. The drill-down stops at the division popup's unit list. There is no "back to national view" button; users zoom out by hand.

### 1.4 "Ready to Make a Difference?"

Sub-text: "Join thousands of compassionate individuals who are making a real difference in communities across Nigeria." It shows four cards:

| Card (badge) | Tagline | Card text | Button → destination |
|---|---|---|---|
| **Volunteer** (V) | Give your time — no fee | "Serve hands-on with your local Red Cross unit — emergency response, first aid, community programs. No membership fee, no payment: just your time and skills." | **Become a Volunteer** → `/volunteer-journey` (`volunteer.journey`, view `pages/volunteer-journey`) |
| **Volunteer & Member** (V+M) | Serve, and pay a small fee | "Serve as an active volunteer in a Red Cross unit and register as a paying member." + "Categories: …" (active 1-year volunteer fee names) | **Become a Volunteer & Member** → `/volunteer-member-journey` (`volunteer-member.journey`) |
| **Supporting Member** (SM) | Support us financially | "Support our mission without volunteering. Register and pay online in minutes." + "Categories: …" (active 1-year member fee names) | **Become a Supporting Member** → `/membership-journey` (`membership.journey`) |
| **Corporate Member** (C) | Partner as an organisation | "Register your company or organisation as a corporate supporter of the Nigerian Red Cross Society's humanitarian work." | **Become a Corporate Member** → `/corporate-membership` (`corporate.journey`) |

What each journey page shows:

- **Volunteer journey**: "Your Journey to Become a Red Cross Volunteer". Four steps: 1 Register Your Account · 2 Link with Your Branch · 3 Red Cross Unit Assignment ("Your branch assigns you…") · 4 Begin Your Service. Then **Register Now** → `/register`.
- **Volunteer & Member journey**: four steps: Register · Link with Your Branch · Red Cross Unit Assignment · **Pay Your Membership Fee** ("through your branch"). A fee table lists the active 1-year volunteer fees (name, description, ₦ yearly fee). Then **Register Now** → `/register`.
- **Supporting Member journey**: four steps: Register · **Note Your DB-Code** · Select Membership Type · Make Payment. A fee table lists the active 1-year member fees. Then **Register Now** → `/register`.
- **Corporate Membership & Partnership**: describes Corporate Membership (annual fee, certificate, recognition) and Donations (cash or in-kind, certificate on request).
  - *Guests* see "Getting Started": 1 Register a Personal Account, 2 Contact Your Branch, then **Register Now** → `/register` and the note "Already have an account? Log in and return to this page…".
  - *Logged-in users* instead see "Contact Your Branch" with **their own branch's** address, phone, email and public contact persons (name, position, email, phone).
  - The page ends with a table of **Corporate Membership Categories** (active organisation fees).

For the trainer to know:
- **All four journeys lead to the same `/register` form.** Nothing is pre-selected. On that form the user chooses between **Volunteer Services** and **Supporting Membership** (field `contribution_type`, with the prompt "Please select one option."). There is no separate Volunteer & Member or Corporate option.
- The **Register Now** buttons are also shown to users who are already logged in.
- Organisations cannot register themselves. A branch creates them (see 02-guides §2.5).

### 1.5 "Our Impact" (national totals, the same for everyone)

| Tile | Figure | Definition |
|---|---|---|
| **Red Cross Units** — "Total units" | `getActiveUnitsCount()` | Active units only. |
| **Volunteers** — "Active volunteers", "including N volunteering members" | `getActiveUnitVolunteersCount()`; the "including" figure is cached for 30 minutes | Users in an **active** unit whose lifecycle is not archived (pending people are included). N = those whose contributor type is Volunteer & Member. |
| **Total Supporting Members** — "Active memberships, not in a unit" | `User::members()` | **No** unit + a valid personal membership payment + lifecycle active or dormant. |
| **Task Forces** — "Total task forces" | `TaskForce::count()` | **All** task forces, including archived ones. |

Total activity hours are calculated in the controller but not shown on the page.

> ⚠ **The map and the Our Impact tiles count differently.** The branch popups' "Volunteers" includes archived people and inactive units; "Members" includes paying volunteers. So branch figures will not add up to the national tiles. If participants compare them, explain the difference rather than treating it as a data error.

---

## 2. The admin sidebar menu

**Source:** `resources/views/components/layouts/admin.blade.php`. This is the layout for every admin page.
- **Desktop:** the sidebar is always visible, under the top bar.
- **Mobile:** the sidebar is hidden behind the hamburger button. When opened, it first shows the top-bar items (Home / My Profile / My Team) and then the menu below.

**How visibility is controlled.** Each item is wrapped in a Blade `@can('<permission>')` (spatie permission). There are **no policies or role-name checks** in the menu. The only exception is the dynamic label of the campaigns item, which depends on the user's access level. There is no `Gate::before` bypass, so super-admin only sees what its own permissions allow.

**Menu tree, in display order:**

**MAIN MENU**

| # | Label (icon) | Visible if user `can(...)` | Route name → URL | Controller@method | Blade view | Route middleware |
|---|---|---|---|---|---|---|
| 1 | **Dashboard** (gauge) | *no check: always shown in the admin layout* | `admin.dashboard` → `/admin/dashboard`, which **redirects** to `reports.dashboard` → `/reports` | `AdminController@dashboard` → `Reports\DashboardController@index` | `dashboard.blade.php` | `manage-admin-panel`, then `view_reports` |
| 2 | **Persons** (user) | `view_user` | `users.index` → `/users` | `UserController@index` | `users/index` | `manage-admin-panel`, `view_user` |
| 3 | **Red Cross Units** (shield) | `view_user` ⚠ (the route uses a different permission) | `red-cross-units.index` → `/red-cross-units` | `RedCrossUnitController@index` | `red-cross-units/index` | `manage-admin-panel`, `view_red_cross_unit` |
| 4 | **Task Forces** (users-gear) | `view_task_force` | `task-forces.index` → `/task-forces` | `TaskForceController@index` | `task-forces/index` | `manage-admin-panel`, `view_task_force` |
| 5 | **Organisations** (industry) | `view_organisation` | `organisations.index` → `/organisations` | `OrganisationController@index` | `organisations/index` | `manage-admin-panel` **only** ⚠ |
| 6 | **Branches** (sitemap) | `view_branch_information` | `branches.index` → `/branches` | `BranchController@index` | `branches/index` | `manage-admin-panel`, `view_branch_information` |
| 7 | **Divisions** (layer-group) | `view_division_information` | `divisions.index` → `/divisions` | `DivisionController@index` | `divisions/index` | `manage-admin-panel`, `view_division_information` |

**MANAGEMENT**

| # | Label (icon) | Visible if user `can(...)` | Route name → URL | Controller@method | Blade view | Route middleware |
|---|---|---|---|---|---|---|
| 8 | **Payment Records** (hand-holding-dollar) | `view_payments` | `membership-payments.index` → `/membership-payments` | `MembershipPaymentController@index` | `membership-payments/index` | `manage-admin-panel`, `view_payments` |
| 9 | **Volunteering Log** (hands-helping) | `view_volunteering` | `activities.index` → `/activities` | `ActivityController@index` | `activities/index` | `manage-admin-panel`, `view_volunteering` |
| 10 | **Training Records** (graduation-cap) | `view_trainings` | `trainings.index` → `/trainings` | `TrainingController@index` | `trainings/index` | `manage-admin-panel`, `view_trainings` |
| 11 | **Donation Records** (heart) | `view_donations` | `donations.index` → `/donations` | `DonationController@index` | `donations/index` | `manage-admin-panel`, `view_donations` |
| 12 | **Campaign Management** (sliders) | `campaign_request_approve` | `campaigns.admin.proposed` → `/campaigns/admin/proposed` | `CampaignAdminController@index` | `campaigns/admin/index` | `manage-admin-panel`, `campaign_request_approve` |
| 13 | **<CODE> Campaigns** (bullhorn): the label is "**NAT Campaigns**" for national users, otherwise "**<branch code> Campaigns**" (e.g. "LAG Campaigns"), or "Branch Campaigns" if no code is found | `campaign_request_create` | `campaigns.mine` → `/campaigns/mine` | `CampaignMyController@index` | `campaigns/mine/index` | `manage-admin-panel`, `campaign_request_create` |
| 14 | **ID Cards** (id-card) | `view_idcards` | `id-cards.prepare-bulk-print` → `/id-cards/prepare-bulk-print` | `IdCardController@showBulkPrintForm` | `id-cards/prepare-bulk-print` | `manage-admin-panel`, `view_idcards` |
| 15 | **Certificates** (certificate) | `view_certificates` | `certificates.index` → `/certificates` | `CertificateController@index` | `certificates/index` | `manage-admin-panel`, `view_certificates` |
| 16 | **Archive Tool** (archive) | `use_archive_tool` | `dormant-users.index` → `/dormant-users` | `DormantUserController@index` | `dormant-users/index` | `use_archive_tool` |
| 17 | **Authorizations** (key) | `manage_roles_and_permissions` | `users.roles.edit` → `/users/roles` | `UserController@editRoles` | `users/edit-roles` | `manage-admin-panel`, `manage_roles_and_permissions`, **password confirmation** |
| 18 | **Settings** (cog) | `change_settings` | `admin.settings.index` → `/admin/settings` | `SettingController@index` | `settings/index` | `change_settings`, **password confirmation** |
| 19 | **Log** (clipboard-list) | `view_log` | `logs.index` → `/logs` | `LogController@index` | `logs/index` | `manage-admin-panel`, `view_log` |

⚠ Notes on the table:
- **Red Cross Units** is shown to anyone with `view_user`, but the page requires `view_red_cross_unit`. Every role currently has both, so nobody is affected in practice.
- **Organisations** is hidden from the menu for anyone without `view_organisation`, but the route only checks `manage-admin-panel`. Any admin role can therefore open `/organisations` by typing the URL.
- The **Dashboard** item has no `@can`. Every role holds `manage-admin-panel` and `view_reports`, so it always works.

---

## 3. Role-by-menu matrix

**Roles that exist in the code (9):** `super-admin`, `national_db_administrator`, `national_db_assistant`, `observer_national_level`, `branch_secretary`, `branch_db_administrator`, `branch_db_assistant`, `division_db_assistant_finance`, `division_db_assistant_operations`.

**There are no unit-level roles.** A Red Cross Unit's *Team Leader* and *Assistant Team Leader* are fields on the unit (`team_leader_id`, `assistant_team_leader_id`), not spatie roles, so they add no sidebar items. A unit member or team leader **with no role** does not see the admin sidebar at all. They get the public top bar with **Home**, **My Profile** and **My Team** (§4). The two division-level roles are the ones listed below.

✔ = visible in the sidebar and the page opens. ✘ = hidden. The first six columns are the roles you asked for; the last three are included for completeness.

| # | Menu item | `branch_secretary` | `branch_db_administrator` | `division_db_assistant_finance` | `division_db_assistant_operations` | `national_db_administrator` | `branch_db_assistant` | `national_db_assistant` | `observer_national_level` | `super-admin` |
|---|---|---|---|---|---|---|---|---|---|---|
| 1 | Dashboard | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| 2 | Persons | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| 3 | Red Cross Units | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| 4 | Task Forces | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| 5 | Organisations | ✔ | ✔ | ✘ | ✘ | ✔ | ✔ | ✔ | ✘ | ✘ |
| 6 | Branches | ✔ | ✔ | ✔ | ✔ | ✔ | ✘ | ✔ | ✘ | ✘ |
| 7 | Divisions | ✔ | ✔ | ✔ | ✔ | ✔ | ✘ | ✔ | ✘ | ✘ |
| 8 | Payment Records | ✔ | ✔ | ✔ | **✘** | ✔ | ✔ | ✔ | ✔ | ✔ |
| 9 | Volunteering Log | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| 10 | Training Records | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| 11 | Donation Records | ✔ | ✔ | ✔ | **✘** | ✔ | ✔ | ✔ | ✔ | ✔ |
| 12 | Campaign Management | ✘ | ✘ | ✘ | ✘ | ✔ | ✘ | ✘ (✔ if given the direct permission *Approve Campaign Requests*) | ✘ | ✘ |
| 13 | <CODE> Campaigns | ✔ ("LAG Campaigns") | ✔ | ✘ | ✘ | ✔ ("NAT Campaigns") | ✘ | ✔ | ✘ | ✘ |
| 14 | ID Cards | ✔ (view only) | ✔ (view only) | ✔ (view only) | ✔ (view only) | ✔ (can print) | ✔ (view) | ✔ (view; print if given *Print ID Cards*) | ✔ (view) | ✔ (view) |
| 15 | Certificates | ✔ (can print) | ✔ (can print) | ✔ (view only) | ✔ (view only) | ✔ (can print) | ✔ (view) | ✔ (view; print if given *Print Certificates*) | ✔ (view) | ✔ (view) |
| 16 | Archive Tool | ✔ | ✔ | ✘ | ✘ | ✔ | ✘ | ✔ | ✘ | ✘ |
| 17 | Authorizations | ✔ | ✔ | ✘ | ✘ | ✔ | ✘ | ✘ | ✘ | ✔ |
| 18 | Settings | ✘ | ✘ | ✘ | ✘ | ✔ | ✘ | ✘ | ✘ | ✘ |
| 19 | Log | ✔ | ✔ | ✘ | ✘ | ✔ | ✘ | ✔ | ✔ | ✘ |
| | **Items visible** | **17** | **17** | **12** | **10** | **19** | **11** | **16** | **11** | **11** |

How to read it:
- **Branch Secretary and Branch DB Administrator see exactly the same menu.** It is everything except **Campaign Management** and **Settings**, which are national only. The data inside is limited to their own branch.
- **Division assistants** see only their own division's data. Operations assistants do not get **Payment Records** or **Donation Records** (money), which matches the role description "Does not handle financial transactions".
- **Branch Secretaries and Branch DB Administrators can print certificates** (for branch-printable types) but **not ID cards**. ID card printing is national only.
- **super-admin** holds several *view* permissions in the local database, so its sidebar shows 11 items. Its intended job is only appointing National DB Administrators via **Authorizations** (see CLAUDE.md / Decisions.md). The Welcome-page banner points it there.

---

## 4. Top bar and user menu

**Source:** `resources/views/components/header.blade.php`, used by both the public layout and the admin layout. The nav items come from `app/View/Composers/NavigationComposer.php` and are rendered by `components/navigation.blade.php` (desktop) and `components/mobile-navigation.blade.php` (mobile).

| Element | Shown to | Destination / behaviour |
|---|---|---|
| **Hamburger** (mobile only) | Everyone | Opens the mobile menu. On admin pages it also opens the sidebar. |
| **NRCS logo + "Volunteer Management System"** | Everyone | → `/` (Welcome) |
| **Home** | Everyone | → `/` |
| **My Profile** | Logged in, **except super-admin** | → `/profile` (`ProfileController@show`, view `profile/show`). Super-admins are redirected to `/reports`. |
| **My Team** | Logged in **and assigned to a Red Cross Unit** | → `/my-unit` (`RedCrossUnitController@myUnit`, view `red-cross-units/my-unit`). Sub-pages `/my-unit/report`, `/my-unit/tables`, `/my-unit/comparison`. No role needed. |
| **Admin** | Users with the `manage-admin-panel` permission, i.e. **all 9 roles** | → `/admin/dashboard` → `/reports`. The narrow-screen version shows it to anyone with any role, which is effectively the same people. |
| **Notifications bell** (with unread count, shown as "9+" above nine) | Every logged-in user | Dropdown titled **Notifications**, showing the latest 8. Items: "Your <module> #<id> was rejected" + "Reason: …"; "Campaign approved: <title>"; "Campaign rejected: <title>" + reason; or a generic message. **Mark all read**. "No notifications" when empty. Clicking an item → `/notifications/{id}/read`. |
| **Welcome <full name>** | Logged in | Plain text, not a link. |
| **Logout** | Logged in | `POST /logout` (`LoginController@logout`) |
| **Login** / **Register** | Guests | → `/login` / `/register` |
| Language switcher | — | **None.** The app is English only; there is no locale switching. |
| Profile dropdown / avatar menu | — | **None.** Profile is reached via **My Profile** in the nav. |

⚠ The notification routes (`/notifications/{id}/read` and `/notifications/read-all`) require `manage-admin-panel`, but the bell is shown to **every** logged-in user. Today notifications are only sent to people who submit records or campaigns, and those people all hold a role, so this should not affect anyone in practice. A volunteer with no role would get a 403 error if one ever appeared.

---

## 5. Quick reference for the course

- **Where do I start after login?** Use **Admin** (top bar) or the sidebar's **Dashboard**. Both end up at `/reports`. The Welcome page stays reachable through **Home**.
- **"Why can't my Operations assistant see Payment Records?"** The role doesn't hold `view_payments`. This is by design.
- **"Why is there no Settings item for me?"** Settings and Campaign Management are for the National DB Administrator only.
- **"Why does the menu say LAG Campaigns?"** The campaigns item is named after your branch code.
- **Map numbers vs. dashboard numbers:** they use different definitions (see §1.3 and §1.5).
