<?php

namespace App\Http\Controllers;

use App\Models\CampaignPurpose;
use App\Models\MessagingCampaign;
use App\Models\User;
use App\Services\CampaignAudienceSummaryService;
use App\Services\CampaignContentValidator;
use App\Services\PlaceholderBracketValidator;
use App\Services\UrlDomainValidator;
use App\Services\UserFilterService;
use App\Support\CampaignPlaceholderRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CampaignWizardController extends Controller
{
    public function start(Request $request)
    {
        $user = Auth::user();

        $filterJson = $request->input('filter_json');

        // If it comes as a JSON string, decode it.
        if (is_string($filterJson)) {
            $filterJson = json_decode($filterJson, true);
        }

        // Fallback if empty/invalid
        if (! is_array($filterJson)) {
            $filterJson = $request->query(); // works for GET start
            unset($filterJson['page']);
        }

        $campaign = MessagingCampaign::create([
            'channel' => 'email_fallback_sms',
            'audience_type' => $filterJson['audience_type'] ?? 'volunteer',
            'title' => null,
            'subject' => null,
            'body' => ' ',
            'filter_json' => $filterJson,
            'status' => 'draft',

            'scope_level' => $user->getAccessLevel(),
            'scope_id' => $user->getScopedId(),
            'lifecycle' => $filterJson['archived_filter'] ?? null, // optional; or map properly later

            'origin_level' => $user->getAccessLevel() === 'national' ? 'national' : 'branch',
            'origin_branch_id' => $user->getScopedBranchId(),

            'created_by' => $user->id,
        ]);

        return redirect()->route('campaigns.wizard.step1', $campaign);
    }

    public function step1(MessagingCampaign $campaign)
    {
        $this->authorizeWizardAccess($campaign);

        $filterDescriptionHtml = $campaign->filter_description_html;
        $channelLabel = $campaign->channel_label;
        $purposes = CampaignPurpose::active()->orderBy('sort_order')->get();

        $filterJson = is_array($campaign->filter_json) ? $campaign->filter_json : [];
        $wizardPurposeHint = $filterJson['wizard_purpose'] ?? null;
        $preselectedPurposeId = $campaign->purpose_id;

        if (! $preselectedPurposeId && $wizardPurposeHint) {
            $matched = $purposes->first(function ($p) use ($wizardPurposeHint) {
                return str_contains(strtolower($p->name), strtolower($wizardPurposeHint));
            });
            $preselectedPurposeId = $matched?->id;
        }

        return view('campaigns.wizard.step-1-purpose', [
            'campaign' => $campaign,
            'filterDescriptionHtml' => $filterDescriptionHtml,
            'channelLabel' => $channelLabel,
            'purposes' => $purposes,
            'preselectedPurposeId' => $preselectedPurposeId,
        ]);
    }

    public function postStep1(Request $request, MessagingCampaign $campaign)
    {
        $this->authorizeWizardAccess($campaign);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255', 'min:3'],
            'purpose_id' => ['required', 'exists:campaign_purposes,id'],
            'channel' => ['required', 'in:email,email_fallback_sms,sms,both'],
        ], [
            'title.required' => 'Please enter a description for this campaign.',
            'title.min' => 'The description must be at least 3 characters.',
            'purpose_id.required' => 'Please select a purpose before continuing.',
            'purpose_id.exists' => 'The selected purpose is invalid.',
        ]);

        // Load purpose defaults if a purpose was selected
        $purpose = $data['purpose_id']
            ? CampaignPurpose::find($data['purpose_id'])
            : null;

        // Auto-fill channel from purpose default if not overridden
        $channel = $data['channel'];
        if ($purpose && ! $request->filled('channel_override')) {
            $channel = $purpose->default_channel;
        }

        // Auto-fill subject and body into filter_json['_content']
        // Only pre-fill if _content is not already set (don't overwrite user edits)
        $filter = $campaign->filter_json ?? [];
        $content = data_get($filter, '_content', []);
        if (! is_array($content) || array_is_list($content)) {
            $content = [];
        }

        if ($purpose) {
            // Pre-fill subject only if not already set
            if (empty($content['email_subject']) && $purpose->default_subject) {
                $content['email_subject'] = $purpose->default_subject;
            }
            // Pre-fill email body only if not already set
            if (empty($content['email_body']) && $purpose->default_email_body) {
                $content['email_body'] = $purpose->default_email_body;
            }
            // Pre-fill SMS body only if not already set
            if (empty($content['sms_body']) && $purpose->default_sms_body) {
                $content['sms_body'] = $purpose->default_sms_body;
            }
        }

        $filter['_content'] = $content;

        $campaign->update([
            'title' => $data['title'] ?? $campaign->title,
            'purpose_id' => $data['purpose_id'] ?? null,
            'channel' => $channel,
            'subject' => $content['email_subject'] ?? $campaign->subject,
            'body' => $content['email_body'] ?? ($content['sms_body'] ?? $campaign->body),
            'filter_json' => $filter,
        ]);

        return redirect()->route('campaigns.wizard.step2', $campaign);
    }

    public function step2(MessagingCampaign $campaign, UserFilterService $userFilterService, CampaignAudienceSummaryService $audienceSummaryService)
    {
        $this->authorizeWizardAccess($campaign);

        $admin = Auth::user();

        $filters = is_array($campaign->filter_json) ? $campaign->filter_json : [];

        // Computed accessor on model (no DB column needed)
        $filterDescriptionHtml = $campaign->filter_description_html;

        // Build the exact same base query as UsersController@index
        $baseQuery = User::query()
            ->with(['branch', 'division', 'redCrossUnit'])
            ->where('is_super_admin', false);

        // Apply EXACT same filter rules
        $filteredQuery = $userFilterService->apply(
            $baseQuery,
            $filters,
            $admin->getAccessLevel(),
            $admin->getScopedId()
        );

        // Reflect chosen channel from step1
        $channel = $campaign->channel;

        $channelLabel = $campaign->channel_label;

        $summary = $audienceSummaryService->summarize($filteredQuery, $channel);
        $matchedTotal = $summary['matchedTotal'];
        $emailContactable = $summary['emailContactable'];
        $smsContactable = $summary['smsContactable'];
        $willEmail = $summary['willEmail'];
        $willSms = $summary['willSms'];
        $willReach = $summary['willReach'];
        $mayReceiveTwo = $summary['mayReceiveTwo'];
        $noReach = $summary['noReach'];

        // Sample recipients (first 20)
        $sample = (clone $filteredQuery)
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'telephone1',
                'telephone2',
                'lifecycle_status',
                'branch_id',
                'division_id',
                'red_cross_unit_id',
            ])
            ->limit(20)
            ->get();

        return view('campaigns.wizard.step-2-audience', [
            'campaign' => $campaign,
            'filters' => $filters,

            'matchedTotal' => $matchedTotal,
            'emailContactable' => $emailContactable,
            'smsContactable' => $smsContactable,

            'channel' => $channel,
            'channelLabel' => $channelLabel,
            'willEmail' => $willEmail,
            'willSms' => $willSms,
            'willReach' => $willReach,
            'mayReceiveTwo' => $mayReceiveTwo,
            'noReach' => $noReach,

            'sample' => $sample,
            'filterDescriptionHtml' => $filterDescriptionHtml,
        ]);
    }

    public function postStep2(Request $request, MessagingCampaign $campaign)
    {
        $this->authorizeWizardAccess($campaign);

        $filter = is_array($campaign->filter_json) ? $campaign->filter_json : [];

        if ($request->boolean('org_representatives')) {
            $filter['org_representatives'] = '1';
        } else {
            unset($filter['org_representatives']);
        }

        $filter['_audience_total'] = $request->integer('_audience_total');

        $campaign->update(['filter_json' => $filter]);

        return redirect()->route('campaigns.wizard.step3', $campaign);
    }

    public function step3(MessagingCampaign $campaign)
    {
        $this->authorizeWizardAccess($campaign);

        $filter = is_array($campaign->filter_json) ? $campaign->filter_json : [];
        $throttling = data_get($filter, '_throttling', []);

        // Default the "phone calls / call window" toggle from the purpose,
        // but respect the user's own choice if they've already visited Step 3.
        if (array_key_exists('encourages_phone_calls', $throttling)) {
            $encouragesPhoneCallsDefault = (bool) $throttling['encourages_phone_calls'];
        } else {
            $purpose = $campaign->purpose_id ? CampaignPurpose::find($campaign->purpose_id) : null;
            $encouragesPhoneCallsDefault = $purpose ? (bool) $purpose->default_call_window : false;
        }

        return view('campaigns.wizard.step-3-throttling', [
            'campaign' => $campaign,
            'encouragesPhoneCallsDefault' => $encouragesPhoneCallsDefault,
        ]);
    }

    public function postStep3(Request $request, MessagingCampaign $campaign)
    {
        $this->authorizeWizardAccess($campaign);

        // Normalize single-digit hours: "8:00" → "08:00"
        foreach (['send_window_start', 'send_window_end'] as $field) {
            $val = trim((string) $request->input($field, ''));
            if (preg_match('/^(\d):(\d{2})$/', $val, $m)) {
                $request->merge([$field => '0'.$val]);
            }
        }

        $data = $request->validate(
            [
                'send_window_start' => ['nullable', 'date_format:H:i'],
                'send_window_end' => ['nullable', 'date_format:H:i'],
                'daily_cap' => ['nullable', 'integer', 'min:1', 'max:10000'],
                'encourages_phone_calls' => ['nullable', 'boolean'],
            ],
            [
                'send_window_start.date_format' => 'Please enter the time as HH:MM (for example, 08:00).',
                'send_window_end.date_format' => 'Please enter the time as HH:MM (for example, 20:00).',
            ]
        );

        // --------------------------------------------------
        // Custom validation: send window logic
        // --------------------------------------------------
        $start = $data['send_window_start'] ?? null;
        $end = $data['send_window_end'] ?? null;
        $callsExpected = ! empty($data['encourages_phone_calls']);

        // If phone calls expected, both window times are required
        if ($callsExpected && (! $start || ! $end)) {
            $errors = [];
            if (! $start) {
                $errors['send_window_start'] = 'A send window start time is required when phone calls are expected.';
            }
            if (! $end) {
                $errors['send_window_end'] = 'A send window end time is required when phone calls are expected.';
            }

            return back()->withErrors($errors)->withInput();
        }

        // If only one is set → error
        if (($start && ! $end) || (! $start && $end)) {
            return back()
                ->withErrors([
                    'send_window_start' => 'Please set both start and end times, or leave both empty.',
                    'send_window_end' => 'Please set both start and end times, or leave both empty.',
                ])
                ->withInput();
        }

        // If both are set, start must be before end
        if ($start && $end && $start >= $end) {
            return back()
                ->withErrors([
                    'send_window_start' => 'Start time must be earlier than end time.',
                    'send_window_end' => 'End time must be later than start time.',
                ])
                ->withInput();
        }

        // --------------------------------------------------
        // Store throttling settings in filter_json
        // --------------------------------------------------
        $filter = $campaign->filter_json ?? [];
        $filter['_throttling'] = [
            'send_window_start' => $start,
            'send_window_end' => $end,
            'daily_cap' => $data['daily_cap'] ?? null,
            'encourages_phone_calls' => ! empty($data['encourages_phone_calls']),
        ];

        $campaign->update([
            'filter_json' => $filter,
        ]);

        return redirect()->route('campaigns.wizard.step4', $campaign);
    }

    public function step4(MessagingCampaign $campaign)
    {
        $this->authorizeWizardAccess($campaign);

        $placeholders = [
            '{{user.first_name}}' => 'First name',
            '{{user.last_name}}' => 'Last name',
            '{{user.full_name}}' => 'Title & full name',
            '{{user.phone}}' => 'Phone',
            '{{user.email}}' => 'Email',
            '{{user.branch}}' => 'Branch',
            '{{user.division}}' => 'Division',
            '{{user.red_cross_unit}}' => 'Red Cross Unit',
            '{{user.db_code_short}}' => 'DB-Code',
            '{{user.lifecycle}}' => 'Lifecycle status',
            '{{user.donations_summary}}' => 'Donations summary',
            '{{user.current_membership}}' => 'Current membership',
            '{{user.time_since_last_first_aid}}' => 'Time since last first aid',

        ];

        return view('campaigns.wizard.step-4-message', [
            'campaign' => $campaign,
            'placeholders' => $placeholders,
        ]);
    }

    public function postStep4(Request $request, MessagingCampaign $campaign)
    {
        $this->authorizeWizardAccess($campaign);

        $channel = $campaign->channel ?? 'both'; // email | sms | both | email_fallback_sms

        // IMPORTANT: keep channel logic consistent with the Blade (Step 4 tabs)
        $hasEmail = in_array($channel, ['email', 'both', 'email_fallback_sms'], true);
        $hasSms = in_array($channel, ['sms', 'both', 'email_fallback_sms'], true);

        $rules = [
            'from_name' => $hasEmail ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'reply_to_email' => $hasEmail ? ['required', 'email', 'max:255'] : ['nullable', 'email', 'max:255'],
            'from_phone' => ['nullable', 'string', 'max:255'],
            '_tab' => ['nullable', 'in:email,sms'],
        ];

        // Content rules based on channel
        if ($hasEmail) {
            $rules['subject'] = ['required', 'string', 'max:255'];
            $rules['email_body'] = ['required', 'string', 'min:20', 'max:8000'];
        } else {
            $rules['subject'] = ['nullable', 'string', 'max:255'];
            $rules['email_body'] = ['nullable', 'string', 'max:8000'];
        }

        if ($hasSms) {
            $rules['sms_body'] = ['required', 'string', 'min:10', 'max:800'];
        } else {
            $rules['sms_body'] = ['nullable', 'string', 'max:800'];
        }

        $data = $request->validate($rules);

        // -------------------------------------------
        // Unresolved placeholder warning (non-blocking)
        // Returns back with warning messages but does NOT
        // prevent saving — user must fix or consciously proceed.
        // -------------------------------------------
        $unresolvedErrors = [];
        $tagPattern = '/@?\{\{\s*([^}]*?)\s*\}\}/';

        $knownPlaceholders = [
            'user.first_name', 'user.last_name', 'user.full_name',
            'user.email', 'user.phone', 'user.branch', 'user.division',
            'user.red_cross_unit', 'user.db_code_short', 'user.db_code_long',
            'user.lifecycle', 'user.donations_summary', 'user.current_membership',
            'user.membership_expiry', 'user.time_since_last_first_aid',
        ];

        $findUnknownTags = function (string $text) use ($knownPlaceholders): array {
            preg_match_all('/@?\{\{\s*([^}]*?)\s*\}\}/', $text, $m, PREG_SET_ORDER);
            $unknown = [];
            foreach ($m as $match) {
                $key = trim($match[1]);
                if (! in_array($key, $knownPlaceholders, true)) {
                    $unknown[] = $match[0];
                }
            }

            return array_unique($unknown);
        };

        if ($hasEmail) {
            $unknown = $findUnknownTags((string) ($data['email_body'] ?? ''));
            if (! empty($unknown)) {
                $unresolvedErrors['unresolved_email_body'] =
                    'Email body contains unknown placeholders: '.implode(', ', $unknown).'. Check for typos.';
            }
            $unknown = $findUnknownTags((string) ($data['subject'] ?? ''));
            if (! empty($unknown)) {
                $unresolvedErrors['unresolved_email_subject'] =
                    'Email subject contains unknown placeholders: '.implode(', ', $unknown).'.';
            }
        }

        if ($hasSms) {
            $unknown = $findUnknownTags((string) ($data['sms_body'] ?? ''));
            if (! empty($unknown)) {
                $unresolvedErrors['unresolved_sms_body'] =
                    'SMS body contains unknown placeholders: '.implode(', ', $unknown).'. Check for typos.';
            }
        }

        if (! empty($unresolvedErrors) && ! $request->boolean('confirm_unresolved')) {
            return back()
                ->withErrors($unresolvedErrors)
                ->withInput();
        }

        // -------------------------------------------
        // Custom URL domain validation (simple allow-list)
        // -------------------------------------------
        $domainValidator = app(UrlDomainValidator::class);
        $bodiesToCheck = [];

        if ($hasEmail) {
            $bodiesToCheck['email_body'] = (string) ($data['email_body'] ?? '');
        }
        if ($hasSms) {
            $bodiesToCheck['sms_body'] = (string) ($data['sms_body'] ?? '');
        }

        $disallowedByField = $domainValidator->findDisallowedDomainsByField($bodiesToCheck);

        if (! empty($disallowedByField)) {
            $allowList = $domainValidator->allowedDomains();
            $allowListText = empty($allowList)
                ? 'no approved domains configured'
                : implode(', ', $allowList);
            $errors = [];

            foreach ($disallowedByField as $field => $domains) {
                $errors[$field] = 'Links must use approved domains ('.$allowListText.'). NOT ALLOWED: '.implode(', ', $domains).'.';
            }

            return back()->withErrors($errors)->withInput();
        }

        // -------------------------------------------
        // Placeholder bracket validation ('[' / ']' left unfilled)
        // -------------------------------------------
        $bracketValidator = app(PlaceholderBracketValidator::class);
        $bracketFields = $bracketValidator->findBracketPlaceholders($bodiesToCheck);

        if (! empty($bracketFields)) {
            $errors = [];

            foreach ($bracketFields as $field) {
                $label = $field === 'sms_body' ? 'SMS body' : 'Email body';
                $errors[$field] = "{$label} still contains a placeholder marker ([...]). Please replace the bracketed text with your actual content before submitting.";
            }

            return back()->withErrors($errors)->withInput();
        }

        // Locked sender email (do NOT accept from request)
        $lockedFromEmail = config('campaigns.mail_from_email', $campaign->from_email ?? 'info@nrcs.org');

        // Canonical content lives in filter_json['_content']
        $filter = $campaign->filter_json ?? [];
        $content = data_get($filter, '_content', []);

        // Normalize: sometimes _content can be stored as [] (a JSON list)
        // We want it to behave like an associative array.
        if (! is_array($content) || array_is_list($content)) {
            $content = [];
        }

        // Only write keys relevant to the channel, to avoid stale/unused content
        if ($hasEmail) {
            $content['email_subject'] = $data['subject'] ?? '';
            $content['email_body'] = $data['email_body'] ?? '';
        } else {
            unset($content['email_subject'], $content['email_body']);
        }

        if ($hasSms) {
            $content['sms_body'] = $data['sms_body'] ?? '';
        } else {
            unset($content['sms_body']);
        }

        $filter['_content'] = $content;

        $campaign->update([
            // “Identity” fields (still columns)
            'from_name' => $data['from_name'] ?? null,
            'from_email' => $lockedFromEmail,
            'reply_to_email' => $data['reply_to_email'] ?? null,
            'from_phone' => $data['from_phone'] ?? null,

            // Optional: keep legacy columns in sync for now (helps Step 5 / older code)
            'subject' => $content['email_subject'] ?? null,
            'body' => $content['email_body'] ?? ($content['sms_body'] ?? $campaign->body),

            // Store canonical content in filter_json
            'filter_json' => $filter,
        ]);

        return redirect()->route('campaigns.wizard.step5', $campaign);
    }

    public function step5(MessagingCampaign $campaign, UserFilterService $userFilterService, CampaignAudienceSummaryService $audienceSummaryService)
    {
        $this->authorizeWizardAccess($campaign);

        $admin = Auth::user();

        // ✅ Robust: filter_json may be array OR JSON string OR null
        $filterRaw = $campaign->filter_json ?? [];
        $filters = is_array($filterRaw)
            ? $filterRaw
            : (json_decode((string) $filterRaw, true) ?: []);

        $throttling = data_get($filters, '_throttling', []);
        $content = data_get($filters, '_content', []);

        // Build same base query as UsersController@index
        $baseQuery = User::query()
            ->with(['branch', 'division', 'redCrossUnit'])
            ->where('is_super_admin', false);

        $filteredQuery = $userFilterService->apply(
            $baseQuery,
            $filters,
            $admin->getAccessLevel(),
            $admin->getScopedId()
        );

        $channel = $campaign->channel ?? 'both';

        $summary = $audienceSummaryService->summarize($filteredQuery, $channel);
        $matchedTotal = $summary['matchedTotal'];
        $willEmail = $summary['willEmail'];
        $willSms = $summary['willSms'];
        $willReach = $summary['willReach'];
        $mayReceiveTwo = $summary['mayReceiveTwo'];
        $noReach = $summary['noReach'];

        // ----------------------------
        // ✅ Message drafts (same logic as Blade)
        // ----------------------------
        $emailSubject = (string) data_get($content, 'email_subject', $campaign->subject ?? '');
        $emailBody = (string) data_get($content, 'email_body', (string) ($campaign->body ?? ''));
        $smsBody = (string) data_get($content, 'sms_body', '');

        // ----------------------------
        // ✅ Sample recipients (INCLUDE id!)
        // ----------------------------
        $sampleUsers = (clone $filteredQuery)
            ->select([
                'id', 'first_name', 'last_name', 'email', 'telephone1', 'telephone2',
                'lifecycle_status', 'branch_id', 'division_id', 'red_cross_unit_id',
            ])
            ->limit(20)
            ->get();

        $sample = $sampleUsers
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: '—',
                    'email' => $u->email,
                    'phone' => $u->telephone1 ?: $u->telephone2,
                ];
            })
            ->toArray();

        // ----------------------------
        // ✅ Personalised previews for Step 5
        // Keyed by sample user id
        // ----------------------------
        // Opt-out footer appended to every SMS at send time (CampaignSendRunner).
        // Placeholder token is a fixed 32 X's, matching the real id_check_token
        // length (Str::random(32)) exactly, so preview counts match reality.
        $hasSms = in_array($channel, ['sms', 'both', 'email_fallback_sms'], true);
        $smsFooterPlaceholderToken = str_repeat('X', 32);
        $smsFooterSuffix = "\nTo stop: ".config('app.url').'/u/'.$smsFooterPlaceholderToken.'/sms';

        $samplePreviews = [];
        foreach ($sampleUsers as $u) {
            $label = trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: ('User #'.$u->id);

            $renderedSmsBody = trim(CampaignPlaceholderRenderer::render($smsBody, $u));
            if ($hasSms && $renderedSmsBody !== '') {
                $renderedSmsBody .= $smsFooterSuffix;
            }

            $samplePreviews[$u->id] = [
                'label' => $label,
                'email_subject' => CampaignPlaceholderRenderer::render($emailSubject, $u),
                'email_body' => CampaignPlaceholderRenderer::render($emailBody, $u),
                'sms_body' => $renderedSmsBody,
            ];
        }

        $audience = [
            'total' => $matchedTotal,
            'human_summary_html' => $campaign->filter_description_html,
            'samples' => $sample,
            'willReach' => $willReach,
            'willEmail' => $willEmail,
            'willSms' => $willSms,
            'mayReceiveTwo' => $mayReceiveTwo,
            'noReach' => $noReach,
        ];

        $warnings = [];
        $blockers = [];

        if ($matchedTotal === 0) {
            $warnings[] = 'Audience estimate is 0. Double-check filters in Step 2.';
        }

        // Nice extra warnings for preview quality (optional but useful)
        if (trim($emailBody) === '' && in_array($channel, ['email', 'both', 'email_fallback_sms'], true)) {
            $warnings[] = 'Email body looks empty. Double-check message content in Step 4.';
        }
        if (trim($smsBody) === '' && in_array($channel, ['sms', 'both', 'email_fallback_sms'], true)) {
            $warnings[] = 'SMS body looks empty. Double-check message content in Step 4.';
        }

        $domainValidator = app(UrlDomainValidator::class);
        $disallowedDomains = $domainValidator->findDisallowedDomains([
            $emailBody,
            $smsBody,
        ]);

        if (! empty($disallowedDomains)) {
            $allowList = implode(', ', $domainValidator->allowedDomains());
            $blockers[] = 'Unapproved link domains found: '.implode(', ', $disallowedDomains).'. Allowed domains: '.$allowList.'.';
        }

        return view('campaigns.wizard.step-5-review', [
            'campaign' => $campaign,
            'throttling' => $throttling,
            'audience' => $audience,
            'content' => $content,
            'warnings' => $warnings,
            'blockers' => $blockers,

            // ✅ NEW for personalised message preview
            'samplePreviews' => $samplePreviews,
        ]);
    }

    /**
     * Keep this private in controller for now.
     * You can move it to a service later.
     */
    private function buildStep5Flags(MessagingCampaign $campaign, array $filter, array $throttling, array $content): array
    {
        $warnings = [];
        $blockers = [];

        $channel = $campaign->channel ?? 'both';
        $hasEmail = in_array($channel, ['email', 'both'], true);
        $hasSms = in_array($channel, ['sms', 'both'], true);

        $emailSubject = trim((string) ($content['email_subject'] ?? ''));
        $emailBody = trim((string) ($content['email_body'] ?? ''));
        $smsBody = trim((string) ($content['sms_body'] ?? ''));

        if ($hasEmail && $emailSubject === '') {
            $blockers[] = 'Email subject is missing.';
        }
        if ($hasEmail && $emailBody === '') {
            $blockers[] = 'Email body is missing.';
        }
        if ($hasSms && $smsBody === '') {
            $blockers[] = 'SMS message is missing.';
        }

        // Basic SMS size warning
        if ($hasSms && strlen($smsBody) > 320) {
            $warnings[] = 'SMS is long and will likely split into multiple messages.';
        }

        // Multiple links warning
        $combined = $emailBody."\n".$smsBody;
        preg_match_all('/https?:\/\/\S+|www\.\S+/i', $combined, $m);
        if (! empty($m[0]) && count($m[0]) > 1) {
            $warnings[] = 'Multiple links detected. Consider using only one official link.';
        }

        // Phone-call hint warning
        $callsExpected = (bool) data_get($throttling, 'encourages_phone_calls', false);
        $winStart = trim((string) data_get($throttling, 'send_window_start', ''));
        $winEnd = trim((string) data_get($throttling, 'send_window_end', ''));

        if ($callsExpected && (! $winStart || ! $winEnd)) {
            $warnings[] = 'Phone calls expected, but call window is not set in Step 3.';
        }

        // Empty audience (if you have a real total)
        $total = (int) data_get($filter, '_audience_total', 0);
        if ($total === 0) {
            $warnings[] = 'Audience estimate is 0. Double-check filters in Step 2.';
        }

        return [$warnings, $blockers];
    }

    public function submit(Request $request, MessagingCampaign $campaign)
    {
        $this->authorizeWizardAccess($campaign);

        $data = $request->validate([
            'confirm_audience' => ['accepted'],
            'confirm_content' => ['accepted'],

            'confirm_calls' => ['nullable'], // Conditional check is handled below
        ]);

        $filter = $campaign->filter_json ?? [];
        $throttling = data_get($filter, '_throttling', []);
        $content = data_get($filter, '_content', []);

        $callsExpected = (bool) data_get($throttling, 'encourages_phone_calls', false);
        if ($callsExpected && ! $request->boolean('confirm_calls')) {
            return back()->withErrors([
                'confirm_calls' => 'Please confirm call window handling before submitting.',
            ]);
        }

        // Completeness: prefer filter_json content (Step 4 canonical)
        $channel = $campaign->channel ?? 'both';
        $hasEmail = in_array($channel, ['email', 'both'], true);
        $hasSms = in_array($channel, ['sms', 'both'], true);

        $emailOk = ! $hasEmail || (trim((string) ($content['email_body'] ?? '')) !== '' && trim((string) ($content['email_subject'] ?? '')) !== '');
        $smsOk = ! $hasSms || trim((string) ($content['sms_body'] ?? '')) !== '';

        if (! $emailOk || ! $smsOk) {
            return back()->with('error', 'Campaign is incomplete. Please review Step 4 message content.');
        }

        $contentErrors = app(CampaignContentValidator::class)->validate($campaign);

        if (! empty($contentErrors)) {
            return back()->withErrors([
                'confirm_content' => implode(' ', $contentErrors),
            ])->withInput();
        }

        $campaign->update([
            'status' => 'proposed',
            'submitted_at' => now(),
            'submitted_by' => Auth::id(),
        ]);

        return redirect()->route('campaigns.mine', ['status' => 'submitted'])
            ->with('success', 'Campaign submitted for approval.');

    }

    protected function authorizeWizardAccess(MessagingCampaign $campaign): void
    {
        $user = Auth::user();

        // Only creator (or national) can edit draft via wizard
        if ($user->getAccessLevel() === 'national') {
            return;
        }

        if ((int) $campaign->created_by !== (int) $user->id) {
            abort(403, 'Not allowed to edit this campaign.');
        }

        if (! in_array($campaign->status, ['draft', 'rejected'], true)) {
            abort(403, 'Only draft or rejected campaigns can be edited in the wizard.');
        }
    }
}
