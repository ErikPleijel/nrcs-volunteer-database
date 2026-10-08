# Data Protection and Compliance — NRCS Membership and Volunteer Database

Last updated: 8 October 2026

## 1. Purpose of this document

The Nigerian Red Cross Society (NRCS) uses this database to register and manage its members and
volunteers: their branch and unit, trainings, activities, membership fees and donations. NRCS
decides why and how this personal data is used, so NRCS is the data controller and is responsible
for it. This document describes, in plain language, how the database meets the requirements of the
Nigeria Data Protection Act 2023 (NDPA). It is written for the NRCS Legal department, management and
the Data Protection Officer.

## 2. Data Protection Officer

NRCS appoints a Data Protection Officer (DPO). The DPO's name, postal address, email address and
phone number are entered in the database settings by the National Database Administrator. They are
not tied to a user account, so a change of DPO needs only an update to the settings.

The DPO's details are shown on the public Privacy Policy page, on the Authorizations page used by
administrators, and next to the anonymization option described in section 6. If no DPO has been
entered yet, the Authorizations page shows a clear warning and the Privacy Policy page says that the
DPO's contact details will be published soon.

## 3. What data we collect and why

We only collect data that we need for our humanitarian work. The table below lists every item
collected at registration, whether it is required, and why we need it.

People register in one of two ways: on the public registration form, or through staff, who use a
separate staff form for people without an email address. Where the two forms differ, the table says
so.

| Data | Required? | Why we need it |
|---|---|---|
| First, middle and last name | First and last name required; middle name optional | To identify the member or volunteer, print the ID card and certificates, and address them correctly in messages. |
| Title (Mr, Mrs, Dr…) | Optional, staff form only | To address people respectfully in letters and messages. |
| Gender | Required | Humanitarian standards require data separated by sex, so we can plan programmes and make sure response teams include women and men (for example when female beneficiaries need female volunteers). Also used for statistics. |
| Year of birth | Required | To plan age-appropriate activities and youth programmes, and for age statistics. It also helps identify the right account when several people share one phone number. We collect only the year, not the full date, to collect as little as possible. |
| National Identification Number (NIN) | Optional | Printed on the Red Cross ID card, linking the card to the holder's national identity. This makes the card trustworthy at checkpoints and during emergency deployments. The system refuses a NIN that is already registered to another account, which helps prevent duplicate or false registrations. It is optional because not all Nigerians have a NIN. It is stored encrypted. |
| Education / field of study, and occupation | Optional | To know which members and volunteers have useful skills (for example health workers, engineers, drivers or logisticians), so they can be called on in an emergency. At present these are shown on the person's record; the system cannot yet search by skill. |
| Telephone number | Required | The main way to contact members and volunteers, especially by SMS, since many do not have email. Used to mobilise volunteers in emergencies, and as the login name for people without email. |
| Second telephone number | Optional | A backup contact when the main number cannot be reached, which matters in emergencies. |
| Email address | Required for self-registration; optional when staff register someone | For logging in, for messages, and for online payments (the payment provider requires an email address). |
| Branch and division | Required | To place the person in the Society's structure, so the right branch can manage the record. Apart from national staff, only staff of that branch or division can see it. |
| Red Cross unit | Not asked on the public form; on the staff form, required for volunteers and not used for members | Volunteers work through a Red Cross unit. The unit is how volunteers are organised, and other members of the same unit can see each other's names. |
| Type of involvement (member or volunteer) | Required | Decides whether the person is managed as a member (membership fees) or as a volunteer (Red Cross unit and volunteering activities). |
| Password | Required | To protect the account. It is stored in a form that cannot be read back. |
| Photo | Optional; on the public form only, otherwise added after registration | Printed on the ID card so the holder can be recognised. |
| Signature | Optional, added after registration | Printed on the ID card. Not used for anything else. |
| Consent and Code of Conduct confirmation | Required | Proof that the person agreed to the Code of Conduct and to the processing of their data, as the law requires. See section 4. |
| Marital status | Optional | Under review. No current use beyond display; NRCS will decide whether to keep collecting it. |
| Residential address | Optional | Under review. Could help locate the nearest unit for mobilisation, but it is not currently used for that; NRCS will decide whether it is needed. |
| Workplace address | Optional | Under review. No current use; NRCS will decide whether to keep collecting it. |
| Organisation (free text) | Optional | Under review. No current use; NRCS will decide whether to keep collecting it. |
| "Personal information" (free text) | Optional | Under review. Lets the person describe skills or other relevant facts. It is stored encrypted. |

### Data recorded by the system

Besides what people enter, the system records:

- the dates of each person's last login and last activity, for security and to find inactive
  accounts;
- their trainings, volunteering activities, membership payments and donations;
- the dates when consent was given, when an account was archived and when it was anonymized, and
  who did it;
- an Audit Log of important administrative actions (see section 5).

While someone is logged in, their login session records the internet (IP) address and browser they
use, as is normal for any website. When someone pays online, the confirmation received from the
payment provider is kept with the payment record; it can include the payer's email and the internet
address used for the payment.

## 4. Consent

- **New registrations.** The person reads the Code of Conduct and ticks four confirmations, the
  fourth being consent to the processing of their personal data under the NDPA. They cannot register
  without all four. The date is recorded.
