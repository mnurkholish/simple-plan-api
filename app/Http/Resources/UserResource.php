<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'nip' => $this->nip,
            'avatar' => $this->avatar,
            'email_verified_at' => $this->email_verified_at,
            'status' => $this->status,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'units' => UnitResource::collection($this->whenLoaded('units')),
            'permissions' => $this->when(
                $request->routeIs('api.v1.auth.*', 'api.v1.profile.*', 'api.v1.impersonation.*'),
                fn (): array => $this->getPermissions(),
            ),
            'created_at' => $this->created_at,
        ];
    }
}
