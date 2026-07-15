<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Reports\BranchStatsService;
use Illuminate\Http\JsonResponse;

class BranchMapController extends Controller
{
    public function __construct(
        private BranchStatsService $branchStatsService
    ) {}

    public function getMapData(): JsonResponse
    {
        $branches = Branch::where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select([
                'id', 'name', 'code', 'zone', 'is_active',
                'physical_address', 'telephone', 'email',
                'latitude', 'longitude'
            ])
            ->get();

        // Get statistics for all branches
        $branchesStats = $this->branchStatsService->getAllBranchesStats();

        return response()->json([
            'branches' => $branches,
            'statistics' => $branchesStats,
            'total_count' => $branches->count()
        ]);
    }

    public function getBranchStatistics(Branch $branch): JsonResponse
    {
        try {
            $statistics = $this->branchStatsService->getBranchStats($branch->id);

            return response()->json([
                'branch_id' => $branch->id,
                'statistics' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
