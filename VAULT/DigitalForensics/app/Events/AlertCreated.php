<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class AlertCreated implements ShouldBroadcast
{
    use SerializesModels;

    public $alert;

    public function __construct($alert)
    {
        $this->alert = $alert;
    }

    public function broadcastOn()
    {
        return new Channel('dlds-alerts');
    }

    public function broadcastAs()
    {
        return 'alert.created';
    }
}