<?php

namespace App\Http\Controllers;

use App\Models\MessagingCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessagingCampaignController extends Controller
{
    public function create(Request $request)
    {
        $user = Auth::user();

        return view('campaigns.create', [
            'prefillLifecycle' => $request->query('lifecycle'),
            'scopeLevel' => $user->getAccessLevel(),
            'scopeId' => $user->getScopedId(),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        // Scope must come from the creator, never from form input.
        $scopeLevel = $user->getAccessLevel();
        $scopeId = $user->getScopedId();

        $data = $request->validate([
            'channel' => ['required', 'string', 'in:email,sms,both'],
            'audience_type' => ['required', 'string', 'max:40'],
            'title' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],

            // Your filter builder should post an array. Laravel will cast to JSON.
            'filter_json' => ['required', 'array'],

            'from_email' => ['nullable', 'string', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_phone' => ['nullable', 'string', 'max:255'],

            'lifecycle' => ['nullable', 'in:awaiting_assignment,active,dormant,archived'],

            // If checked, we jump to proposed directly
            'submit_now' => ['nullable', 'boolean'],
        ]);

        // Optional: basic validation rule — subject is only relevant for email/both
        if (in_array($data['channel'], ['email', 'both'], true) && empty($data['subject'])) {
            // Keep subject optional if you prefer. If you want it required, uncomment:
            // return back()->withErrors(['subject' => 'Subject is required for email campaigns.'])->withInput();
        }

        $status = !empty($data['submit_now']) ? 'proposed' : 'draft';

        $campaign = MessagingCampaign::create([
            'channel' => $data['channel'],
            'audience_type' => $data['audience_type'],
            'title' => $data['title'] ?? null,
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'filter_json' => $data['filter_json'],

            'from_email' => $data['from_email'] ?? null,
            'from_name' => $data['from_name'] ?? null,
            'from_phone' => $data['from_phone'] ?? null,

            'scope_level' => $scopeLevel,
            'scope_id' => $scopeId,
            'lifecycle' => $data['lifecycle'] ?? null,

            'origin_level' => $user->getAccessLevel() === 'national' ? 'national' : 'branch',
            'origin_branch_id' => $user->getScopedBranchId(),

            'status' => $status,

            'created_by' => $user->id,
        ]);

        return redirect()
            ->route('campaigns.create', ['lifecycle' => $campaign->lifecycle])
            ->with('success', $status === 'proposed'
                ? 'Campaign request submitted for approval.'
                : 'Campaign saved as draft.'
            );
    }

    public function approve(Request $request, MessagingCampaign $campaign)
    {
        $user = Auth::user();

        $this->authorizeScope($campaign, $user);

        if ($campaign->status !== 'proposed') {
            return back()->with('error', 'Only proposed campaign requests can be approved.');
        }

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $campaign->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejected_at' => null,
            'review_note' => $data['review_note'] ?? null,
        ]);

        return back()->with('success', 'Campaign request approved.');
    }

    public function reject(Request $request, MessagingCampaign $campaign)
    {
        $user = Auth::user();

        $this->authorizeScope($campaign, $user);

        if ($campaign->status !== 'proposed') {
            return back()->with('error', 'Only proposed campaign requests can be rejected.');
        }

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $campaign->update([
            'status' => 'rejected',
            'approved_by' => $user->id, // reviewer (kept in same field)
            'rejected_at' => now(),
            'approved_at' => null,
            'review_note' => $data['review_note'] ?? null,
        ]);

        return back()->with('success', 'Campaign request rejected.');
    }

    public function send(Request $request, MessagingCampaign $campaign)
    {
        $user = Auth::user();

        $this->authorizeScope($campaign, $user);

        // Only approved campaigns should be allowed to queue/send
        if ($campaign->status !== 'approved') {
            return back()->with('error', 'Only approved campaigns can be sent.');
        }

        // Queue it (your sending job/command can pick up queued rows)
        $campaign->update([
            'status' => 'queued',
        ]);

        return back()->with('success', 'Campaign queued for sending.');
    }

    /**
     * Scope authorization using getAccessLevel/getScopedId.
     * Tune this logic to your exact hierarchy.
     */
    protected function authorizeScope(MessagingCampaign $campaign, $user): void
    {
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        // National can access all.
        if ($accessLevel === 'national') {
            return;
        }

        // Must match level
        if ($campaign->scope_level !== $accessLevel) {
            abort(403, 'Scope mismatch.');
        }

        // Must match id (string compare to avoid int/null weirdness)
        if ((string)($campaign->scope_id ?? '') !== (string)($scopedId ?? '')) {
            abort(403, 'Out of scope.');
        }
    }
}
