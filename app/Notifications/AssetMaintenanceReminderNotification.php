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
        public readonly bool $isUrgent = false,
    ) {
        $itemName = $schedule->assetCode?->assetItem?->name
            ?? $schedule->assetCode?->item?->name
            ?? 'Aset';

        if ($this->message === null) {
            $prefix = $this->isUrgent ? 'Tugas Mendesak: ' : 'Pengingat: ';
            $this->message = match (strtolower($reminderType)) {
                'h-3' => "{$prefix}3 hari lagi (H-3) adalah jadwal pemeliharaan aset {$itemName}.",
                'h-2' => "{$prefix}2 hari lagi (H-2) adalah jadwal pemeliharaan aset {$itemName}.",
                'h-1' => "{$prefix}Besok (H-1) adalah jadwal pemeliharaan aset {$itemName}.",
                'hari-h', 'today' => "{$prefix}Hari ini adalah jadwal pemeliharaan aset {$itemName}.",
                default => "{$prefix}Jadwal pemeliharaan aset {$itemName} akan segera dilaksanakan ({$reminderType}).",
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
            'title' => $this->isUrgent ? 'Tugas Pemeliharaan Mendesak' : 'Pengingat Pemeliharaan Aset',
            'subtitle' => $this->message,
            'icon' => $this->isUrgent ? 'IconAlertCircle' : 'IconCalendarEvent',
            'color' => $this->isUrgent ? 'orange' : 'blue',
            'url' => '/inventory/asset-maintenances/'.$this->schedule->id,
            'target' => [
                'feature' => 'inventory',
                'subfeature' => 'maintenance',
                'resource_id' => $this->schedule->id,
            ],
            'is_urgent' => $this->isUrgent,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
