<?php

namespace App\Legacy;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MessagingCampaign;
use App\Models\RedCrossUnit;
use App\Models\User;
use App\Services\SendGridService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Import the new model
// Import the new model
// Import Auth facade

class ComposerController extends Controller
{
    protected $sendGridService;
    protected $smsService;

    public function __construct(SendGridService $sendGridService, SmsService $smsService)
    {
        $this->sendGridService = $sendGridService;
        $this->smsService = $smsService;
    }

    /**
     * Displays the form for filtering users for messaging.
     */
    public function filterUsers(Request $request)
    {
        $filters = $request->except(['_token', 'page']);
        $hasFilters = count($filters) > 0;

        $query = User::query();
        $query->with(['branch', 'division', 'redCrossUnit', 'roles']);

        // Get current user's access level and scoped ID
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $userBranchId = null;
        $userDivisionId = null;

        if ($accessLevel === 'branch') {
            $userBranchId = $scopedId;
        } elseif ($accessLevel === 'division') {
            $userDivisionId = $scopedId;
            $userDivision = Division::find($scopedId);
            if ($userDivision) {
                $userBranchId = $userDivision->branch_id;
            }
        }

        // Apply global access level filters to the main query
        switch ($accessLevel) {
            case 'branch':
                if ($userBranchId) {
                    $query->where('branch_id', $userBranchId);
                }
                break;
            case 'division':
                if ($userDivisionId) {
                    $query->where('division_id', $userDivisionId);
                }
                break;
            // 'national' level sees all, so no additional filter here
        }


        // Apply Search Filter
        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('telephone1', 'like', '%' . $search . '%')
                    ->orWhere('telephone2', 'like', '%' . $search . '%')
                    // Removed: national_id_number cannot be searched after NDPA encryption-at-rest.
                    // Search user_id_reference attribute (requires custom logic or raw query)
                    ->orWhereRaw("CONCAT('DB-', users.id, '/', UPPER(COALESCE((SELECT code FROM branches WHERE id = users.branch_id), (SELECT name FROM branches WHERE id = users.branch_id), 'UNK'))) LIKE ?", ['%' . str_replace('DB-', '', $search) . '%']);
            });
        }

        // Apply Branch Filter (only if not restricted by user's access level)
        if ($accessLevel === 'national' && isset($filters['branch_id']) && $filters['branch_id'] !== '') {
            $query->where('branch_id', $filters['branch_id']);
        }

        // Apply Division Filter (only if not restricted by user's access level)
        if (in_array($accessLevel, ['national', 'branch']) && isset($filters['division_id']) && $filters['division_id'] !== '') {
            $query->where('division_id', $filters['division_id']);
        }

        // Apply Red Cross Unit Filter (cascading from division)
        if (isset($filters['red_cross_unit_id']) && $filters['red_cross_unit_id'] !== '') {
            $query->where('red_cross_unit_id', $filters['red_cross_unit_id']);
        }

        // Apply Membership Fee Name Filter
        if (isset($filters['membership_fee_name']) && $filters['membership_fee_name'] !== '') {
            $query->whereHas('membershipPayments', function ($q) use ($filters) {
                $q->personal()->whereHas('membershipFee', function ($feeQuery) use ($filters) {
                    $feeQuery->where('name', $filters['membership_fee_name']);
                });
            });
        }

        // Apply Membership Validity Status Filter
        if (isset($filters['validity_status']) && $filters['validity_status'] !== '') {
            $query->whereHas('activeMembershipPayments', function ($q) use ($filters) { // Use activeMembershipPayments relation
                $q->personal();
                if ($filters['validity_status'] === 'valid') {
                    $q->whereDate('expiry_date', '>', now());
                } elseif ($filters['validity_status'] === 'expiring_soon') {
                    $q->whereDate('expiry_date', '>', now())
                        ->whereDate('expiry_date', '<=', now()->addDays(30));
                } elseif ($filters['validity_status'] === 'expired') {
                    $q->whereDate('expiry_date', '<=', now());
                }
            });
        }

        // Apply 'My Records' Filter
        if (isset($filters['my_records']) && $filters['my_records'] === '1' && auth()->check()) {
            $query->where('created_by', auth()->id());
        }

        // Apply Sorting
        $sortBy = $filters['sort_by'] ?? 'name_asc';
        switch ($sortBy) {
            case 'name_asc':
                $query->orderBy('first_name', 'asc')->orderBy('last_name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('first_name', 'desc')->orderBy('last_name', 'desc');
                break;
            case 'created_at_desc':
                $query->orderBy('created_at', 'desc');
                break;
            case 'created_at_asc':
                $query->orderBy('created_at', 'asc');
                break;
        }

        $totalRecords = $query->count();
        $users = $query->paginate(10)->appends($filters); // Paginate results

        // Get filter options based on access level
        $branches = collect();
        $divisions = collect();
        $redCrossUnits = collect();

        switch ($accessLevel) {
            case 'national':
                $branches = Branch::select('id', 'name')->orderBy('name')->get();
                if ($request->filled('branch_id')) {
                    $divisions = Division::where('branch_id', $request->branch_id)
                        ->select('id', 'name')->orderBy('name')->get();
                }
                break;
            case 'branch':
                if ($userBranchId) {
                    $branches = Branch::where('id', $userBranchId)
                        ->select('id', 'name')->orderBy('name')->get();
                    $divisions = Division::where('branch_id', $userBranchId)
                        ->select('id', 'name')->orderBy('name')->get();
                }
                break;
            case 'division':
                if ($userDivisionId) {
                    $userDivision = Division::find($userDivisionId);
                    if ($userDivision) {
                        $branches = Branch::where('id', $userDivision->branch_id)
                            ->select('id', 'name')->orderBy('name')->get();
                        $divisions = Division::where('id', $userDivisionId)
                            ->select('id', 'name')->orderBy('name')->get();
                    }
                }
                break;
        }

        // Populate Red Cross Units based on division_id or pre-selected red_cross_unit_id, respecting access level
        if ($request->filled('division_id')) {
            if ($accessLevel === 'division' && $userDivisionId && (string)$userDivisionId !== (string)$request->division_id) {
                // If user is division-scoped and tries to filter by another division, ignore
                $redCrossUnits = collect();
            } else {
                $redCrossUnits = RedCrossUnit::where('division_id', $request->division_id)
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get();
            }
        } elseif ($accessLevel === 'division' && $userDivisionId) {
            // If division level user, default to their division's units
            $redCrossUnits = RedCrossUnit::where('division_id', $userDivisionId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        $membershipFees = MembershipFee::all(); // All membership fees are generally available

        return view('messaging.user-filter', [
            'users' => $users,
            'branches' => $branches, // Filtered branches
            'divisions' => $divisions, // Filtered divisions
            'redCrossUnits' => $redCrossUnits, // Filtered redCrossUnits
            'membershipFees' => $membershipFees,
            'hasFilters' => $hasFilters,
            'totalRecords' => $totalRecords,
            'filters' => $filters, // Pass current filters back to the view for hidden inputs
            'accessLevel' => $accessLevel, // Pass access level to the view
            'scopedId' => $scopedId, // Pass scoped ID to the view
            'userBranchId' => $userBranchId, // Pass user's branch ID
            'userDivisionId' => $userDivisionId, // Pass user's division ID
        ]);
    }


    /**
     * Displays the email/SMS composer form.
     * It receives filter parameters from other modules to identify recipients.
     */
    public function compose(Request $request)
    {
        $sourceModule = $request->input('source_module');
        $filters = $request->except(['source_module', '_token']);

        $placeholders = [
            'full_name' => 'Full Name',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email Address',
            'primary_phone' => 'Primary Phone Number',
            'telephone1' => 'Telephone 1',
            'telephone2' => 'Telephone 2',
            'user_id_reference' => 'User ID Reference',
            'organisation' => 'Organization',
            'occupation' => 'Occupation',
            'residential_address' => 'Residential Address',
            'workplace_address' => 'Workplace Address',
            'gender' => 'Gender',
            'birth_year' => 'Birth Year',
            'marital_status' => 'Marital Status',
            'national_id_number' => 'National ID Number',
            'branch_name' => 'Branch Name',
            'division_name' => 'Division Name',
            'red_cross_unit_name' => 'Red Cross Unit Name',
            'role_display_name' => 'Role Display Name',
        ];

        // Fetch recipients based on the filters received from user-filter blade
        $recipients = $this->getFilteredRecipients($sourceModule, $filters);

        $emailAvailableCount = $recipients->filter(fn($user) => !empty($user->email))->count();
        $noEmailCount = $recipients->count() - $emailAvailableCount;

        $phoneAvailableCount = $recipients->filter(fn($user) => !empty($user->telephone1))->count();
        $noPhoneCount = $recipients->count() - $phoneAvailableCount;

        $displayFilters = [];
        $hasFilters = false;

        // Process filters for display in the composer view
        foreach ($filters as $key => $value) {
            if (!empty($value) && !is_array($value)) { // Simple non-array filters
                $displayFilters[$key] = $value;
                $hasFilters = true;
            } elseif (is_array($value) && !empty($value)) { // Handle array filters if any
                $displayFilters[$key] = implode(', ', $value);
                $hasFilters = true;
            }
        }
        // Special handling for some display filters (e.g., converting IDs to names)
        if (isset($filters['branch_id']) && $filters['branch_id'] !== '') {
            $branch = Branch::find($filters['branch_id']);
            if ($branch) $displayFilters['branch_name'] = $branch->name;
        }
        if (isset($filters['division_id']) && $filters['division_id'] !== '') {
            $division = Division::find($filters['division_id']);
            if ($division) $displayFilters['division_name'] = $division->name;
        }
        if (isset($filters['red_cross_unit_id']) && $filters['red_cross_unit_id'] !== '') {
            $redCrossUnit = RedCrossUnit::find($filters['red_cross_unit_id']);
            if ($redCrossUnit) $displayFilters['red_cross_unit_name'] = $redCrossUnit->name;
        }
        if (isset($filters['validity_status']) && $filters['validity_status'] !== '') {
            $statusText = '';
            switch ($filters['validity_status']) {
                case 'valid': $statusText = 'Valid Memberships'; break;
                case 'expiring_soon': $statusText = 'Expiring Within 30 Days'; break;
                case 'expired': $statusText = 'Expired Memberships'; break;
            }
            if ($statusText) $displayFilters['validity_status_display'] = $statusText;
        }
        if (isset($filters['my_records']) && $filters['my_records'] === '1') {
            $displayFilters['my_records_display'] = 'My Records Only';
        }


        return view('messaging.composer', [
            'sourceModule' => $sourceModule,
            'filters' => $filters,
            'recipientCount' => $recipients->count(),
            'recipients' => $recipients,
            'emailAvailableCount' => $emailAvailableCount,
            'noEmailCount' => $noEmailCount,
            'phoneAvailableCount' => $phoneAvailableCount,
            'noPhoneCount' => $recipients->count() - $phoneAvailableCount,
            'placeholders' => $placeholders,
            'displayFilters' => $displayFilters,
            'hasFilters' => $hasFilters,
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'source_module' => 'required|string',
            'message_type' => 'required|in:email,sms',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
        ]);

        $sourceModule = $request->input('source_module');
        $messageType = $request->input('message_type');
        $subject = $request->input('subject');
        $body = $request->input('body');
        $filters = $request->except(['source_module', 'message_type', 'subject', 'body', '_token']);

        $recipients = $this->getFilteredRecipients($sourceModule, $filters);

        if ($recipients->isEmpty()) {
            return view('messaging.bulk-preview', [
                'error' => 'No recipients found for preview with the current filters.',
                'messageType' => $messageType,
            ]);
        }

        $messages = [];
        foreach ($recipients as $recipient) {
            $personalizedBody = $body;
            $personalizedSubject = $subject;

            $replacements = $this->getRecipientPlaceholders($recipient);

            $personalizedBody = str_replace(array_keys($replacements), array_values($replacements), $personalizedBody);
            if ($messageType === 'email') {
                $personalizedSubject = str_replace(array_keys($replacements), array_values($replacements), $personalizedSubject);
            }

            $messages[] = [
                'recipientName' => $recipient->full_name,
                'email' => $recipient->email,
                'phone' => $recipient->telephone1,
                'subject' => $personalizedSubject,
                'body' => $personalizedBody,
                'messageType' => $messageType,
            ];
        }

        return view('messaging.bulk-preview', [
            'messages' => $messages,
            'messageType' => $messageType,
            'totalRecipients' => $recipients->count(),
        ]);
    }

    public function send(Request $request)
    {
        $request->validate([
            'source_module' => 'required|string',
            'message_type' => 'required|in:email,sms',
            'subject' => 'required_if:message_type,email|nullable|string|max:255',
            'body' => 'required|string',
        ]);

        $sourceModule = $request->input('source_module');
        $messageType = $request->input('message_type');
        $subject = $request->input('subject');
        $body = $request->input('body');
        $filters = $request->except(['source_module', 'message_type', 'subject', 'body', '_token']);

        $recipients = $this->getFilteredRecipients($sourceModule, $filters);

        if ($recipients->isEmpty()) {
            return back()->with('error', 'No recipients found matching the applied filters. Message not sent.');
        }

        // 1. Create the Messaging Campaign record
        $campaignTitle = $request->input('campaign_title') ?? ('Bulk Message - ' . now()->format('Y-m-d H:i:s'));

        $campaign = MessagingCampaign::create([
            'channel' => $messageType,
            'audience_type' => 'filtered_users', // You can make this dynamic based on the filters
            'title' => $campaignTitle,
            'subject' => ($messageType === 'email') ? $subject : null,
            'body' => $body,
            'filter_json' => $filters,
            'from_email' => ($messageType === 'email') ? env('MAIL_FROM_ADDRESS') : null,
            'from_name' => ($messageType === 'email') ? env('MAIL_FROM_NAME') : null,
            'from_phone' => ($messageType === 'sms') ? env('SMS_FROM_NUMBER') : null,
            'status' => 'sending', // Initial status
            'created_by' => auth()->id(),
            'stats_total' => $recipients->count(), // Initialize total recipients
        ]);

        $emailsSentCount = 0;
        $emailsFailedCount = 0;
        $smsSentCount = 0;
        $smsFailedCount = 0;

        // 2. Iterate through recipients, create MessagingRecipient records, and attempt to send
        foreach ($recipients as $recipient) {
            $personalizedBody = $body;
            $personalizedSubject = $subject;

            $replacements = $this->getRecipientPlaceholders($recipient);
            // Ensure payload_json stores the raw replacements for tracking
            $payloadJson = json_encode($replacements);

            $personalizedBody = str_replace(array_keys($replacements), array_values($replacements), $personalizedBody);
            if ($messageType === 'email') {
                $personalizedSubject = str_replace(array_keys($replacements), array_values($replacements), $personalizedSubject);
            }

            // Create MessagingRecipient record for this recipient
            $messagingRecipient = $campaign->recipients()->create([
                'recipient_type' => get_class($recipient), // e.g., 'App\Models\User'
                'recipient_id' => $recipient->id,
                'email' => $recipient->email,
                'phone' => $recipient->telephone1,
                'payload_json' => $payloadJson,
                'status' => 'pending', // Will be updated after the send attempt
            ]);

            $sendSuccess = false;
            $errorMessage = null;

            if ($messageType === 'email') {
                if (!empty($recipient->email)) {
                    $sendSuccess = $this->sendGridService->sendEmail(
                        $recipient->email,
                        $personalizedSubject,
                        $personalizedBody
                    );
                    if ($sendSuccess) {
                        $emailsSentCount++;
                        $messagingRecipient->update(['status' => 'sent', 'sent_at' => now()]);
                    } else {
                        $emailsFailedCount++;
                        $errorMessage = 'Email sending failed (SendGridService).';
                        $messagingRecipient->update(['status' => 'failed', 'last_error' => $errorMessage]);
                    }
                } else {
                    $emailsFailedCount++;
                    $errorMessage = 'No email address for recipient.';
                    $messagingRecipient->update(['status' => 'failed', 'last_error' => $errorMessage]);
                }
            } elseif ($messageType === 'sms') {
                if (!empty($recipient->telephone1)) {
                    $sendSuccess = $this->smsService->sendSms(
                        $recipient->telephone1,
                        $personalizedBody
                    );
                    if ($sendSuccess) {
                        $smsSentCount++;
                        $messagingRecipient->update(['status' => 'sent', 'sent_at' => now()]);
                    } else {
                        $smsFailedCount++;
                        $errorMessage = 'SMS sending failed (SmsService).';
                        $messagingRecipient->update(['status' => 'failed', 'last_error' => $errorMessage]);
                    }
                } else {
                    $smsFailedCount++;
                    $errorMessage = 'No phone number for recipient.';
                    $messagingRecipient->update(['status' => 'failed', 'last_error' => $errorMessage]);
                }
            }
        }

        // 3. Update the Messaging Campaign stats and final status
        $campaign->stats_sent = ($messageType === 'email') ? $emailsSentCount : $smsSentCount;
        $campaign->stats_failed = ($messageType === 'email') ? $emailsFailedCount : $smsFailedCount;

        if ($campaign->stats_sent > 0 && $campaign->stats_failed > 0) {
            $campaign->status = 'partially_sent';
        } elseif ($campaign->stats_sent == 0 && $campaign->stats_failed > 0) {
            $campaign->status = 'failed';
        } else {
            $campaign->status = 'sent';
        }
        $campaign->save();

        $totalSent = ($messageType === 'email') ? $emailsSentCount : $smsSentCount;
        $totalFailed = ($messageType === 'email') ? $emailsFailedCount : $smsFailedCount;

        if ($totalSent > 0) {
            $message = ucfirst($messageType) . 's dispatched successfully to ' . $totalSent . ' recipients.';
            if ($totalFailed > 0) {
                $message .= ' (' . $totalFailed . ' failed).';
            }
            return redirect()->back()->with('success', $message);
        } else {
            return back()->with('error', 'Failed to dispatch ' . $messageType . 's to any recipient. Total failed: ' . $totalFailed . '. Check logs for details.');
        }
    }

    /**
     * Fetches User models based on the source module and provided filters.
     *
     * @param string $sourceModule The module from which the composer was accessed (e.g., 'membership-payments', 'user-filter').
     * @param array $filters An associative array of filters applied.
     * @return \Illuminate\Support\Collection
     */
    protected function getFilteredRecipients(string $sourceModule, array $filters)
    {
        $query = User::query();
        $query->with(['branch', 'division', 'redCrossUnit', 'roles']);

        // Get current user's access level and scoped ID for recipient filtering
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $userBranchId = null;
        $userDivisionId = null;

        if ($accessLevel === 'branch') {
            $userBranchId = $scopedId;
        } elseif ($accessLevel === 'division') {
            $userDivisionId = $scopedId;
            $userDivision = Division::find($scopedId);
            if ($userDivision) {
                $userBranchId = $userDivision->branch_id;
            }
        }

        // Apply global access level filters to the main query
        switch ($accessLevel) {
            case 'branch':
                if ($userBranchId) {
                    $query->where('branch_id', $userBranchId);
                }
                break;
            case 'division':
                if ($userDivisionId) {
                    $query->where('division_id', $userDivisionId);
                }
                break;
            // 'national' level sees all, so no additional filter here
        }

        // --- Apply common filters ---
        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('telephone1', 'like', '%' . $search . '%')
                    ->orWhere('telephone2', 'like', '%' . $search . '%')
                    // Removed: national_id_number cannot be searched after NDPA encryption-at-rest.
                    ->orWhereRaw("CONCAT('DB-', users.id, '/', UPPER(COALESCE((SELECT code FROM branches WHERE id = users.branch_id), (SELECT name FROM branches WHERE id = users.branch_id), 'UNK'))) LIKE ?", ['%' . str_replace('DB-', '', $search) . '%']);
            });
        }
        // Branch filter, respecting access level
        if ($accessLevel === 'national' && isset($filters['branch_id']) && $filters['branch_id'] !== '') {
            $query->where('branch_id', $filters['branch_id']);
        }

        // Division filter, respecting access level
        if (in_array($accessLevel, ['national', 'branch']) && isset($filters['division_id']) && $filters['division_id'] !== '') {
            $query->where('division_id', $filters['division_id']);
        }

        if (isset($filters['red_cross_unit_id']) && $filters['red_cross_unit_id'] !== '') {
            $query->where('red_cross_unit_id', $filters['red_cross_unit_id']);
        }
        if (isset($filters['my_records']) && $filters['my_records'] === '1' && auth()->check()) {
            $query->where('created_by', auth()->id());
        }

        // --- Apply module-specific filters ---
        switch ($sourceModule) {
            case 'user-filter':
                // Membership type and validity filters specific to user-filter
                if (isset($filters['membership_fee_name']) && $filters['membership_fee_name'] !== '') {
                    $query->whereHas('activeMembershipPayments', function ($q) use ($filters) {
                        $q->personal()->whereHas('membershipFee', function ($feeQuery) use ($filters) {
                            $feeQuery->where('name', $filters['membership_fee_name']);
                        });
                    });
                }
                if (isset($filters['validity_status']) && $filters['validity_status'] !== '') {
                    $query->whereHas('activeMembershipPayments', function ($q) use ($filters) {
                        $q->personal();
                        if ($filters['validity_status'] === 'valid') {
                            $q->whereDate('expiry_date', '>', now());
                        } elseif ($filters['validity_status'] === 'expiring_soon') {
                            $q->whereDate('expiry_date', '>', now())
                                ->whereDate('expiry_date', '<=', now()->addDays(30));
                        } elseif ($filters['validity_status'] === 'expired') {
                            $q->whereDate('expiry_date', '<=', now());
                        }
                    });
                }
                break;

            case 'membership-payments':
                $query->whereHas('membershipPayments', function ($q) use ($filters) {
                    $q->personal();
                    if (isset($filters['search']) && $filters['search'] !== '') {
                        $q->where(function ($subQuery) use ($filters) {
                            $subQuery->where('reference', 'like', '%' . $filters['search'] . '%')
                                ->orWhereHas('user', function ($userQuery) use ($filters) {
                                    $userQuery->where('first_name', 'like', '%' . $filters['search'] . '%')
                                        ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                                        ->orWhere('id', 'like', '%' . str_replace('DB-', '', $filters['search']) . '%');
                                });
                        });
                    }
                    if (isset($filters['membership_fee_name']) && $filters['membership_fee_name'] !== '') {
                        $q->whereHas('membershipFee', function ($feeQuery) use ($filters) {
                            $feeQuery->where('name', $filters['membership_fee_name']);
                        });
                    }
                    if (isset($filters['validity_status']) && $filters['validity_status'] !== '') {
                        if ($filters['validity_status'] === 'valid') {
                            $q->whereDate('expiry_date', '>', now());
                        } elseif ($filters['validity_status'] === 'expiring_soon') {
                            $q->whereDate('expiry_date', '>', now())
                                ->whereDate('expiry_date', '<=', now()->addDays(30));
                        } elseif ($filters['validity_status'] === 'expired') {
                            $q->whereDate('expiry_date', '<=', now());
                        }
                    }
                });
                break;

            case 'trainings':
                $query->whereHas('trainings', function ($q) use ($filters) {
                    if (isset($filters['search']) && $filters['search'] !== '') {
                        $q->where(function ($subQuery) use ($filters) {
                            $subQuery->where('reference', 'like', '%' . $filters['search'] . '%')
                                ->orWhereHas('user', function ($userQuery) use ($filters) {
                                    $userQuery->where('first_name', 'like', '%' . $filters['search'] . '%')
                                        ->orWhere('last_name', 'like', '%' . $filters['search'] . '%');
                                })
                                ->orWhereHas('trainingType', function ($typeQuery) use ($filters) {
                                    $typeQuery->where('name', 'like', '%' . $filters['search'] . '%');
                                });
                        });
                    }
                    if (isset($filters['training_type_id']) && $filters['training_type_id'] !== '') {
                        $q->where('training_type_id', $filters['training_type_id']);
                    }
                    if (isset($filters['status']) && $filters['status'] !== '') {
                        // This part might need adjustment depending on how 'expiry_date' is calculated for trainings
                        // and if it's directly available on the 'trainings' table or through a relation.
                        // Assuming 'expiry_date' is a direct column on 'trainings' or a derivable field.
                        if ($filters['status'] === 'valid') {
                            $q->whereDate('expiry_date', '>', now());
                        } elseif ($filters['status'] === 'expiring_2_weeks') {
                            $q->whereDate('expiry_date', '>', now())
                                ->whereDate('expiry_date', '<=', now()->addWeeks(2));
                        }
                        // Note: The original ComposerController had a 'status' filter for trainings that used 'expiry_date'.
                        // Ensure your Training model or related logic correctly handles 'expiry_date' if not direct.
                    }
                });
                break;

            case 'donations':
                $query->whereHas('donations', function ($q) use ($filters) {
                    if (isset($filters['search']) && $filters['search'] !== '') {
                        $q->where(function ($subQuery) use ($filters) {
                            $subQuery->where('donor_full_name', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('reference', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('purpose', 'like', '%' . $filters['search'] . '%');
                        });
                    }
                });
                break;

            case 'activities':
                $query->whereHas('activities', function ($q) use ($filters) {
                    if (isset($filters['search']) && $filters['search'] !== '') {
                        $q->where(function ($subQuery) use ($filters) {
                            $subQuery->where('reference', 'like', '%' . $filters['search'] . '%')
                                ->orWhereHas('user', function ($userQuery) use ($filters) {
                                    $userQuery->where('first_name', 'like', '%' . $filters['search'] . '%')
                                        ->orWhere('last_name', 'like', '%' . $filters['search'] . '%');
                                })
                                ->orWhereHas('activityType', function ($typeQuery) use ($filters) {
                                    $typeQuery->where('name', 'like', '%' . $filters['search'] . '%');
                                });
                        });
                    }
                    if (isset($filters['activity_type_id']) && $filters['activity_type_id'] !== '') {
                        $q->where('activity_type_id', $filters['activity_type_id']);
                    }
                });
                break;

            default:
                // If no specific module is provided or recognized, return an empty collection
                return collect();
        }

        // Return a collection without pagination for the composer
        return $query->distinct()->get();
    }

    /**
     * Helper to get recipient placeholders.
     */
    protected function getRecipientPlaceholders(User $recipient): array
    {
        return [
            '@{{full_name}}' => $recipient->full_name,
            '@{{first_name}}' => $recipient->first_name,
            '@{{last_name}}' => $recipient->last_name,
            '@{{email}}' => $recipient->email,
            '@{{primary_phone}}' => $recipient->primary_phone,
            '@{{telephone1}}' => $recipient->telephone1,
            '@{{telephone2}}' => $recipient->telephone2,
            '@{{user_id_reference}}' => $recipient->user_id_reference,
            '@{{organisation}}' => $recipient->organisation,
            '@{{occupation}}' => $recipient->occupation,
            '@{{residential_address}}' => $recipient->residential_address,
            '@{{workplace_address}}' => $recipient->workplace_address,
            '@{{gender}}' => $recipient->gender,
            '@{{birth_year}}' => $recipient->birth_year,
            '@{{marital_status}}' => $recipient->marital_status,
            '@{{national_id_number}}' => $recipient->national_id_number,
            '@{{branch_name}}' => $recipient->branch->name ?? '',
            '@{{division_name}}' => $recipient->division->name ?? '',
            '@{{red_cross_unit_name}}' => $recipient->redCrossUnit->name ?? '',
            '@{{role_display_name}}' => $recipient->role_display_name,
        ];
    }
}
