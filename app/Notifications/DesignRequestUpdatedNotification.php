<?php

namespace App\Notifications;

use App\Models\DesignRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class DesignRequestUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly DesignRequest $designRequest,
        public readonly string $title,
        public readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'design_request_id' => $this->designRequest->getKey(),
            'request_number' => $this->designRequest->request_number,
            'status' => $this->designRequest->status->value,
            'title' => $this->title,
            'subtitle' => $this->message,
            'icon' => 'IconPalette',
            'color' => 'violet',
            'url' => '/design-requests/'.$this->designRequest->getKey(),
            'target' => [
                'feature' => 'design-requests',
                'resource_id' => $this->designRequest->getKey(),
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
