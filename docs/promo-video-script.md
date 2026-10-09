# Promo Video Script: "One Database for Your Volunteers"

**Length:** about 3 minutes · **Audience:** leaders and database staff of other African National Red Cross Societies
**Message:** This works in Nigeria, and it can be adapted for your National Society too.
**Voice:** the ElevenLabs Nigerian voice used for the in-app tutorials (`public/tutorials/audio/`)
**Contact (end card):** Erik Pleijel · erik.pleijel@gmail.com

**Changes in this version (2026-10-08):**

- Registration and My Profile scenes removed (registration is mentioned in one sentence in the welcome scene; the "stay in control of their own data" line moved to the start of My Team)
- New scenes: Statistics and Campaigns
- Audit log removed from scene 3g
- Closing: "thousands of volunteers", "can be adapted", contact Erik Pleijel

---

## Step 1: What the application really does today

Based on the routes, controllers, views, config, `Decisions.md` and `COMPLIANCE.md` as of 2026-10-08.

### a) Built and safe to show

| Feature | Where | Notes |
|---|---|---|
| Public welcome page | `/` | Map of all branches (click a branch to see its divisions), live counts of units, volunteers, members and task forces, four ways to join (Volunteer, Volunteer & Member, Supporting Member, Corporate Member) |
| Self-registration | `/register` | Personal, contact and Red Cross details; branch then division dropdown; photo from the phone/webcam camera or an upload; Code of Conduct with 4 required confirmations (the 4th is data consent); email verification; bot protection |
| My Profile | `/profile` | Details, photo, signature, Red Cross unit, trainings, volunteering hours, membership, donations, organisations, printed ID cards and certificates, communication preferences, "Archive My Account" (self-service) |
| My Team | `/my-unit` (menu label "My Team") | Unit name, division and branch, team leader and assistant, member list (photos only shown on request, to save mobile data), task forces, plus five sub-pages: ID Cards Completeness Report, Membership/Training/Activities tables, Compare with Other Units, Volunteer Map, First Aid Coverage map |
| Dashboard | `/admin/dashboard` (redirects to `/reports`) | Tiles for training, members, revenue, donations, volunteers, registrations and units; lifecycle overview (pending engagement / active / dormant); planning tools; trends; heat maps; database-administration lists |
| Volunteer and first-aid maps | `/reports/maps/volunteers/branches`, `/reports/maps/first-aid/branches` | Branch and division level |
| Two-person approval | `/trainings/approvals`, `/activities/approvals`, `/membership-payments/approvals`, `/donations/approvals` | Trainings, volunteering hours, membership payments and donations entered by staff count only after a different person approves them. Nobody can approve their own entry. |
| Certificates | `/certificates` | Bulk print from approved trainings: plain (pre-printed paper), branded, and branded portrait. Each certificate has a QR code leading to a public check page. |
| ID cards | `/id-cards/prepare-bulk-print` | Bulk print, print history report, card expiry report, public QR check at `/idcheck/{token}` (name, status, branch, division, first aid, other trainings, volunteering hours; no photo) |
| Organisation structure | `/branches`, `/divisions`, `/red-cross-units`, `/task-forces`, `/organisations` | Branch, division, unit, plus task forces and corporate organisations |
| Roles by level | `/users/roles` | National, branch and division roles; each staff member sees only their own area |
| Member statuses | dashboard, `/lifecycle/*`, `/dormant-users` | Pending engagement, active, dormant (re-engage), archived; nightly automatic update |
| Data protection | `/consent/confirm`, `/privacy-policy`, `/logs` | One-time consent and Code of Conduct for everyone, a data-handling commitment for staff, protected photos, re-entering the password for sensitive settings, audit log, automatic anonymisation after 7 years in the archive |
| Tutorials | `/learn` | 4 levels and 11 lessons with Nigerian-voice audio, progress saved, completion report. **Staff roles only**: a plain volunteer cannot open them. |
| Reports | `/reports/...` | Members, volunteers, branches, units, trainings, donations, finance, registrations, staff activity, tutorial completion |

### b) Built but not active or not finished

