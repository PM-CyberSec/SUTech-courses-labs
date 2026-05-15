<?php

namespace App\Http\Controllers;

use App\Models\DldsEvent;

class EventPageController extends Controller
{
    public function index()
    {
        $events = DldsEvent::orderByDesc('id')->paginate(30);

        return view('pages.events', compact('events'));
    }
}