<?php

namespace App\Notifications;

use App\Models\AssetMaintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AssetMaintenanceOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AssetMaintenance $schedule,
        public ?string $message = null,
    ) {
        $itemName = $schedule->assetCode?->assetItem?->name
            ?? $schedule->assetCode?->item?->name
            ?? 'Aset';

        $this->message ??= "Jadwal pemeliharaan aset {$itemName} telah melewati batas waktu (terlambat).";
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
            'status' => 'terlambat',
            'message' => $this->message,
            'title' => 'Jadwal Pemeliharaan Terlambat',
            'subtitle' => $this->message,
            'icon' => 'IconAlertTriangle',
            'color' => 'red',
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
