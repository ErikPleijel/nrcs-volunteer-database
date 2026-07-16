<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;

class PoliciesController extends Controller
{
    /**
     * Static reference page explaining the approval and lifecycle policies.
     * Gated only by the route group's can:view_reports middleware.
     */
    public function index()
    {
        return view('reports.policies');
    }
}
