# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Architecture

### Data Model Hierarchy

The organizational structure flows: `Organisation` → `Branch` → `Division` → `RedCrossUnit`. Users (`User`) belong to this hierarchy and are either volunteers (`can_contribute_volunteering`) or members (`can_contribute_member`), or both.

User lifecycle states: `awaiting_engagement` → `active` → `dormant` → `archived`. The `MarkDormantUsersFromActivity` command transitions users based on activity.

### Authorization

Uses `spatie/laravel-permission`. Super admins bypass all gates (configured in `AuthServiceProvider`). Route protection uses `can:permission_name` middleware. The `UserPolicy` handles model-level authorization. Roles and permissions are seeded via `RoleSeeder`.

### Key Subsystems

**Messaging Campaigns** — `MessagingCampaign` → `MessagingRecipient` flow. `BuildCampaignRecipients` command populates recipients. Delivery channels implement `DeliveryChannel` interface (`app/Campaigns/Delivery/`). Currently uses `LogEmailChannel`/`LogSmsChannel` (dry-run). See `docs/campaign_notes.md` for production readiness checklist.

**Credentials** — ID cards (`IdCardPrint`) and certificates (`CertificatePrint`) have dedicated print workflows with verification tokens. Multiple print formats supported (plain, branded, portrait).

### Frontend

Inertia.js is **not** used — this is a traditional Blade + React hybrid where React components are mounted via `@vite` directives.

### Database

MySQL for production (see `docs/deploymentVPS.md`). Legacy data migration commands are in `app/Console/Commands/Migrate*` — these were one-time operations for importing from the old system.

### Email

Legacy password hash support exists for migrated users.