| Feature | Status |
|---|---|
| **Online payments (Paystack)** | Code exists, but `docs/paystack.md` §4 "What remains before this can go live" is still open (real account, live keys, webhook). **The Supporting Member card on the welcome page says "Register and pay online in minutes".** Do not narrate it, and do not dwell on that sentence on screen. |
| **Sending campaign messages (email and SMS)** | The campaign wizard, approval, recipient building and monitor all work, but delivery is **log only** (`LogEmailChannel`, `LogSmsChannel`, SMSLive247 in dry run, `CAMPAIGNS_SIMULATE=true`). Campaigns are now included in the video (scene 3d). The narration describes choosing recipients, writing the message, approval and following progress. It does not claim that messages are already being delivered in Nigeria. The delivery numbers shown on the monitor in the sandbox are simulated. See "Things to check", item 1. |
| Public ID-card check | Works, but `COMPLIANCE.md` says the amount of information it shows is "still to be decided". Showing it is OK; do not promise what it will show in the end. |
| "Membership Growth Over Time (Not real data)" chart | In `admin-dashboard.blade.php`, which is no longer reached. Do not show it. |
| Banner "⚠️ Development Version" | Hard-coded at the top of the welcome page. Hide it on the recording sandbox, or crop it out. |

### c) Nigeria-specific. Avoid or soften.

- **NIN (National ID number):** required for ID-card printing and printed on the ID card. Blur it on screen.
- **Phone numbers:** only Nigerian mobile numbers are accepted.
- **NDPA** (Nigeria Data Protection Act): used in the consent wording and the privacy policy.
- **Naira and Paystack:** payment currency and gateway.
- **Welcome page text:** "37 states… 774 local government areas", "communities across Nigeria"; the map is centred on Nigeria.
- **Branch = state, division = local area:** the same levels work elsewhere, but the names differ (region, district, chapter…).
- **Membership categories:** Junior Unit, School Unit, Detachment, Service Group, Associate; Bronze to Platinum. These are NRCS fee categories.
- **NRCS Code of Conduct text and logo** throughout.

> **Note:** registrations are **not** approved by a second person. A new person starts as "pending engagement" and becomes active when staff place them in a Red Cross unit, link them to an organisation, or approve their first record. The two-person approval applies to **trainings, volunteering hours, membership payments and donations**.

---

## Scenes

**Order:** 1 Welcome page → 2 My Team → 3 Dashboard and features (dashboard, statistics, heat maps, campaigns, approval and certificates, ID cards, roles and tutorials) → 4 Closing

**Accounts needed** (all in the sandbox, all made up):

- **Demo Volunteer:** no staff role, placed in the demo unit (the "My Team" menu item only appears for people in a unit)
- **Demo National Admin:** `national_db_administrator`
- **Demo Branch Admin:** a second staff account that enters a training and creates a campaign, so the National Admin can approve them (nobody can approve their own entry)

**Recording tips:** browser at 1920×1080 with the zoom at 110–125%; hide bookmarks; use one fixed browser profile; record each scene separately and a few seconds longer than its narration; generate the ElevenLabs audio one scene at a time.

---

### Scene 1: Welcome page

**Time:** 0:00 – 0:31
**URL:** `/`
**Logged in as:** nobody (logged out)

**On screen:**

1. Start below the "Development Version" banner (or hide/crop it): the title "Welcome to the … Volunteer Management System".
2. Scroll slowly to **Our Branch Network**. Click the map once to activate it, then click one branch marker to open its popup.
3. Scroll to the four **join cards** (Volunteer, Volunteer & Member, Supporting Member, Corporate Member). Do not linger on "pay online".
4. Optional, 2–3 seconds: click **Volunteer** and show the top of the registration form, then go back.
5. End on **Our Impact** (the four counters).

**Narration:**

> How many volunteers does your National Society really have? Where are they? And what are they trained to do? In Nigeria, the Nigerian Red Cross Society answers these questions with one shared database. It starts with a public welcome page. A map shows every branch. And anyone can choose how to join: as a volunteer, as a member, or as a partner organisation. Registration takes only a few minutes, on a phone or a computer.

