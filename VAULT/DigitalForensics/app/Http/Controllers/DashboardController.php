<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DldsEvent;

class DashboardController extends Controller
{
    public function index()
    {
        return view('pages.dashboard', [
            'totalEvents' => DldsEvent::count(),
            'highSeverity' => DldsEvent::where('severity', 'HIGH')->count(),
            'mediumSeverity' => DldsEvent::where('severity', 'MEDIUM')->count(),
            'lowSeverity' => DldsEvent::where('severity', 'LOW')->count(),
        ]);
    }
}