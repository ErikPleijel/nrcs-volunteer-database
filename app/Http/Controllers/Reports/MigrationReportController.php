<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Log as AuditLog;
use Illuminate\Http\Request;

class MigrationReportController extends Controller
{
    public function index(Request $request)
    {
        $authUser    = auth()->user();
        $accessLevel = $authUser->getAccessLevel();
        $scopedId    = $authUser->getScopedId();

        $branches = Branch::orderBy('name')->get(['id', 'name']);

        if ($accessLevel === 'national') {
            $selectedBranchId   = $request->input('branch_id') ?? $branches->first()?->id;
            $selectedDivisionId = null;
        } elseif ($accessLevel === 'branch') {
            $selectedBranchId   = $scopedId;
            $selectedDivisionId = null;
        } else {
            $selectedDivisionId = $scopedId;
            $selectedBranchId   = $authUser->branch_id;
        }

        $movementType = $request->input('movement_type', 'branch');
        $direction    = $request->input('direction', 'both');
        $dateFrom     = $request->input('date_from', now()->subDays(90)->toDateString());
        $dateTo       = $request->input('date_to', now()->toDateString());

        // Use JSON_EXTRACT — do NOT rely on the branch_id FK column (known null bug).
        $query = AuditLog::query()
            ->where('action', 'member_branch_division_changed')
            ->whereBetween('created_at', [
                $dateFrom . ' 00:00:00',
                $dateTo   . ' 23:59:59',
            ])
            ->with(['subject', 'user:id,first_name,last_name']);

        if ($movementType === 'branch') {
            $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(old_values, '$.branch_id')) != " .
                "JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.branch_id'))"
            );

            if ($direction === 'in') {
                $query->whereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.branch_id')) = ?",
                    [$selectedBranchId]
                );
            } elseif ($direction === 'out') {
                $query->whereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(old_values, '$.branch_id')) = ?",
                    [$selectedBranchId]
                );
            } else {
                $query->where(function ($q) use ($selectedBranchId) {
                    $q->whereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.branch_id')) = ?",
                        [$selectedBranchId]
                    )->orWhereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(old_values, '$.branch_id')) = ?",
                        [$selectedBranchId]
                    );
                });
            }
        } else {
            $divId = $selectedDivisionId ?? $request->input('division_id');

            $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(old_values, '$.division_id')) != " .
                "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.division_id')), '')"
            );

            if ($divId) {
                if ($direction === 'in') {
                    $query->whereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.division_id')) = ?",
                        [$divId]
                    );
                } elseif ($direction === 'out') {
                    $query->whereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(old_values, '$.division_id')) = ?",
                        [$divId]
                    );
                } else {
                    $query->where(function ($q) use ($divId) {
                        $q->whereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.division_id')) = ?",
                            [$divId]
                        )->orWhereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(old_values, '$.division_id')) = ?",
                            [$divId]
                        );
                    });
                }
            }
        }

        $logs = $query->orderByDesc('created_at')->paginate(50);

        $branchIds   = collect();
        $divisionIds = collect();
        foreach ($logs as $log) {
            $old = $log->old_values ?? [];
            $new = $log->new_values ?? [];
            if (!empty($old['branch_id']))   $branchIds->push((int) $old['branch_id']);
            if (!empty($new['branch_id']))   $branchIds->push((int) $new['branch_id']);
            if (!empty($old['division_id'])) $divisionIds->push((int) $old['division_id']);
            if (!empty($new['division_id'])) $divisionIds->push((int) $new['division_id']);
        }
        $branchMap   = Branch::whereIn('id', $branchIds->unique())->pluck('name', 'id');
        $divisionMap = Division::whereIn('id', $divisionIds->unique())->pluck('name', 'id');

        $divisionsForBranch = $selectedBranchId
            ? Division::where('branch_id', $selectedBranchId)->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('reports.migration', compact(
            'logs', 'branches', 'divisionsForBranch',
            'branchMap', 'divisionMap',
            'accessLevel', 'selectedBranchId', 'selectedDivisionId',
            'movementType', 'direction', 'dateFrom', 'dateTo'
        ));
    }
}
