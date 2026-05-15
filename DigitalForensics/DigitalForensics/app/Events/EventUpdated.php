<?php

namespace App\Events;

use App\Models\DldsEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DldsEvent $event) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dlds-events'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'event.updated';
    }

    public function broadcastWith(): array
    {
        $this->event->loadMissing(['eventType', 'process', 'alertCategory', 'severityLevel']);

        return [
            'event' => $this->event->toArray(),
        ];
    }
}
