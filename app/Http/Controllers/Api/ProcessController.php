<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DldsEvent;

class ProcessController extends Controller
{
    public function index()
    {
        return DldsEvent::where('type', 'process')
            ->latest()
            ->limit(100)
            ->get();
        return view('pages.processes', compact('processes'));
    }
}