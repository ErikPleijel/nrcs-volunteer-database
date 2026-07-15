<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\DatabaseAccessStatsService;
use App\Models\Branch;
use Illuminate\Http\Request;
use App\Models\User as UserModel; // avoid confusion with Request::user()

class DatabaseAccessReportController extends Controller
{
    protected DatabaseAccessStatsService $stats;

    public function __construct(DatabaseAccessStatsService $stats)
    {
        $this->stats = $stats;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $title      = 'Database Access Team';
        $pageHeader = 'Database Access Team';

        // Main data
        $team    = $this->stats->getDatabaseAccessTeam();
        $summary = $this->stats->getDatabaseAccessRoleSummary();
        $directPermissionUsers = $this->stats->getUsersWithDirectPermissions();

        // -------- Branch handling for branch & division tables --------
        $branches = Branch::select('id', 'name')
            ->orderBy('name')
            ->get();

        // Non-national users are locked to their own branch; ignore any request param
        if ($accessLevel !== 'national') {
            $selectedBranchId = $scopedId;
        } else {
            $selectedBranchId = $request->input('branch_id');

            if (!$selectedBranchId) {
                $selectedBranchId = $branches->isNotEmpty() ? $branches->first()->id : null;
            }
        }

        // Make sure it's an int or null
        $selectedBranchId = $selectedBranchId ? (int) $selectedBranchId : null;

        return view('reports.database-access.index', compact(
            'title',
            'pageHeader',
            'team',
            'summary',
            'directPermissionUsers',
            'branches',
            'selectedBranchId'
        ));
    }
}
