# Campaign Sending – Production Readiness TODO

## P0 – Must-have before real production sending

- [ ] Add scheduler entry to run `campaigns:send` automatically
  - Location: app/Console/Kernel.php (or equivalent in Laravel 12)
  - Schedule: every minute
  - Use withoutOverlapping()

- [ ] Add distributed lock inside campaigns:send
  - Use Cache::lock('campaigns:send')
  - Prevent manual + scheduler overlap

- [ ] Add dedicated log channel for deliveries
  - File: config/logging.php
  - Channel name: campaign_deliveries
  - Output: storage/logs/campaign_deliveries.log

- [ ] Log per-recipient delivery outcome
  - Location: DeliveryChannel implementations
  - Fields to log:
  - campaign_id
  - recipient_id
  - channel (email/sms)
  - destination (email/phone)
  - dry_run
  - outcome (sent/failed)
  - error (if any)

- [ ] Add admin monitor page for active campaigns
  - Show:
  - status
  - last_send_run_at
  - daily_sent_count
  - stats_total / sent / failed
  - Purpose: see progress without logs


## P1 – Should-have (stability & operability)

- [ ] Move send-loop logic into CampaignSendRunner only
  - Artisan command and controller should both call runner
  - Single source of truth for sending logic

- [ ] Add safety guard against stuck campaigns
  - If status = sending AND no pending recipients → mark sent
  - Or alert in monitor view

- [ ] Add retry visibility
  - Show count of failed / undeliverable recipients
  - Provide “reset to pending” action (already partially exists)

- [ ] Add explicit delivery outcome enums / constants
  - Avoid magic strings: sent, failed, bounced, undeliverable


## P2 – Nice-to-have / later

- [ ] Add real email provider (SES / SendGrid / SMTP)
  - Implement EmailChannel
  - Register via config/campaigns.php only

- [ ] Add real SMS provider (Twilio / etc.)
  - Implement SmsChannel
  - Register via config only

- [ ] Add queue-based sending for very large campaigns
  - Optional: move CampaignSendRunner to queue job

- [ ] Add delivery metrics aggregation
  - Success rate
  - Failure reasons distribution

- [ ] Add alerting (Slack / email) on high failure rates


## Notes

- Current system is valid for development and dry-run testing.
- No user-facing risk as long as:
    - Sending is manual
    - Dry-run is enabled
- Do NOT add providers until P0 is complete.
