<?php

namespace App\Events;

use App\Models\DldsEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class NewAlertEvent implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public DldsEvent $event) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('dlds-events'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'dlds.event.created';
    }

    public function broadcastWith(): array
    {
        $this->event->loadMissing(['eventType', 'process', 'alertCategory', 'severityLevel']);

        return [
            'event' => $this->event->toApiArray(),
        ];
    }
}
