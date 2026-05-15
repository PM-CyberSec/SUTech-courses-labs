<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class NetworkPageController extends Controller
{
    public function index()
    {
        $network = \App\Models\DldsEvent::whereNotNull('src_ip')
            ->orWhereNotNull('dst_ip')
            ->latest()
            ->get();

        return view('pages.network', compact('network'));
    }
}
