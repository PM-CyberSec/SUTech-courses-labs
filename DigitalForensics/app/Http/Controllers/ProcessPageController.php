<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProcessPageController
{
    public function index()
    {
        $processes = \App\Models\DldsEvent::whereNotNull('pid')
            ->orWhereNotNull('process_name')
            ->latest()
            ->get();

        return view('pages.processes', compact('processes'));
    }
}