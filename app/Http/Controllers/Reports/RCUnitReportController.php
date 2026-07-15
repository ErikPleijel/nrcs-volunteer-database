<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\RedCrossUnit;
use App\Services\Reports\RedCrossUnitStatsService;
use Illuminate\Http\Request;

class RCUnitReportController extends Controller
{
    protected $unitStatsService;

    public function __construct(RedCrossUnitStatsService $unitStatsService)
    {
        $this->unitStatsService = $unitStatsService;
    }

    public function index()
    {
        $units = RedCrossUnit::withCount('users')->get();
        return view('reports.units.index', compact('units'));
    }

    public function performance()
    {
        $data = [
            'unitPerformance' => $this->getUnitPerformance(),
        ];

        return view('reports.units.performance', compact('data'));
    }

    public function distribution()
    {
        $data = [
            'unitDistribution' => $this->getUnitDistribution(),
        ];

        return view('reports.units.distribution', compact('data'));
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
        $stats = $this->unitStatsService->getUnitStats();

        return response()->json($stats);
    }

    // Helper methods
    private function getUnitPerformance()
    {
        // Placeholder data
        return [
            'labels' => ['First Aid', 'Disaster Response', 'Youth', 'Training', 'Community'],
            'activities' => [45, 35, 30, 25, 20],
            'volunteers' => [180, 150, 140, 120, 110],
            'hours' => [450, 380, 320, 280, 250],
        ];
    }

    private function getUnitDistribution()
    {
        // Placeholder data
        return [
            'byBranch' => [
                'labels' => ['Lagos', 'Abuja', 'Rivers', 'Kano', 'Enugu'],
                'data' => [15, 12, 10, 8, 7],
            ],
            'bySize' => [
                'labels' => ['1-5 Members', '6-10 Members', '11-20 Members', '21-50 Members', '50+ Members'],
                'data' => [12, 18, 15, 7, 3],
            ],
            'byType' => [
                'labels' => ['First Aid', 'Disaster Response', 'Youth', 'Training', 'Community'],
                'data' => [20, 15, 12, 8, 10],
            ],
        ];
    }
}
