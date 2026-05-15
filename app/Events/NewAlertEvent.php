<?php

namespace App\Events;

use App\Models\DldsEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class NewAlertEvent implements ShouldBroadcast
{
    use SerializesModels;

    public $event;

    public function __construct(DldsEvent $event)
    {
        $this->event = $event;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('dlds-alerts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'new.alert';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->event->id,
            'type' => $this->event->type,
            'severity' => $this->event->severity,
            'description' => $this->event->description,
            'timestamp' => $this->event->event_timestamp, // ✅ FIXED
        ];
    }
}