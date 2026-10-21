<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TicketStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly string $message,
        public readonly string $title = 'Update Status Tiket',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            // Field khusus sesuai instruksi
            'ticket_id' => $this->ticket->id,
            'nomor_tiket' => $this->ticket->ticket_number,
            'status' => $this->ticket->status->value,
            'message' => $this->message,
            'url' => '/helpdesk/'.$this->ticket->service->value.'/'.$this->ticket->getKey(),
            'target' => [
                'feature' => 'helpdesk',
                'service' => $this->ticket->service->value,
                'resource_id' => $this->ticket->getKey(),
            ],

            // Field tambahan agar kompatibel dengan format bawaan frontend/NotificationService
            'title' => $this->title,
            'subtitle' => $this->message,
            'icon' => 'IconTicket',
            'color' => 'blue',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
