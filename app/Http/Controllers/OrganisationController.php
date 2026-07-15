<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrganisationController extends Controller
{
    public function index(Request $request)
    {
        $authUser    = Auth::user();
        $accessLevel = $authUser->getAccessLevel();
        $scopedId    = $authUser->getScopedId();

        $status     = $request->input('status', 'active');
        $membership = $request->input('membership', 'all');
        $sortBy     = $request->input('sort_by', 'name_asc');

        $query = $status === 'archived'
            ? Organisation::onlyTrashed()->with('branch')->withCount('users')
            : Organisation::with(['branch', 'activeMembership.membershipFee', 'latestMembership'])->withCount(['users', 'donations']);

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
                if ($scopedId) {
                    $query->where('branch_id', $scopedId);
                }
                $branches = Branch::where('id', $scopedId)->orderBy('name')->get();
                break;

            default:
                if ($request->filled('branch_id')) {
                    $query->where('branch_id', $request->branch_id);
                }
                $branches = Branch::orderBy('name')->get();
                break;
        }

        if ($status === 'active') {
            switch ($membership) {
                case 'members':
                    $query->whereHas('membershipPayments', function ($q) {
                        $q->where('is_deleted', false)->where('expiry_date', '>=', now());
                    });
                    break;
                case 'expiring_14':
                    $query->whereHas('membershipPayments', function ($q) {
                        $q->where('is_deleted', false)
                          ->where('expiry_date', '>=', now())
                          ->where('expiry_date', '<=', now()->addDays(14));
                    });
                    break;
                case 'expiring_28':
                    $query->whereHas('membershipPayments', function ($q) {
                        $q->where('is_deleted', false)
                          ->where('expiry_date', '>=', now())
                          ->where('expiry_date', '<=', now()->addDays(28));
                    });
                    break;
                case 'non_members':
                    $query->whereDoesntHave('membershipPayments', function ($q) {
                        $q->where('is_deleted', false)->where('expiry_date', '>=', now());
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

        $hasFilters = $request->anyFilled(['search', 'branch_id'])
            || ($status === 'active' && $membership !== 'all')
            || $sortBy !== 'name_asc';

        return view('organisations.index', compact('organisations', 'branches', 'accessLevel', 'status', 'membership', 'sortBy', 'hasFilters'));
    }

    public function create()
    {
        $authUser    = Auth::user();
        $accessLevel = $authUser->getAccessLevel();
        $scopedId    = $authUser->getScopedId();

        switch ($accessLevel) {
            case 'branch':
                $branches = Branch::where('id', $scopedId)->get();
                break;

            default:
                $branches = Branch::orderBy('name')->get();
                break;
        }

        return view('organisations.create', compact('branches', 'accessLevel'));
    }

    public function store(Request $request)
    {
        $authUser    = Auth::user();
        $accessLevel = $authUser->getAccessLevel();
        $scopedId    = $authUser->getScopedId();

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'short_name'          => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:100',
            'address'             => 'nullable|string|max:500',
            'description'         => 'nullable|string|max:1000',
            'email'               => 'nullable|email|max:255',
            'phone'               => 'nullable|string|max:50',
            'branch_id'           => 'nullable|exists:branches,id',
        ]);

        if ($accessLevel === 'branch') {
            $validated['branch_id'] = $scopedId;
        }

        Organisation::create($validated);

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()->route('organisations.index')
            ->with('success', 'Organisation created successfully.');
    }

    public function show(Organisation $organisation)
    {
        $organisation->load(['branch', 'users']);

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
        if ($organisation->trashed()) {
            return redirect()->route('organisations.index', ['status' => 'archived'])
                ->with('error', 'Archived organisations cannot be edited.');
        }

        $branches = Branch::orderBy('name')->get();
        return view('organisations.edit', compact('organisation', 'branches'));
    }

    public function update(Request $request, Organisation $organisation)
    {
        if ($organisation->trashed()) {
            return redirect()->route('organisations.index', ['status' => 'archived'])
                ->with('error', 'Archived organisations cannot be updated.');
        }

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'short_name'          => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:100',
            'address'             => 'nullable|string|max:500',
            'description'         => 'nullable|string|max:1000',
            'email'               => 'nullable|email|max:255',
            'phone'               => 'nullable|string|max:50',
        ]);

        $organisation->update($validated);

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()->route('organisations.show', $organisation)
            ->with('success', 'Organisation updated successfully.');
    }

    public function archive(Organisation $organisation)
    {
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
        $request->validate(['user_id' => 'required|exists:users,id']);

        if ($organisation->users()->where('user_id', $request->user_id)->exists()) {
            return back()->with('error', 'This user is already linked to this organisation.');
        }

        $organisation->users()->attach($request->user_id, [
            'linked_at' => now(),
            'linked_by' => auth()->id(),
            'is_primary_contact' => false,
        ]);

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return back()->with('success', 'User linked to organisation successfully.');
    }

    public function unlinkUser(Request $request, Organisation $organisation, User $user)
    {
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
}
