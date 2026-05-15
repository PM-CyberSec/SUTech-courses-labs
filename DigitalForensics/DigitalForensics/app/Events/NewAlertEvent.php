<?php

namespace App\Events;

use App\Models\DldsEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewAlertEvent implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(public DldsEvent $event) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dlds-events'),
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
            'event' => $this->event->toArray(),
        ];
    }
}