---

### Scene 2: My Team

**Time:** 0:31 – 0:59
**URL:** `/my-unit`, then `/my-unit/comparison`, then `/my-unit/first-aid-map`
**Logged in as:** Demo Volunteer

**On screen:**

1. Start on the logged-in home page and click **My Team** in the top menu.
2. Show the unit name, division and branch, the **Team Leader** and **Assistant Team Leader**, and the member list. Tick **Show profile photos** (demo photos only).
3. Click **Membership, Training & Activities** and show one tab.
4. Go back and click **Compare with Other Units**.
5. Click **First Aid Coverage** to show the map.

**Narration:**

> Once registered, every volunteer can log in, keep their own details up to date, choose how they want to be contacted, and stay in control of their own data. Under My Team, they see their own Red Cross unit: their team leader, their fellow members, and the unit's trainings and activities. They can compare their unit with others, and see on a map where trained first aiders are.

---

### Scene 3: Dashboard and features

**Time:** 0:59 – 2:38
**Logged in as:** Demo National Admin (3d and 3e need Demo Branch Admin to have created the campaign and entered the training beforehand)

#### 3a – Dashboard (0:59 – 1:16)

**URL:** `/admin/dashboard` (click **Admin** in the top menu; it opens `/reports`)

**On screen:** the top tiles (Volunteers, Supporting Members, Training & First Aid, Registrations, Red Cross Units). Scroll to **Lifecycle Overview** (Pending engagement / Active / Dormant). Change the branch filter to one branch and back.

**Narration:**

> For your leaders and database staff, the dashboard shows the full picture: volunteers, members, trainings and new registrations, for the whole country or for one branch. It also shows who is active, and who has gone quiet and needs a call.

#### 3b – Statistics (1:16 – 1:32)

**URL:** the **Trends** section of the dashboard, then two or three reports under `/reports/...` (for example trainings, registrations and finance; check the exact URLs)

**On screen:** scroll through the trend charts slowly. Open one report and change a filter (branch or period) so the numbers update. Keep each chart on screen for at least 3 seconds.

**Narration:**

> Behind the dashboard are detailed reports and trends: growth in members and volunteers, trainings, donations and fees, and new registrations over time. Your leaders can see what is working, and plan where to recruit and train next.

#### 3c – Heat maps (1:32 – 1:39)

**URL:** `/reports/maps/first-aid/branches`, then click into one branch (division level)

**On screen:** the first-aid map; hover over a strong area and a weak area.

**Narration:**

> Maps show where you have many trained first aiders, and where you have too few.

#### 3d – Campaigns (1:39 – 1:59)

**URL:** the **Campaigns** menu (campaign list, wizard, approval and monitor; check the exact URLs)

**On screen:**

1. Open the campaign list.
2. Start a new campaign in the wizard: choose a purpose, then set two or three filters (for example one branch and "First Aid trained") and show the recipient count update.
3. Show the message step with a short, friendly demo message (email and SMS text).
4. Switch to the campaign created earlier by Demo Branch Admin and approve it as Demo National Admin.
5. Show the campaign monitor with its progress (simulated in the sandbox).

**Narration:**

> Need to reach your volunteers? With campaigns, you choose exactly who to contact: by branch, by unit, by training or by status. You write one message, by email or SMS, and a second person approves it before it goes out. Then you follow the progress on screen.

#### 3e – Two-person approval and certificates (1:59 – 2:15)

**URL:** `/trainings/approvals`, then `/certificates`, then the printed certificate, then the QR check page on a phone

**On screen:**

1. In **Approvals**, open the pending First Aid training (entered by Demo Branch Admin) and click **Approve**.
2. Go to **Certificates**, select the demo volunteer's training, and choose **branded** print.
3. Show the certificate preview and zoom in on the QR code.
4. Cut to a phone scanning the QR code and opening the check page (record the phone screen separately).

**Narration:**

> When staff record a training or volunteering hours, a second person checks and approves it before it counts. Approved trainings become certificates. Each one has a Q-R code that anyone can scan to confirm it is genuine.

#### 3f – ID cards (2:15 – 2:28)

