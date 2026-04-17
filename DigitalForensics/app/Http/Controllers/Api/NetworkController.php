<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DldsEvent;

class NetworkController extends Controller
{
    public function index()
    {
        return DldsEvent::where('type', 'network')
            ->latest()
            ->limit(100)
            ->get();
        
        return view('pages.network', compact('network'));
    }
}