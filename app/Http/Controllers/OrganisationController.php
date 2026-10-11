<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Division;
use App\Models\Log as AuditLog;
use App\Models\Organisation;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrganisationController extends Controller
{
    public function index(Request $request)
    {
        $authUser    = Auth::user();
        $accessLevel = $authUser->getAccessLevel();
        $scopedId    = $authUser->getScopedId();

        $this->abortUnlessOrganisationStaff($accessLevel);

        $status     = $request->input('status', 'active');
        $membership = $request->input('membership', 'all');
        $sortBy     = $request->input('sort_by', 'name_asc');

        // --------------------------------------------------
        // View mode
        // --------------------------------------------------
        $viewMode = $request->input('view_mode', 'standard');

        $with = ['branch', 'division', 'activeMembership.membershipFee', 'latestMembership'];
        if ($viewMode === 'donations') {
            $with[] = 'donations';
        } elseif ($viewMode === 'certificates') {
            $with[] = 'certificatePrints';
        }

        $validViewModes = ['standard', 'donations', 'campaigns', 'certificates'];
        if (! in_array($viewMode, $validViewModes)) {
            $viewMode = 'standard';
        }

        $query = $status === 'archived'
            ? Organisation::onlyTrashed()->with(['branch', 'division'])->withCount('users')
            : Organisation::with($with)->withCount(['users', 'donations']);
        $query->inScopeOf($authUser);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('short_name', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('id', is_numeric($search) ? (int)$search : -1);
            });
        }

        switch ($accessLevel) {
            case 'branch':
                $branches = Branch::where('id', $scopedId)->orderBy('name')->get();
                break;

            default:
                if ($request->filled('branch_id')) {
                    $query->where('branch_id', $request->branch_id);
                }
                $branches = Branch::orderBy('name')->get();
                break;
        }

        // Division filter: the divisions of the branch shown (branch staff's
        // own, or the one chosen); a division outside it is ignored.
        $filterBranchId = $accessLevel === 'branch' ? $scopedId : $request->input('branch_id');
        $divisions = filled($filterBranchId)
            ? Division::where('branch_id', $filterBranchId)->orderBy('name')->get(['id', 'name'])
            : collect();
        if ($request->filled('division_id') && $divisions->contains('id', (int) $request->division_id)) {
            $query->where('division_id', $request->division_id);
        }

        if ($status === 'active') {
            switch ($membership) {
                case 'members':
                    $query->whereHas('membershipPayments', function ($q) {
                        $q->where('is_deleted', false)->where('expiry_date', '>=', now()->toDateString());
                    });
                    break;
                case 'expiring_14':
                    $query->whereHas('membershipPayments', function ($q) {
                        $q->where('is_deleted', false)
                          ->where('expiry_date', '>=', now()->toDateString())
                          ->where('expiry_date', '<=', now()->addDays(14)->toDateString());
                    });
                    break;
                case 'expiring_28':
                    $query->whereHas('membershipPayments', function ($q) {
                        $q->where('is_deleted', false)
                          ->where('expiry_date', '>=', now()->toDateString())
                          ->where('expiry_date', '<=', now()->addDays(28)->toDateString());
                    });
                    break;
                case 'non_members':
                    $query->whereDoesntHave('membershipPayments', function ($q) {
                        $q->where('is_deleted', false)->where('expiry_date', '>=', now()->toDateString());
                    });
                    break;
            }
        }

        switch ($sortBy) {
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'created_at_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'created_at_desc':
                $query->orderBy('created_at', 'desc');
                break;
            default: // name_asc
                $query->orderBy('name', 'asc');
                break;
        }

        $organisations = $query->paginate(20)->withQueryString();

        if ($viewMode === 'campaigns') {
            $organisations->each(function ($organisation) {
                $organisation->setRelation('campaignRecipients',
                    $organisation->campaignRecipients()
                        ->with('campaign')
                        ->latest()
                        ->limit(8)
                        ->get());
            });
        }

        $hasFilters = $request->anyFilled(['search', 'branch_id', 'division_id'])
            || ($status === 'active' && $membership !== 'all')
            || $sortBy !== 'name_asc';

        return view('organisations.index', compact('organisations', 'branches', 'divisions', 'accessLevel', 'status', 'membership', 'sortBy', 'hasFilters', 'viewMode'));
    }

    public function create()
    {
        $authUser    = Auth::user();
        $accessLevel = $authUser->getAccessLevel();
        $scopedId    = $authUser->getScopedId();

        $this->abortUnlessOrganisationStaff($accessLevel);

        switch ($accessLevel) {
            case 'branch':
                $branches = Branch::where('id', $scopedId)->get();
                break;

            default:
                $branches = Branch::orderBy('name')->get();
                break;
        }

        $divisionsByBranch = $this->divisionsByBranch($branches);

        return view('organisations.create', compact('branches', 'accessLevel', 'divisionsByBranch'));
    }

    public function store(Request $request)
    {
        $authUser    = Auth::user();
        $accessLevel = $authUser->getAccessLevel();
        $scopedId    = $authUser->getScopedId();

        $this->abortUnlessOrganisationStaff($accessLevel);

        // Branch staff always create in their own branch.
        if ($accessLevel === 'branch') {
            $request->merge(['branch_id' => $scopedId]);
        }

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'short_name'          => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:100',
            'address'             => 'nullable|string|max:500',
            'description'         => 'nullable|string|max:1000',
            'email'               => 'nullable|email|max:255',
            'phone'               => 'nullable|string|max:50',
            ...$this->locationRules($request),
        ]);

        Organisation::create($validated);

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()->route('organisations.index')
            ->with('success', 'Organisation created successfully.');
    }

    public function show(Organisation $organisation)
    {
        $this->authorizeOrganisation($organisation);
        $organisation->load(['branch', 'division', 'users']);

        $prints = $organisation->certificatePrints()
            ->with('printedBy')
            ->orderByDesc('printed_at')
            ->get();

        $certificatePrintsLimitMessage = $prints->count() >= 6;

        $typeLabels = [
            'organisation_membership' => 'Organisation – Membership',
            'organisation_donation'   => 'Organisation – Donation',
        ];

        $certificatePrints = $prints->map(fn ($print) => [
            'printed_at'       => $print->printed_at->format('M d, Y H:i'),
            'certificate_type' => $typeLabels[$print->certificate_type] ?? ucwords(str_replace('_', ' ', $print->certificate_type)),
            'printed_by'       => optional($print->printedBy)->full_name ?? 'System',
        ]);

        $allCampaignRecipients = $organisation->campaignRecipients()
            ->with('campaign')
            ->orderByDesc('sent_at')
            ->get();

        $campaignRecipientsLimitMessage = $allCampaignRecipients->count() >= 6;

        $campaignRecipients = $allCampaignRecipients->map(fn ($r) => [
            'sent_at'        => $r->sent_at?->format('M d, Y H:i') ?? null,
            'campaign_title' => $r->campaign?->title ?? '—',
            'channel'        => $r->campaign?->channel ?? '—',
            'status'         => $r->status ?? '—',
            'email'          => $r->email,
            'phone'          => $r->phone,
        ]);

        return view('organisations.show', compact(
            'organisation',
            'certificatePrints',
            'certificatePrintsLimitMessage',
            'campaignRecipients',
            'campaignRecipientsLimitMessage'
        ));
    }

    public function edit(Organisation $organisation)
    {
        $this->authorizeOrganisation($organisation);
        if ($organisation->trashed()) {
            return redirect()->route('organisations.index', ['status' => 'archived'])
                ->with('error', 'Archived organisations cannot be edited.');
        }

        $accessLevel = Auth::user()->getAccessLevel();

        // Only national staff can move an organisation to another branch;
        // branch staff choose a division within its branch.
        $branches = $accessLevel === 'national'
            ? Branch::orderBy('name')->get()
            : Branch::whereKey($organisation->branch_id)->get();
        $divisionsByBranch = $this->divisionsByBranch($branches);

        return view('organisations.edit', compact('organisation', 'branches', 'accessLevel', 'divisionsByBranch'));
    }

    public function update(Request $request, Organisation $organisation)
    {
        $this->authorizeOrganisation($organisation);
        if ($organisation->trashed()) {
            return redirect()->route('organisations.index', ['status' => 'archived'])
                ->with('error', 'Archived organisations cannot be updated.');
        }

        $accessLevel = Auth::user()->getAccessLevel();

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'short_name'          => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:100',
            'address'             => 'nullable|string|max:500',
            'description'         => 'nullable|string|max:1000',
            'email'               => 'nullable|email|max:255',
            'phone'               => 'nullable|string|max:50',
            ...$this->locationRules($request),
        ]);

        // Moving rules, as for persons (UserController::update): national
        // staff anywhere; branch staff only between divisions of their own
        // branch; anyone else not at all.
        $branchChanged   = (int) $validated['branch_id'] !== (int) $organisation->branch_id;
        $divisionChanged = (int) $validated['division_id'] !== (int) $organisation->division_id;

        if ($branchChanged && $accessLevel !== 'national') {
            return back()->withInput()->withErrors([
                'branch_id' => 'Only national staff can move an organisation to a different branch.',
            ]);
        }
        if ($divisionChanged && ! in_array($accessLevel, ['national', 'branch'], true)) {
            return back()->withInput()->withErrors([
                'division_id' => 'You cannot move organisations.',
            ]);
        }

        $old = ['branch_id' => $organisation->branch_id, 'division_id' => $organisation->division_id];
        $oldNames = [$organisation->branch?->name, $organisation->division?->name];

        $organisation->update($validated);

        if ($branchChanged || $divisionChanged) {
            $organisation->load('branch', 'division');
            $new = ['branch_id' => $organisation->branch_id, 'division_id' => $organisation->division_id];

            $parts = [];
            if ($branchChanged) {
                $parts[] = sprintf('Branch: %s → %s', $oldNames[0] ?: 'N/A', $organisation->branch?->name ?: 'N/A');
            }
            $parts[] = sprintf('Division: %s → %s', $oldNames[1] ?: 'N/A', $organisation->division?->name ?: 'N/A');

            AuditLog::write(
                'organisation_branch_division_changed',
                $organisation,
                $new,
                $old,
                $new,
                'Organisation location updated ('.implode(', ', $parts).'). Earlier payments keep their recorded branch and division.'
            );
        }

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()->route('organisations.show', $organisation)
            ->with('success', 'Organisation updated successfully.');
    }

    public function archive(Organisation $organisation)
    {
        $this->authorizeOrganisation($organisation);
        $organisation->update([
            'deactivated_date'  => now()->toDateString(),
            'deactivated_by_id' => Auth::id(),
        ]);

        $organisation->delete();

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()->route('organisations.index')
            ->with('success', "{$organisation->name} has been archived.");
    }

    public function restore(int $id)
    {
        $organisation = Organisation::withTrashed()->findOrFail($id);
        $this->authorizeOrganisation($organisation);

        $organisation->restore();

        $organisation->update([
            'deactivated_date'  => null,
            'deactivated_by_id' => null,
        ]);

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()->route('organisations.index')
            ->with('success', "{$organisation->name} has been restored.");
    }

    public function linkUser(Request $request, Organisation $organisation)
    {
        $this->authorizeOrganisation($organisation);
        $request->validate(['user_id' => 'required|exists:users,id']);

        if ($organisation->users()->where('user_id', $request->user_id)->exists()) {
            return back()->with('error', 'This user is already linked to this organisation.');
        }

        $organisation->users()->attach($request->user_id, [
            'linked_at' => now(),
            'linked_by' => auth()->id(),
            'is_primary_contact' => false,
        ]);

        // Mirrors RC-unit assignment: organisation linkage promotes a
        // pending_engagement user to active, same as being assigned to a unit.
        $user = User::find($request->user_id);
        if ($user->lifecycle_status === 'pending_engagement') {
            $user->markActive();
        }

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return back()->with('success', 'User linked to organisation successfully.');
    }

    public function unlinkUser(Request $request, Organisation $organisation, User $user)
    {
        $this->authorizeOrganisation($organisation);
        if (!$organisation->users()->where('user_id', $user->id)->exists()) {
            return back()->with('error', 'User is not linked to this organisation.');
        }

        $organisation->users()->detach($user->id);

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return back()->with('success', 'User unlinked from organisation.');
    }

    public function setPrimaryContact(Request $request, Organisation $organisation, User $user)
    {
        $this->authorizeOrganisation($organisation);
        if (!$organisation->users()->where('user_id', $user->id)->exists()) {
            return back()->with('error', 'User is not linked to this organisation.');
        }

        DB::table('organisation_user')
            ->where('organisation_id', $organisation->id)
            ->update(['is_primary_contact' => false]);

        $organisation->users()->updateExistingPivot($user->id, ['is_primary_contact' => true]);

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return back()->with('success', 'Primary contact updated successfully.');
    }

    /**
     * Organisations are managed by national and branch staff only; division
     * staff and anyone without a scope get a 403 even if a role was given
     * an organisation permission. The routes are also gated (can:*_organisation).
     */
    private function abortUnlessOrganisationStaff(string $accessLevel): void
    {
        abort_unless(in_array($accessLevel, ['national', 'branch'], true), 403, 'Organisations are managed by national and branch staff.');
    }

    /** 403 unless the organisation is in the signed-in user's scope (Organisation::isInScopeOf()). */
    private function authorizeOrganisation(Organisation $organisation): void
    {
        abort_unless($organisation->isInScopeOf(Auth::user()), 403, 'This organisation is not in your branch.');
    }

    /**
     * Branch and division are both required, and the division must be in
     * the branch. The column stays nullable (a deleted division sets it to
     * null), so the requirement lives here.
     */
    private function locationRules(Request $request): array
    {
        return [
            'branch_id'   => 'required|exists:branches,id',
            'division_id' => [
                'required',
                'exists:divisions,id',
                function (string $attribute, mixed $value, Closure $fail) use ($request) {
                    $branchId = Division::whereKey($value)->value('branch_id');
                    if ($branchId !== null && (int) $branchId !== (int) $request->input('branch_id')) {
                        $fail('The selected division is not in the selected branch.');
                    }
                },
            ],
        ];
    }

    /**
     * The divisions of $branches, keyed by branch id, for the forms'
     * division list (filtered in the page when the branch changes).
     *
     * @return array<int, list<array{id: int, name: string}>>
     */
    private function divisionsByBranch(Collection $branches): array
    {
        return Division::whereIn('branch_id', $branches->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id'])
            ->groupBy('branch_id')
            ->map(fn ($divisions) => $divisions->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values()->all())
            ->all();
    }
}
