<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class AdminController extends Controller
{
    public function dashboard()
    {
        // Redirect to the new reports dashboard
        return redirect()->route('reports.dashboard');
    }
}
