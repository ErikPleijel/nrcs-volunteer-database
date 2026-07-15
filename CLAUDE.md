# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Start full dev environment (Laravel server + queue + Vite + log watcher)
composer run dev

# Or run individually:
php artisan serve
npm run dev

# Run tests
composer test
# or: php artisan test

# Lint/format PHP code
./vendor/bin/pint

# Build frontend assets for production
npm run build

# Run migrations
php artisan migrate

# Clear stats cache
php artisan cache:clear
```

## Architecture

**Red Cross volunteer management system** built on Laravel 12 + React 19 + Tailwind CSS.

### Data Model Hierarchy

The organizational structure flows: `Organisation` → `Branch` → `Division` → `RedCrossUnit`. Users (`User`) belong to this hierarchy and are either volunteers (`can_contribute_volunteering`) or members (`can_contribute_member`), or both.

User lifecycle states: `awaiting_engagement` → `active` → `dormant` → `archived`. The `MarkDormantUsersFromActivity` command transitions users based on activity.

### Authorization

Uses `spatie/laravel-permission`. Super admins bypass all gates (configured in `AuthServiceProvider`). Route protection uses `can:permission_name` middleware. The `UserPolicy` handles model-level authorization. Roles and permissions are seeded via `RoleSeeder`.

### Key Subsystems

**Reporting** — Controllers under `app/Http/Controllers/Reports/` handle dashboards and exports. Reports are organization-scoped and cover volunteers, members, branches, finances, trainings, and donations.

**Messaging Campaigns** — `MessagingCampaign` → `MessagingRecipient` flow. `BuildCampaignRecipients` command populates recipients. Delivery channels implement `DeliveryChannel` interface (`app/Campaigns/Delivery/`). Currently uses `LogEmailChannel`/`LogSmsChannel` (dry-run). See `docs/campaign_notes.md` for production readiness checklist.

**Credentials** — ID cards (`IdCardPrint`) and certificates (`CertificatePrint`) have dedicated print workflows with verification tokens. Multiple print formats supported (plain, branded, portrait).

**Excel Exports** — Uses `maatwebsite/excel`. Export classes live in `app/Exports/`.

**QR Codes** — `simplesoftwareio/simple-qrcode` used for ID card verification.

**Geographic** — Branches store lat/lng coordinates. Frontend uses Leaflet for map display.

### Frontend

React components live in `resources/js/`. Vite bundles assets. Inertia.js is **not** used — this is a traditional Blade + React hybrid where React components are mounted via `@vite` directives.

### Database

SQLite by default (dev). MySQL for production (see `docs/deploymentVPS.md`). 42+ migrations. Legacy data migration commands are in `app/Console/Commands/Migrate*` — these were one-time operations for importing from the old system.

### Email

Configured via SendGrid (`s-ichikawa/laravel-sendgrid-driver`). Dev uses `log` mailer. Custom notifications: `VerifyEmailNotification`, `ResetPassword`. Legacy password hash support exists for migrated users.

### Settings

Application-wide settings stored in the `settings` table, accessed via the `Setting` model. Organisation-scoped where applicable.
