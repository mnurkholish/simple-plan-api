<?php

namespace App\Services;

use App\Models\DesignRequest;
use App\Models\User;
use App\Notifications\DesignRequestUpdatedNotification;
use Illuminate\Database\Eloquent\Builder;

class DesignNotificationService
{
    public function created(DesignRequest $request): void
    {
        foreach (User::query()->whereHas('roles', fn (Builder $query): Builder => $query->whereIn('name', ['super-admin', 'super_admin']))->get() as $admin) {
            $this->notify($admin, $request, 'Request Desain Baru', "Request desain {$request->request_number} menunggu verifikasi dan penugasan.");
        }
    }

    public function assigned(DesignRequest $request): void
    {
        if ($request->assignedOfficer) {
            $this->notify($request->assignedOfficer, $request, 'Desain Ditugaskan', "Anda ditugaskan mengerjakan request desain {$request->request_number}.");
        }
    }

    public function rejected(DesignRequest $request): void
    {
        $this->notify($request->requester, $request, 'Request Desain Ditolak', "Request desain {$request->request_number} ditolak. Lihat detail untuk alasan penolakan.");
    }

    public function draftUploaded(DesignRequest $request): void
    {
        $this->notify($request->requester, $request, 'Draft Desain Siap Ditinjau', "Draft desain {$request->request_number} sudah tersedia. Silakan tinjau hasilnya, lalu setujui jika sudah sesuai atau ajukan revisi jika masih perlu perubahan.");
    }

    public function revisionRequested(DesignRequest $request): void
    {
        if ($request->assignedOfficer) {
            $this->notify($request->assignedOfficer, $request, 'Revisi Desain Diminta', "Request desain {$request->request_number} memerlukan revisi.");
        }
    }

    public function approved(DesignRequest $request): void
    {
        if ($request->assignedOfficer) {
            $this->notify($request->assignedOfficer, $request, 'Desain Disetujui', "Request desain {$request->request_number} telah disetujui dan selesai.");
        }
    }

    private function notify(User $user, DesignRequest $request, string $title, string $message): void
    {
        $user->notify(new DesignRequestUpdatedNotification($request, $title, $message));
    }
}
