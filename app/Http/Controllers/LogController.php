<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Division;
use App\Models\Log;
use App\Models\MembershipPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $query = Log::with([
            'user',
            'branch',
            'division',
            'subject',
        ])->orderByDesc('created_at');

        // --- Access level scoping ---
        switch ($accessLevel) {
            case 'national':
                break;
            case 'branch':
                if ($scopedId) {
                    $query->forBranch($scopedId);
                }
                break;
            case 'division':
                if ($scopedId) {
                    $query->forDivision($scopedId);
                }
                break;
            default:
                $query->whereRaw('0 = 1');
                break;
        }

        // --- Filters ---

        // Combined search:
        // - always: description / action / subject type / subject id
        // - if numeric: also treat as "user id" (actor or member in JSON snapshots)
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                // Textual search
                $q->where('description', 'like', '%' . $search . '%')
                    ->orWhere('action', 'like', '%' . $search . '%')
                    ->orWhere('subject_type', 'like', '%' . $search . '%')
                    ->orWhere('subject_id', 'like', '%' . $search . '%');

                // If numeric → also search as a "user id" across actor + JSON snapshots
                if (is_numeric($search)) {
                    $userId = (int) $search;

                    $q->orWhere('user_id', $userId)
                      ->orWhere(function ($q2) use ($userId) {
                          $q2->where('subject_type', \App\Models\User::class)
                             ->where('subject_id', $userId);
                      })
                      ->orWhereRaw("JSON_EXTRACT(old_values, '$.user_id') = ?", [$userId])
                      ->orWhereRaw("JSON_EXTRACT(new_values, '$.user_id') = ?", [$userId])
                      ->orWhereRaw("JSON_EXTRACT(old_values, '$.submitted_by_user_id') = ?", [$userId])
                      ->orWhereRaw("JSON_EXTRACT(new_values, '$.submitted_by_user_id') = ?", [$userId])
                      ->orWhereRaw("JSON_EXTRACT(old_values, '$.entered_by_user_id') = ?", [$userId])
                      ->orWhereRaw("JSON_EXTRACT(new_values, '$.entered_by_user_id') = ?", [$userId])
                      ->orWhereRaw("JSON_EXTRACT(old_values, '$.removed_by_user_id') = ?", [$userId])
                      ->orWhereRaw("JSON_EXTRACT(new_values, '$.removed_by_user_id') = ?", [$userId]);
                }
            });
        }

        // Action
        if ($request->filled('action')) {
            $query->forAction($request->action);
        }

        // Branch / Division from request (guarded by access level)
        if ($accessLevel === 'national' && $request->filled('branch_id')) {
            $query->forBranch((int) $request->branch_id);
        }

        if (in_array($accessLevel, ['national', 'branch']) && $request->filled('division_id')) {
            $query->forDivision((int) $request->division_id);
        }

        // Date range
        if ($request->filled('from_date')) {
            $query->fromDate($request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->toDate($request->to_date);
        }

        $logs = $query->paginate(25)->appends($request->query());

        $branches = Branch::select('id', 'name')
            ->orderBy('name')
            ->get();

        $divisionsQuery = Division::select('id', 'name', 'branch_id')->orderBy('name');
        if ($accessLevel === 'branch' && $scopedId) {
            $divisionsQuery->where('branch_id', $scopedId);
        }
        $divisions = $divisionsQuery->get();

        $actions = Log::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('logs.index', [
            'logs'            => $logs,
            'branches'        => $branches,
            'divisions'       => $divisions,
            'actions'         => $actions,
            'user'            => $user,
            'accessLevel'     => $accessLevel,
            'scopedId'        => $scopedId,
            'selectedBranch'  => $request->input('branch_id'),
            'selectedDivision'=> $request->input('division_id'),
        ]);
    }
}
