<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class ArchivedAccountController extends Controller
{
    public function show(Request $request)
    {
        $dbReference   = session('archived_db_ref');
        $name          = session('archived_name');
        $branchId      = $request->filled('branch_id') ? (int) $request->branch_id : session('archived_branch_id');
        $branch        = $branchId ? Branch::find($branchId) : null;
        $selfInitiated = $request->boolean('self');

        $rejoinSubject = 'Request to rejoin the Nigerian Red Cross Society';
        $rejoinBody =
            'Dear ' . ($branch?->name ? $branch->name . ' Branch' : 'Nigerian Red Cross Society') . ",\n\n"
            . "My Nigerian Red Cross Society account has been archived. I would like to rejoin and "
            . "continue my involvement — as a member, volunteer, or donor.\n\n"
            . ($dbReference ? "My reference number is {$dbReference}.\n\n" : "")
            . "Please let me know what I need to do to reactivate my account.\n\n"
            . "Thank you,\n"
            . ($name ?: '[Your name]');

        return view('auth.archived-account', compact('branch', 'dbReference', 'rejoinSubject', 'rejoinBody', 'selfInitiated'));
    }
}
