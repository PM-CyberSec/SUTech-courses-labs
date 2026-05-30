<?php

namespace App\Http\Controllers;

use App\Models\DldsEvent;

class AlertPageController extends Controller
{
    public function index()
    {
        $alerts = DldsEvent::whereNotNull('alert_type')
            ->orWhere('severity', 'HIGH')
            ->latest()
            ->get();

        return view('pages.alerts', compact('alerts'));
    }
}