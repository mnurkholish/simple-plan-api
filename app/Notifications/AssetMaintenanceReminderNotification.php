<?php

namespace App\Notifications;

use App\Models\AssetMaintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AssetMaintenanceReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AssetMaintenance $schedule,
        public readonly string $reminderType = 'hari-h',
        public ?string $message = null,
    ) {
        $itemName = $schedule->assetCode?->assetItem?->name
            ?? $schedule->assetCode?->item?->name
            ?? 'Aset';

        if ($this->message === null) {
            $this->message = match (strtolower($reminderType)) {
                'h-3' => "Pengingat: 3 hari lagi (H-3) adalah jadwal pemeliharaan aset {$itemName}.",
                'h-1' => "Pengingat: Besok (H-1) adalah jadwal pemeliharaan aset {$itemName}.",
                'hari-h', 'today' => "Pengingat: Hari ini adalah jadwal pemeliharaan aset {$itemName}.",
                default => "Pengingat: Jadwal pemeliharaan aset {$itemName} akan segera dilaksanakan ({$reminderType}).",
            };
        }
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $itemName = $this->schedule->assetCode?->assetItem?->name
            ?? $this->schedule->assetCode?->item?->name
            ?? 'Aset';

        return [
            'maintenance_id' => $this->schedule->id,
            'asset_code_id' => $this->schedule->asset_code_id,
            'full_code' => $this->schedule->assetCode?->full_code,
            'item_name' => $itemName,
            'maintenance_type' => $this->schedule->maintenance_type,
            'scheduled_date' => $this->schedule->next_scheduled_date?->format('Y-m-d'),
            'reminder_type' => $this->reminderType,
            'status' => $this->schedule->status->value ?? $this->schedule->status,
            'message' => $this->message,
            'title' => 'Pengingat Pemeliharaan Aset',
            'subtitle' => $this->message,
            'icon' => 'IconCalendarEvent',
            'color' => 'blue',
            'url' => '/inventory/asset-maintenances/'.$this->schedule->id,
            'target' => [
                'feature' => 'inventory',
                'subfeature' => 'maintenance',
                'resource_id' => $this->schedule->id,
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
