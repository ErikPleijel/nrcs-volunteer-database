# NDPA Compliance — Nigerian Red Cross Society Volunteer & Member Database

Last updated: 2026-06-25

---

## 1. COMPLETED — Technical fixes applied

### Biometric file storage (FAIL → FIXED)
- app/Traits/HandlesImageUploads.php: all public_path() calls replaced with
  Storage::disk('local') — photos write to storage/app/photos/, not the webroot
- app/Models/User.php: all four photo URL accessors now return authenticated
  route('photos.show', ...) URLs, never direct public paths
- app/Http/Controllers/PhotoController.php: new controller serving photos via
  response()->file() behind auth + UserPolicy authorize() check
- routes/web.php: GET /photos/{user}/{type} route inside auth middleware group
- Note: backward-compat fallback to public/ in PhotoController is dead code
  after production migration — remove it post-deployment

### Encryption at rest (FAIL → FIXED)
- app/Models/User.php: encrypted cast added for national_id_number and
  personal_info
- app/Legacy/ComposerController.php: national_id_number removed from all LIKE
  search queries (lines 86, 570) — encrypted fields cannot be queried with LIKE
- database/migrations/: column widened from VARCHAR(255) to TEXT to accommodate
  ciphertext length
- app/Console/Commands/EncryptExistingNationalIds.php: one-off Artisan command
  (php artisan ndpa:encrypt-national-ids) to re-save existing rows through the
  encrypted cast — must be run once on production before go-live
  ⚠️  APP_KEY must never be rotated after this command has run without first
  decrypting and re-encrypting all national_id_number rows. Back up APP_KEY
  separately from the .env file.

### Unauthenticated photo access (FAIL → FIXED)
- Resolved by the storage and PhotoController changes above
- /idcheck/{token} public verification page: profile photo removed from
  response — biometric images must not be served unauthenticated. Name, branch,
  division, and membership status remain visible, sufficient for ID verification.
  Decision logged in DECISIONS.md.

### Legacy MD5 → bcrypt upgrade gap for organisation-originated users (PASS with gap → FIXED)
- app/Console/Commands/MigrateOrganisations.php line 308: during the old-database
  import, organisation-originated users had their password column set to a random
  bcrypt string instead of an empty string. This prevented LoginController from
  ever reaching the MD5 legacy upgrade branch, meaning those users could not log
  in at all. Fixed by setting password to empty string, matching the pattern used
  for volunteer-originated users in MigrateUsers. The upgrade to bcrypt now fires
  correctly on their first successful login.

### Sensitive field audit logging (FAIL → FIXED)
- app/Models/User.php: Eloquent updating observer logs changes to
  national_id_number, personal_info, passport_photo, signature, picture —
  field names only, values stored as '[redacted]'
- app/Http/Controllers/PhotoController.php: photo access is gated by
  authentication and a UserPolicy authorize() check, but individual view
  events are not logged (decision recorded in DECISIONS.md on 2026-07-05)

### Volunteer consent — public self-registration
- resources/views/auth/register.blade.php: fourth NDPA consent checkbox added
  to the existing Code of Conduct flow (same scroll-enforcement, Alpine x-model,
  server-side 'accepted' validation)
- Registration controller: on successful submission, records
  consent_obtained_at, consent_obtained_by_id (self), and consent_notes on
  the user record

### Volunteer consent — admin registration of users without email
- resources/views/users/create.blade.php: "Data Protection Attestation" section
  added with two required checkboxes — staff attests consent was explained and
  that the form of consent is documented
- Optional consent_notes text input for recording the form of consent
- users.store controller: validated as 'accepted', records consent_obtained_at,
  consent_obtained_by_id (the admin), and consent_notes on the user record

### Staff data handling policy acknowledgement
- database/migrations/: policy_accepted_at (timestamp, nullable) added to users
- app/Models/User.php: policy_accepted_at in $fillable, cast as datetime,
  hasAcceptedPolicy() helper method
- app/Http/Middleware/RequiresPolicyAcceptance.php: redirects staff/admin users
  (any Spatie role) to /policy/accept if policy_accepted_at is null. Uses
  getRoleNames()->isEmpty() consistent with codebase pattern.
- app/Http/Controllers/PolicyAcceptanceController.php: show() and store()
- resources/views/policy/accept.blade.php: four-point data handling commitment,
  single required checkbox, timestamps acceptance on submit
- routes/web.php: GET/POST /policy/accept inside auth middleware group
-
- 
## Reports
- Report controllers scoped by access level: PendingApprovalsReportController,
  DatabaseTeamReportController, DatabaseAccessReportController now enforce
  branch_id locking for non-national users — request params cannot be used
  to access other branches' data.

---

## 2. OPEN — Organisational/policy tasks for NRCS leadership

These are legal obligations, not code tasks. They require action by NRCS
management before or shortly after go-live.

- [ ] Designate a Data Protection Officer (DPO) with expert knowledge of the
      NDPA. Required for data controllers of major importance processing personal
      data of more than 2,000 data subjects. NRCS qualifies.
- [ ] Register with the Nigeria Data Protection Commission at ndpc.gov.ng
- [ ] Document the VPS server location. If hosted outside Nigeria, confirm the
      legal basis for cross-border transfer (adequacy decision or explicit
      informed consent from each data subject).
- [ ] Write a 72-hour breach notification procedure naming the responsible
      person who identifies breaches and files with the NDPC.
- [ ] Decide whether NIN collection is legally required or optional for
      volunteers. If optional, consider making the field non-mandatory.
      Document the decision.
- [ ] File an annual Compliance Audit Return (CAR) with the NDPC by 31 March
      each year.
- [ ] Brief NRCS branch and division administrators on the data handling policy
      they will be asked to accept on first login, and on the consent attestation
      they must complete when registering users without email.

---

## 3. OPEN — Deployment prerequisites (code)

These must be executed in order during production deployment:

1. Run all pending migrations (includes TEXT column widen and policy_accepted_at)
2. php artisan ndpa:encrypt-national-ids --dry-run  (verify count)
3. php artisan ndpa:encrypt-national-ids            (encrypt existing NIDs)
4. Continue with normal migration sequence (migrate:old-db, lifecycle:reconcile)

---

## 4. OPEN — One architectural decision still needed

- /idcheck/{token} route: confirm with NRCS whether the data exposed on the
  public verification page (name, branch, division, membership status, training
  history) is appropriate for an unauthenticated public-facing page, or whether
  the entire route should require authentication. Document the decision in
  DECISIONS.md.
