<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PolicyAcceptanceController extends Controller
{
    public function show()
    {
        return view('policy.accept');
    }

    public function store(Request $request)
    {
        $request->validate([
            'policy_accepted' => 'accepted',
        ]);

        $user = $request->user();
        $user->policy_accepted_at = now();
        $user->save();

        return redirect()->intended(route('reports.dashboard'));
    }
}
