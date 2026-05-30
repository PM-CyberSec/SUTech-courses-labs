<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['web', 'auth']]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('dlds-events', function ($user) {
    if (! $user) {
        return false;
    }

    if (! $user->isApproved()) {
        return false;
    }

    if (! $user->hasRole('admin', 'analyst', 'viewer')) {
        return false;
    }

    return true;
});