**URL:** `/id-cards/prepare-bulk-print`, then the print view, then `/idcheck/{token}` on the phone

**On screen:**

1. Filter to the demo unit, tick three demo volunteers, and click print.
2. Show the cards (**blur the NIN line**).
3. Phone scan, then the **ACTIVE VOLUNTEER** check page with First Aid Certifications and volunteering hours.

**Narration:**

> Volunteer I-D cards are printed in batches, straight from the database. Scan a card, and you see at once if the volunteer is active, and which first aid training they hold.

#### 3g – Roles and tutorials (2:28 – 2:38)

**URL:** `/learn` (tutorial levels), then open **Level 0 → Welcome & Overview** and let the audio play for 2 seconds

**On screen:** do **not** open `/users/roles` (it asks for the password). For the first sentence, show the dashboard with the branch filter locked to one branch as a Demo Branch Admin, or simply the `/learn` page.

**Narration:**

> Staff see only the part of the society they are responsible for. And short spoken tutorials teach new staff, step by step.

---

### Scene 4: Closing

**Time:** 2:38 – 3:02
**URL:** back to `/` (the map), then an end card (made in the video editor)
**Logged in as:** nobody

**On screen:** slow zoom on the branch map, fade to an end card with:

- Red Cross logo (as agreed with NRCS)
- Erik Pleijel
- erik.pleijel@gmail.com

Keep the end card on screen for at least 5 seconds after the narration ends, so viewers can write down the email.

**Narration:**

> This system was built together with the Nigerian Red Cross Society, for thousands of volunteers across Nigeria. It can be adapted for your National Society too, with your own branches, your own divisions and your own units. Contact us for a demonstration. Together, let us know our volunteers better, and serve our communities better.

---

## Narration, all scenes in one block (ready to paste into ElevenLabs)

How many volunteers does your National Society really have? Where are they? And what are they trained to do? In Nigeria, the Nigerian Red Cross Society answers these questions with one shared database. It starts with a public welcome page. A map shows every branch. And anyone can choose how to join: as a volunteer, as a member, or as a partner organisation. Registration takes only a few minutes, on a phone or a computer.

Once registered, every volunteer can log in, keep their own details up to date, choose how they want to be contacted, and stay in control of their own data. Under My Team, they see their own Red Cross unit: their team leader, their fellow members, and the unit's trainings and activities. They can compare their unit with others, and see on a map where trained first aiders are.

For your leaders and database staff, the dashboard shows the full picture: volunteers, members, trainings and new registrations, for the whole country or for one branch. It also shows who is active, and who has gone quiet and needs a call.

Behind the dashboard are detailed reports and trends: growth in members and volunteers, trainings, donations and fees, and new registrations over time. Your leaders can see what is working, and plan where to recruit and train next.

Maps show where you have many trained first aiders, and where you have too few.

Need to reach your volunteers? With campaigns, you choose exactly who to contact: by branch, by unit, by training or by status. You write one message, by email or SMS, and a second person approves it before it goes out. Then you follow the progress on screen.

When staff record a training or volunteering hours, a second person checks and approves it before it counts. Approved trainings become certificates. Each one has a Q-R code that anyone can scan to confirm it is genuine.

Volunteer I-D cards are printed in batches, straight from the database. Scan a card, and you see at once if the volunteer is active, and which first aid training they hold.

Staff see only the part of the society they are responsible for. And short spoken tutorials teach new staff, step by step.

This system was built together with the Nigerian Red Cross Society, for thousands of volunteers across Nigeria. It can be adapted for your National Society too, with your own branches, your own divisions and your own units. Contact us for a demonstration. Together, let us know our volunteers better, and serve our communities better.

---

## Word count and length

| Scene | Words | Time |
|---|---|---|
| 1 Welcome page | 75 | 0:00 – 0:31 |
| 2 My Team | 68 | 0:31 – 0:59 |
| 3a Dashboard | 41 | 0:59 – 1:16 |
| 3b Statistics | 37 | 1:16 – 1:32 |
| 3c Heat maps | 15 | 1:32 – 1:39 |
| 3d Campaigns | 47 | 1:39 – 1:59 |
| 3e Approval and certificates | 37 | 1:59 – 2:15 |
| 3f ID cards | 31 | 2:15 – 2:28 |
| 3g Roles and tutorials | 22 | 2:28 – 2:38 |
| 4 Closing | 54 | 2:38 – 3:02 |
| **Total** | **427** | **about 3:00** |

