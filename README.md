# Red Cross Volunteer & Membership Management System

A web-based management platform for Red Cross and Red Crescent national societies, originally developed for the **Nigerian Red Cross Society (NRCS)**. Built to manage volunteers, members, donations, training, and campaigns across a multi-tier geographic structure.

> Dedicated to supporting Red Cross and Red Crescent national societies worldwide.

---

## Overview

This application was built to replace manual, spreadsheet-based record-keeping with a structured, auditable system. It handles the full lifecycle of volunteers and members — from registration and onboarding through activity tracking, dormancy, and reactivation — across a three-tier geographic hierarchy (national → branch → division → unit).

It is designed for national society HQ staff, branch coordinators, and data entry officers, with role-based access control governing what each tier can see and do.

---

## Key Features

### People & Lifecycle Management
- Unified volunteer and member database.
- Lifecycle policy engine: active, dormant, lapsed, and reactivated statuses
- Volunteer vs. member classification with separate dormancy rules
- Branch-move workflow with approval controls

### Approval Workflows
- Two-step submit → approve/reject flow across four modules: donations, membership payments, volunteering, and training
- Four-eyes integrity: no single user can both submit and approve the same record
- Approvable trait with global ApprovedScope ensuring only approved records surface in reporting

### Geographic Hierarchy
- Three-tier structure: national HQ → branches → divisions → Red Cross units
- Role-based visibility: branch staff see only their branch; national staff see all
- First-aid bubble map and heat score system for volunteer density visualization

### Campaigns
- Campaign management with Quill WYSIWYG editor
- Campaign Filter Wizard with seven accordion filter sections
- Bulk approval and notification system

### Reporting & Analytics
- Stats snapshots for historical trends
- Exportable reports per branch, division, and unit
- Dashboard with lifecycle summary cards

### Additional Modules
- Organisation management (corporate memberships, pivot table, opt-out system)
- Training records
- Tutorial system with text-to-speech narration (ElevenLabs TTS)
- Nightly scheduler for automated tasks

---

## Technology Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 |
| Frontend | Blade, Tailwind CSS, Alpine.js |
| Authorization | Spatie Laravel Permission |
| Database | MySQL |
| Server | Ubuntu / nginx / PHP 8.3 |
| Local dev | Windows / XAMPP |

---

## Authorization Model

The application uses a three-tier role system built on Spatie permissions:

- **Super Admin** — narrowly scoped; deliberately excluded from `Gate::before` bypass to preserve approval integrity
- **National Staff** — full read access; approval authority across all branches
- **Branch/Division Staff** — scoped to their own geographic unit

Role boundaries are load-bearing for the approval workflow and should not be modified without reviewing the `Approvable` trait and `ApprovedScope`.

---

## Installation

### Requirements

- PHP 8.3+
- MySQL 8.0+
- Composer
- Node.js (for asset compilation)
- nginx or Apache

### Setup

```bash
git clone https://github.com/ErikPleijel/redcross_volunteer.git
cd redcross_volunteer

composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate
```

Configure your `.env` file — database credentials, mail settings, and any third-party API keys (see `.env.example` for required variables).

```bash
php artisan migrate
php artisan db:seed
```

### First Login

After seeding, a default super-admin account is created. Credentials are defined in `DatabaseSeeder.php`. Change the password immediately after first login.

---

## Adapting for Your National Society

This application was designed with adaptability in mind. Key areas to customize:

- **Geographic structure** — branch/division names and hierarchy depth are database-driven and configurable
- **Lifecycle policies** — dormancy thresholds and classification rules are defined in the policy engine and can be adjusted per society rules
- **Branding** — logo, colours, and society name are set via environment variables and Blade layout files
- **Language** — the UI is English; Laravel's localization system (`lang/`) is in place for translation

If you adapt this for your national society, contributions back to the main repository are welcome.

---

## Migration Notes (for NRCS or similar legacy data imports)

The application includes a migration command suite for importing from legacy databases:

```bash
php artisan migrate:fresh
php artisan migrate:old-db --table=all
php artisan lifecycle:reconcile --dry-run
php artisan lifecycle:reconcile --apply
```

> ⚠️ The `dates:shift` command is for development/testing only and must never be run against production data.

A full deployment checklist is maintained in `DEPLOYMENT_CHECKLIST.md`.

---

## Project Structure Notes

Non-obvious architectural decisions are documented in `DECISIONS.md` at the project root. If you are modifying core workflow logic (approval chains, scope guards, role boundaries), read that file first.

---

## License

MIT License — see [LICENSE](LICENSE) for full text.

Originally developed for the Nigerian Red Cross Society.
Dedicated to supporting Red Cross and Red Crescent national societies worldwide.

Copyright (c) 2026 Erik Pleijel

---

## Author

**Erik Pleijel**
Floby, Sweden
[erikpleijel.com](https://erikpleijel.com)

Contributions, questions, and adaptations from other national societies are welcome.