- **Registration by staff** (for people without email). The staff member confirms that the person
  was told about the Code of Conduct and gave consent, and that the form of consent has been
  recorded (spoken, a signed paper form, or another documented way). They can note which form was
  used. The staff member and the date are recorded. When the person later logs in, they are also
  asked to confirm the Code of Conduct and consent themselves.
- **Existing members from the old database.** The first time they log in to the new system, they
  must read the Code of Conduct and confirm it, together with consent, before they can continue. The
  date is shown on their record.
- **Members who cannot log in.** About 27,000 imported records (counted on a copy of the database in
  October 2026) have neither an email address nor a phone number, so these people cannot log in.
  Their consent therefore cannot be confirmed online. NRCS must decide how consent is handled for
  them, for example on paper at their next contact with their branch.

## 5. Who can see the data

- **Access by role.** Staff can see only the records their role allows. National roles see
  members across the country; branch roles see their own branch; division roles see their own
  division.
- **Members and volunteers** see their own record. Volunteers can also see the names of the other
  members of their own Red Cross unit (and their photos, if they choose to load them), and members
  of a task force can see their teammates.
- **Staff commitment.** Before they can use the system, every staff member with an administrative
  role must accept a data handling commitment: to access only what their role needs, not to share
  data outside NRCS channels, and to handle it under the NDPA.
- **Encryption.** The NIN and the "personal information" text are stored encrypted. The NIN is
  printed on the ID card.
- **Photos and signatures** are kept in a protected area. Only logged-in users who are allowed to
  see that person can view them, and the system never places them on a public web page.
- **Public ID card check.** Anyone who scans the QR code on an ID card can see the holder's name,
  branch, division, unit or membership type, membership expiry, trainings and volunteering hours,
  but not their photo. Whether this is the right amount of information is still to be decided.
- **Re-entering the password.** Changing settings, managing roles, managing membership fees and
  training types, and anonymizing an account all require the staff member to enter their password
  again.
- **Two-person approval ("four eyes").** Donations, membership payments, trainings and volunteering
  hours entered by staff only count once a different authorised person has approved them; nobody
  can approve their own entry. Online payments confirmed by the payment provider are approved
  automatically.
- **Audit Log.** Important administrative actions are recorded with who did them and when. These
  include role and permission changes, moving a person to another branch or division, changes to a
  person's NIN, personal information, photo or signature (which items changed, not the values),
  archiving, restoring and anonymizing accounts, consent confirmations, settings changes, changes to
  membership fees, membership payments added or deleted, and message campaigns being approved and
  sent. Not every change is recorded, and looking at a record or photo is not recorded.

## 6. How long we keep data — archiving and anonymization

- **Archiving.** Accounts that are no longer needed can be archived by staff, by the person
  themselves, or when duplicate accounts are merged. An archived person cannot log in. The system
  records when the account was archived and by whom. Until it is anonymized, an archived account
  can be restored by staff.
- **Automatic anonymization after seven years.** A job scheduled to run every night anonymizes
  accounts that have been archived for seven years. Name, contact details, NIN, addresses, photo,
  signature and similar details are permanently removed, including copies in message records,
  payment records and the Audit Log. Only gender, branch, division, unit and age group (the decade
  of birth, for example 1980–1989) are kept, for statistics. Training, activity and payment records
  are kept without a name, for statistics and accounting.
- **Earlier anonymization on request.** The National Database Administrator can anonymize an
  archived account earlier, on request. Before doing so they must confirm that they have consulted
  the DPO, and enter their password again. The anonymization is recorded in the Audit Log without
  the person's name. It cannot be undone.
- **Administrative roles.** An account with an administrative role must have the role removed
  before it can be archived or anonymized.
- **Limits.** Backups keep earlier data until they expire (backup period: to be confirmed). Copies
  of photos on the old database server are not affected and should be deleted when the old system
  is shut down.

## 7. Rights of members and volunteers

- **To see their data.** Most of it is shown on their profile page after logging in.
- **To correct it.** They can update most of their own details on their profile page; other
  corrections are made by their branch.
- **To withdraw consent.** They can contact their branch or the DPO. They can also stop email and
  SMS messages at any time on their profile page or with the unsubscribe link in each message.
- **To archive their own account,** from their profile page, if they hold no administrative role.
- **To ask for anonymization,** through their branch or the DPO. It is carried out by the National
  Database Administrator after consulting the DPO.
- **To complain** to the Nigeria Data Protection Commission (NDPC).

## 8. Privacy Policy

A public Privacy Policy page exists. It is linked from the registration form, the consent
confirmation page and the profile page, and anyone can open it without logging in. The current text
is a draft and is marked as such on the page. The NRCS Legal department will provide the final text.

## 9. Changes made after the Legal review (October 2026)

- Every registration field now has a stated purpose (section 3), and fields without a clear purpose
  are marked "Under review".
- The DPO's contact details are kept in the database settings and shown to members and staff.
- A public Privacy Policy page was added.
- Existing members confirm the Code of Conduct and consent when they first log in. The date of
  registration consent is now recorded correctly.
- The date of archiving, and who archived the account, are now recorded.
- Archived accounts are anonymized after seven years, or earlier on request after consulting the
  DPO.
- Phone numbers were removed from the "My unit" page, which every member of a unit can open.
- The signature page now explains why the signature is needed and how it is protected.

Technical details and open items are recorded in Decisions.md.
