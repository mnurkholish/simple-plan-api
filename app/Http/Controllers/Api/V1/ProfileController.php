<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileDestroyRequest;
use App\Http\Requests\ProfilePasswordRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource(
            $request->user()->load(['roles.permissions', 'unit'])
        );
    }

    public function update(ProfileUpdateRequest $request): UserResource
    {
        $user = $request->user();
        $data = $request->validated();
        unset($data['avatar']);

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('avatar')) {
            $avatarPath = $user->getRawOriginal('avatar');

            if ($avatarPath && ! str_starts_with($avatarPath, 'http')) {
                Storage::disk('public')->delete($avatarPath);
            }

            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        return (new UserResource($user->load(['roles.permissions', 'unit'])))
            ->additional(['message' => 'Profil berhasil diperbarui.']);
    }

    public function updatePassword(ProfilePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
        ]);
    }

    public function destroy(ProfileDestroyRequest $request): JsonResponse
    {
        $user = $request->user();

        if (
            $user->hasRole('super-admin')
            && ! User::role('super-admin')->whereKeyNot($user->getKey())->exists()
        ) {
            throw ValidationException::withMessages([
                'password' => ['Super admin terakhir tidak dapat menghapus akunnya sendiri.'],
            ]);
        }

        $avatarPath = $user->getRawOriginal('avatar');

        $user->tokens()->delete();
        $user->delete();

        if ($avatarPath && ! str_starts_with($avatarPath, 'http')) {
            Storage::disk('public')->delete($avatarPath);
        }

        return response()->json([
            'message' => 'Akun berhasil dihapus.',
        ]);
    }
}
