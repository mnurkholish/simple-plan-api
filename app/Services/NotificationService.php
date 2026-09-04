<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemActivityNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    /**
     * @return array{data: array<int, array<string, mixed>>, unread_count: int}
     */
    public function forUser(User $user): array
    {
        $notifications = $user->unreadNotifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $this->format($notification))
            ->values()
            ->all();

        return [
            'data' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ];
    }

    public function markAsRead(User $user, string $notificationId): void
    {
        $user->notifications()
            ->whereKey($notificationId)
            ->whereNull('read_at')
            ->firstOrFail()
            ->markAsRead();
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }

    public function recordLogin(User $user): void
    {
        $this->notify($user, 'Login berhasil', 'Ada sesi login baru pada '.now()->format('d M Y H:i').'.', 'IconLogin', 'emerald');
    }

    public function recordLogout(User $user): void
    {
        $this->notify($user, 'Logout berhasil', 'Sesi Anda berakhir pada '.now()->format('d M Y H:i').'.', 'IconLogout', 'slate');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activitySummary(): array
    {
        $today = now()->format('Y-m-d');

        return [
            [
                'id' => 'activity-login-today',
                'title' => 'Login hari ini',
                'subtitle' => Cache::get('login_today_'.$today, 0).' sesi login tercatat.',
                'icon' => 'IconLogin',
                'color' => 'emerald',
                'time' => 'Hari ini',
            ],
            [
                'id' => 'activity-logout-today',
                'title' => 'Logout hari ini',
                'subtitle' => Cache::get('logout_today_'.$today, 0).' sesi logout tercatat.',
                'icon' => 'IconLogout',
                'color' => 'slate',
                'time' => 'Hari ini',
            ],
        ];
    }

    private function notify(
        User $user,
        string $title,
        string $subtitle,
        string $icon,
        string $color,
    ): void {
        $user->notify(new SystemActivityNotification($title, $subtitle, $icon, $color));
    }

    /**
     * @return array<string, mixed>
     */
    private function format(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->data['title'] ?? 'Notifikasi',
            'subtitle' => $notification->data['subtitle'] ?? '',
            'icon' => $notification->data['icon'] ?? 'IconInfoCircle',
            'color' => $notification->data['color'] ?? 'slate',
            'time' => $notification->created_at?->diffForHumans(),
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
        ];
    }
}
