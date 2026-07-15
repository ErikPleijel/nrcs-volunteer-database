<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class LifecycleController extends Controller
{
    public function awaitingEngagement()
    {
        $user = Auth::user();

        // v1: global count (like your dashboard card). We’ll scope later.
        $awaitingCount = User::query()->awaitingEngagement()->count();

        return view('lifecycle.awaiting-assignment', [
            'awaitingCount' => $awaitingCount,
        ]);
    }

    public function active()
    {
        $user = Auth::user();

        // v1: global count. We'll scope later via getAccessLevel()/getScopedId().
        $activeCount = User::query()->active()->count(); // make sure scopeActive exists

        return view('lifecycle.active', [
            'activeCount' => $activeCount,
        ]);
    }


    public function dormant()
    {
        $dormantCount = User::query()->dormant()->count(); // or where lifecycle_status='dormant'
        return view('lifecycle.dormant', compact('dormantCount'));
    }

    public function archived()
    {
        $archivedCount = User::query()->archived()->count(); // or where lifecycle_status='archived'
        return view('lifecycle.archived', compact('archivedCount'));
    }


}
