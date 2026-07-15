<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MembershipPayment;
use App\Models\Donation;
use App\Models\Training;
use App\Models\Activity;
use Illuminate\Support\Facades\DB;

class PendingApprovalsReportController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        if ($accessLevel !== 'national' && $accessLevel !== 'branch') {
            abort(403);
        }

        $branches = $accessLevel === 'branch'
            ? Branch::active()->where('id', $scopedId)->get(['id', 'name'])
            : Branch::active()->orderBy('name')->get(['id', 'name']);

        // One grouped query per module — count pending by branch_id
        $payments   = MembershipPayment::pendingApproval()
            ->when($accessLevel === 'branch', fn ($q) => $q->where('branch_id', $scopedId))
            ->select('branch_id', DB::raw('COUNT(*) as cnt'), DB::raw('MIN(created_at) as oldest'))
            ->groupBy('branch_id')->get()->keyBy('branch_id');

        $donations  = Donation::pendingApproval()
            ->when($accessLevel === 'branch', fn ($q) => $q->where('branch_id', $scopedId))
            ->select('branch_id', DB::raw('COUNT(*) as cnt'), DB::raw('MIN(created_at) as oldest'))
            ->groupBy('branch_id')->get()->keyBy('branch_id');

        $trainings  = Training::pendingApproval()
            ->when($accessLevel === 'branch', fn ($q) => $q->where('branch_id', $scopedId))
            ->select('branch_id', DB::raw('COUNT(*) as cnt'), DB::raw('MIN(created_at) as oldest'))
            ->groupBy('branch_id')->get()->keyBy('branch_id');

        $activities = Activity::pendingApproval()
            ->when($accessLevel === 'branch', fn ($q) => $q->where('branch_id', $scopedId))
            ->select('branch_id', DB::raw('COUNT(*) as cnt'), DB::raw('MIN(created_at) as oldest'))
            ->groupBy('branch_id')->get()->keyBy('branch_id');

        // Build one row per branch
        $rows = $branches->map(function ($branch) use (
            $payments, $donations, $trainings, $activities
        ) {
            $p = (int) ($payments[$branch->id]->cnt   ?? 0);
            $d = (int) ($donations[$branch->id]->cnt  ?? 0);
            $t = (int) ($trainings[$branch->id]->cnt  ?? 0);
            $a = (int) ($activities[$branch->id]->cnt ?? 0);

            $oldestDates = collect([
                $payments[$branch->id]->oldest   ?? null,
                $donations[$branch->id]->oldest  ?? null,
                $trainings[$branch->id]->oldest  ?? null,
                $activities[$branch->id]->oldest ?? null,
            ])->filter()->map(fn ($d) => \Carbon\Carbon::parse($d));

            return [
                'branch'      => $branch,
                'payments'    => $p,
                'donations'   => $d,
                'trainings'   => $t,
                'activities'  => $a,
                'total'       => $p + $d + $t + $a,
                'oldest'      => $oldestDates->isNotEmpty() ? $oldestDates->min() : null,
            ];
        })->sortByDesc('total')->values();

        $grandTotal = [
            'payments'   => $payments->sum('cnt'),
            'donations'  => $donations->sum('cnt'),
            'trainings'  => $trainings->sum('cnt'),
            'activities' => $activities->sum('cnt'),
            'total'      => $payments->sum('cnt') + $donations->sum('cnt')
                          + $trainings->sum('cnt') + $activities->sum('cnt'),
        ];

        return view('reports.pending-approvals', compact('rows', 'grandTotal', 'accessLevel', 'scopedId'));
    }
}
