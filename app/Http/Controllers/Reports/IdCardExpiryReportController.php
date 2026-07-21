<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\IdCardPrint;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class IdCardExpiryReportController extends Controller
{
    private function expiryColumns(): array
    {
        $now = Carbon::now();
        $columns = [];

        // 3 months back: index -3, -2, -1
        for ($i = -3; $i <= -1; $i++) {
            $columns[$i] = [
                'from'  => $now->copy()->addMonths($i)->startOfDay(),
                'to'    => $now->copy()->addMonths($i + 1)->startOfDay(),
                'label' => abs($i) . 'mo ago',
                'past'  => true,
                'color' => 'gray',
            ];
        }

        // Forward: 3 individual months, then two merged 3-month bands
        $columns[1] = [
            'from'  => $now->copy()->startOfDay(),
            'to'    => $now->copy()->addMonths(1)->startOfDay(),
            'label' => '< 1mo',
            'past'  => false,
            'color' => 'red',
        ];
        $columns[2] = [
            'from'  => $now->copy()->addMonths(1)->startOfDay(),
            'to'    => $now->copy()->addMonths(2)->startOfDay(),
            'label' => '2mo',
            'past'  => false,
            'color' => 'red',
        ];
        $columns[3] = [
            'from'  => $now->copy()->addMonths(2)->startOfDay(),
            'to'    => $now->copy()->addMonths(3)->startOfDay(),
            'label' => '3mo',
            'past'  => false,
            'color' => 'orange',
        ];
        $columns['4-6'] = [
            'from'  => $now->copy()->addMonths(3)->startOfDay(),
            'to'    => $now->copy()->addMonths(6)->startOfDay(),
            'label' => '4-6mo',
            'past'  => false,
            'color' => 'orange',
        ];
        $columns['7-9'] = [
            'from'  => $now->copy()->addMonths(6)->startOfDay(),
            'to'    => $now->copy()->addMonths(9)->startOfDay(),
            'label' => '7-9mo',
            'past'  => false,
            'color' => 'blue',
        ];

        return $columns;
    }

    /**
     * Resolve the current user's access level and the branch/division IDs their access is scoped to.
     */
    private function resolveAccessContext(User $user): array
    {
        $accessLevel = $user->getAccessLevel();

        return [
            'accessLevel'    => $accessLevel,
            'userBranchId'   => in_array($accessLevel, ['branch', 'division'], true) ? $user->getScopedBranchId() : null,
            'userDivisionId' => $accessLevel === 'division' ? $user->getScopedId() : null,
        ];
    }

    /**
     * Count valid (non-expired) ID cards expiring in a given window for a set of user IDs.
     */
    private function countExpiring($userIds, Carbon $from, Carbon $to): int
    {
        return IdCardPrint::whereIn('user_id', $userIds)
            ->whereRaw('id = (SELECT MAX(id) FROM id_card_prints p2 WHERE p2.user_id = id_card_prints.user_id)')
            ->where('expiry_date', '>=', $from)
            ->where('expiry_date', '<', $to)
            ->count();
    }

    /**
     * National level: list all branches with expiry columns.
     */
    public function national()
    {
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();

        if ($accessLevel === 'branch') {
            return redirect()->route('reports.id-card-expiry.branch', $user->getScopedBranchId());
        }
        if ($accessLevel === 'division') {
            return redirect()->route('reports.id-card-expiry.division', [$user->getScopedBranchId(), $user->getScopedId()]);
        }

        ['accessLevel' => $accessLevel, 'userBranchId' => $userBranchId, 'userDivisionId' => $userDivisionId] = $this->resolveAccessContext($user);

        $columns = $this->expiryColumns();
        $branches = Branch::active()->orderBy('name')->get();

        $rows = $branches->map(function ($branch) use ($columns) {
            $userIds = $branch->users()->pluck('id');
            $counts = [];
            foreach ($columns as $i => $col) {
                $counts[$i] = $this->countExpiring($userIds, $col['from'], $col['to']);
            }
            return [
                'id'     => $branch->id,
                'name'   => $branch->name,
                'counts' => $counts,
                'total'  => array_sum($counts),
            ];
        });

        return view('reports.campaign-planning.id-card-expiry', compact('rows', 'columns', 'accessLevel', 'userBranchId', 'userDivisionId'));
    }

    /**
     * Branch level: list divisions within a branch with expiry columns.
     */
    public function branch(Branch $branch)
    {
        ['accessLevel' => $accessLevel, 'userBranchId' => $userBranchId, 'userDivisionId' => $userDivisionId] = $this->resolveAccessContext(Auth::user());

        $columns = $this->expiryColumns();
        $divisions = Division::where('branch_id', $branch->id)->orderBy('name')->get();

        $rows = $divisions->map(function ($division) use ($columns) {
            $userIds = User::where('division_id', $division->id)->pluck('id');
            $counts = [];
            foreach ($columns as $i => $col) {
                $counts[$i] = $this->countExpiring($userIds, $col['from'], $col['to']);
            }
            return [
                'id'     => $division->id,
                'name'   => $division->name,
                'counts' => $counts,
                'total'  => array_sum($counts),
            ];
        });

        return view('reports.campaign-planning.id-card-expiry', compact('branch', 'rows', 'columns', 'accessLevel', 'userBranchId', 'userDivisionId'));
    }

    /**
     * Division level: list RC units within a division with expiry columns.
     */
    public function division(Branch $branch, Division $division)
    {
        ['accessLevel' => $accessLevel, 'userBranchId' => $userBranchId, 'userDivisionId' => $userDivisionId] = $this->resolveAccessContext(Auth::user());

        $columns = $this->expiryColumns();
        $units = RedCrossUnit::where('division_id', $division->id)->orderBy('name')->get();

        $rows = $units->map(function ($unit) use ($columns) {
            $userIds = $unit->activeUsers()->pluck('id');
            $counts = [];
            foreach ($columns as $i => $col) {
                $counts[$i] = $this->countExpiring($userIds, $col['from'], $col['to']);
            }
            return [
                'id'     => $unit->id,
                'name'   => $unit->name,
                'counts' => $counts,
                'total'  => array_sum($counts),
            ];
        });

        // Also count division users not in any RC unit
        $usersWithUnit = $units->flatMap(fn($u) => $u->activeUsers()->pluck('id'));
        $allDivisionUserIds = User::where('division_id', $division->id)
            ->where('lifecycle_status', '!=', 'archived')
            ->pluck('id');
        $unassignedIds = $allDivisionUserIds->diff($usersWithUnit);
        if ($unassignedIds->isNotEmpty()) {
            $counts = [];
            foreach ($columns as $i => $col) {
                $counts[$i] = $this->countExpiring($unassignedIds, $col['from'], $col['to']);
            }
            $rows->push([
                'id'     => null,
                'name'   => '(No RC Unit)',
                'counts' => $counts,
                'total'  => array_sum($counts),
            ]);
        }

        return view('reports.campaign-planning.id-card-expiry', compact('branch', 'division', 'rows', 'columns', 'accessLevel', 'userBranchId', 'userDivisionId'));
    }
}
