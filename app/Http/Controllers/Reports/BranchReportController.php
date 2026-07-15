<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Reports\BranchStatsService;
use Illuminate\Http\Request;

class BranchReportController extends Controller
{
    protected $branchStatsService;

    public function __construct(BranchStatsService $branchStatsService)
    {
        $this->branchStatsService = $branchStatsService;
    }

    public function index()
    {
        $branches = Branch::withCount('users')->get();
        return view('reports.branches.index', compact('branches'));
    }

    public function comparison()
    {
        $data = [
            'branchComparison' => $this->getBranchComparison(),
        ];

        return view('reports.branches.comparison', compact('data'));
    }

    public function growth()
    {
        $data = [
            'branchGrowth' => $this->getBranchGrowth(),
        ];

        return view('reports.branches.growth', compact('data'));
    }

    public function export($type)
    {
        // Export logic will go here
        // $type can be 'pdf', 'excel', 'csv'

        return response()->download('/path/to/generated/file');
    }

    public function getStats(Request $request)
    {
        // Logic to get stats for API calls
        $stats = $this->branchStatsService->getBranchStats();

        return response()->json($stats);
    }

    // Helper methods
    private function getBranchComparison()
    {
        // Placeholder data
        return [
            'labels' => ['Lagos', 'Abuja', 'Rivers', 'Kano', 'Enugu'],
            'members' => [230, 180, 150, 120, 90],
            'volunteers' => [180, 150, 110, 90, 70],
            'activities' => [45, 40, 35, 30, 25],
        ];
    }

    private function getBranchGrowth()
    {
        // Placeholder data for 6 months
        return [
            'labels' => ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'lagos' => [410, 420, 430, 450, 460, 480],
            'abuja' => [310, 320, 330, 340, 350, 360],
            'rivers' => [210, 220, 230, 240, 250, 260],
        ];
    }
}
