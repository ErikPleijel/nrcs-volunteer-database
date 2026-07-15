<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class TutorialCompletionReportController extends Controller
{
    public function index(Request $request)
    {
        $user        = Auth::user();
        $accessLevel = $user->getAccessLevel();

        abort_unless(in_array($accessLevel, ['national', 'branch']), 403);

        // Current filter values
        $name     = $request->input('name');
        $dbNumber = $request->input('db_number');
        $role     = $request->input('role');
        $branchId = $request->input('branch_id');
        $noLessons = $request->boolean('no_lessons');

        // Base query: selectable people (excludes super-admins) who hold a role
        $query = User::query()
            ->selectableForEntry()
            ->whereHas('roles');

        // Branch scoping
        if ($accessLevel === 'branch') {
            $query->where('branch_id', $user->getScopedBranchId());
        } else { // national
            if ($branchId !== null && $branchId !== '' && $branchId !== 'national') {
                $query->where('branch_id', $branchId);
            }
        }

        // Filters (both levels)
        if (filled($name)) {
            $query->where(function ($q) use ($name) {
                $q->where('first_name', 'LIKE', "%{$name}%")
                  ->orWhere('last_name', 'LIKE', "%{$name}%");
            });
        }

        if (filled($dbNumber)) {
            $digits = preg_replace('/\D/', '', $dbNumber);
            if ($digits !== '') {
                $query->where('id', $digits);
            }
        }

        if (filled($role)) {
            $query->whereHas('roles', fn($q) => $q->where('name', $role));
        }

        if ($noLessons) {
            $query->whereDoesntHave('tutorialProgress');
        }

        // Aggregates, eager loads, ordering
        $query->withCount('tutorialProgress')
            ->withMax('tutorialProgress as last_completed_at', 'completed_at')
            ->with([
                'tutorialProgress:id,user_id,lesson_key',
                'branch:id,name',
                'roles:id,name',
            ])
            ->orderByDesc('last_completed_at'); // NULL (never completed) sorts last on DESC

        $rows = $query->paginate(25)->withQueryString();

        // Dropdown data
        $branches = $accessLevel === 'national'
            ? Branch::orderBy('name')->get(['id', 'name'])
            : null;

        $roles = Role::where('name', '!=', 'super-admin')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Whether any filter is actively applied
        $hasFilters = filled($name)
            || filled($dbNumber)
            || filled($role)
            || ($accessLevel === 'national' && filled($branchId) && $branchId !== 'national')
            || $noLessons;

        $user->touchLastAdminActivity();

        return view('reports.tutorial-completion', [
            'rows'        => $rows,
            'branches'    => $branches,
            'roles'       => $roles,
            'accessLevel' => $accessLevel,
            'hasFilters'  => $hasFilters,
            // Current filter values (preserve in form)
            'name'        => $name,
            'dbNumber'    => $dbNumber,
            'role'        => $role,
            'branchId'    => $branchId,
            'noLessons'   => $noLessons,
        ]);
    }
}
