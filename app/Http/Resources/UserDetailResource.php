<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->name,
            'nip' => $this->nip,
            'unit_user' => $this->unit?->unit_name,
            'jabatan' => $this->jabatan,
            'role' => $this->roles->first()?->name ?? null,
            'no_hp' => $this->no_hp,
            'status_user' => $this->status_user,
            'alasan_nonaktif' => $this->alasan_nonaktif,
            'riwayat_status_akun' => $this->riwayat_status_akun,
        ];
    }
}
