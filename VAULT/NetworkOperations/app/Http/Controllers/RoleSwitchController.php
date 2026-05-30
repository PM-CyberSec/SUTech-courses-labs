<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class RoleSwitchController extends Controller
{
    public function __invoke(string $role): RedirectResponse
    {
        abort_unless(in_array($role, ['admin', 'engineer', 'viewer'], true), 404);

        session(['role' => $role]);

        return redirect()->back()->with('success', 'Role switched to '.ucfirst($role).'.');
    }
}
