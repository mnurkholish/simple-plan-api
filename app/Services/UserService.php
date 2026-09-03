<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserService
{
    public function store(array $data, ?UploadedFile $avatar = null): User
    {
        $avatarPath = $avatar?->store('avatars', 'public');

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'nip' => $data['nip'] ?? null,
            'password' => Hash::make($data['password']),
            'avatar' => $avatarPath,
            'status' => $data['status'] ?? 'active',
        ]);

        if (isset($data['selectedRoles'])) {
            $user->assignRole($data['selectedRoles']);
        }

        if (isset($data['selectedUnits'])) {
            $user->units()->sync($data['selectedUnits']);
        }

        return $user;
    }

    public function update(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        $avatarPath = $user->getRawOriginal('avatar');

        if ($avatar) {
            if ($avatarPath) {
                Storage::disk('public')->delete($avatarPath);
            }

            $avatarPath = $avatar->store('avatars', 'public');
        }

        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'nip' => $data['nip'] ?? null,
            'avatar' => $avatarPath,
            'status' => $data['status'] ?? $user->status,
        ];

        if (! empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        if (isset($data['selectedRoles'])) {
            $user->syncRoles($data['selectedRoles']);
        }

        $user->units()->sync($data['selectedUnits'] ?? []);

        return $user;
    }

    public function destroy(array $ids): void
    {
        User::whereIn('id', $ids)->get()->each->delete();
    }
}