Estimate: 150 spoken words per minute (about 2:51 of speech) plus about 1 second between scenes and 2 seconds at the end. If the voice is slower and the video runs long, the easiest cuts are the last sentence of scene 1 and the first sentence of scene 3g.

---

## Sandbox demo data checklist (prepare before recording)

**Record only from a sandbox database with made-up people.** The local database is copied from real NRCS data, so do not record from it.

- [ ] **Hide the "Development Version" banner** on the recording sandbox (or crop it in the editor). Consider hiding the "pay online" sentence on the Supporting Member card too.
- [ ] **Branch structure:** real Nigerian branch and division names are fine (they are places, not people). Make sure the map has coordinates for the branches you will click.
- [ ] **One demo unit** in one division, for example "Demo Unit Alpha", with a team leader, an assistant team leader and about 12 members.
- [ ] **Made-up people:** invented names (check none matches a real volunteer); AI-generated or licensed stock portraits; phone numbers that pass the Nigerian mobile check but are not real people's numbers (or blur them); clearly fake 11-digit NINs (for example 00000000001); made-up email addresses on a domain you control.
- [ ] **Demo signatures** (drawn by you) for the ID-card volunteers.
- [ ] **ID-card-ready volunteers (at least 3):** photo, signature, NIN, branch and division all filled in. Check the bulk-print page shows them before recording day.
- [ ] **Trainings:** approved First Aid trainings (with an expiry date) and one other training for the demo volunteer and several unit members.
- [ ] **One pending First Aid training** entered by Demo Branch Admin, ready to be approved on camera by Demo National Admin.
- [ ] **Volunteering hours** (approved activities) for the demo volunteer and unit.
- [ ] **A mix of statuses** (active, dormant, pending engagement) so the Lifecycle Overview is not empty.
- [ ] **Statistics:** enough demo data spread over several months (registrations, trainings, donations, membership payments) so the trend charts and reports show real-looking lines, not one flat point. Run the statistics snapshot and cache refresh (`/reports/refresh`).
- [ ] **Campaigns:** one campaign created by Demo Branch Admin and waiting for approval; one finished campaign with progress on the monitor (simulated); demo recipients with made-up emails and phone numbers. Check that `CAMPAIGNS_SIMULATE=true` on the sandbox, so nothing is really sent.
- [ ] **Certificate:** check the branded print shows made-up names and signatures, not a real official's (`/admin/settings/signatures`, `/settings/signature-titles`).
- [ ] **QR codes:** set the sandbox `APP_URL` to a URL the phone can open, and test-scan before recording.
- [ ] **Consent** already accepted by Demo Volunteer, and the staff data-handling commitment (`/policy/accept`) accepted by both admin accounts.
- [ ] **End card:** Red Cross logo (as agreed with NRCS), Erik Pleijel, erik.pleijel@gmail.com.

---

## Things to check

1. **Campaign delivery.** Email and SMS sending is not live yet (log only). The narration describes how campaigns work, which is fine for a demo. If someone asks after seeing the video, be ready to say that the email and SMS services are being connected. If you want to be extra careful, change "You write one message, by email or SMS" to "You write one message, ready for email and SMS".
2. **Campaign filters and approval.** Check that the wizard really filters by branch, unit, training and status, and that a different person must approve the campaign. Adjust the narration if the filters are named differently.
3. **Exact URLs** for the campaign pages and the statistics reports (scenes 3b and 3d) were not listed above. Fill them in before recording.
4. **NRCS permission** to show their name and logo in a video for other National Societies.
5. **Pronunciation:** test "Q-R code", "I-D cards" and "SMS" in ElevenLabs. If the dashes sound odd, try "QR code" and "ID cards".
6. **Timing:** adjust scene times after generating the audio.
