<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(
        protected UserRepository $userRepository
    ) {}

    public function getAllUsers(int $perPage = 15): LengthAwarePaginator
    {
        return $this->userRepository->getPaginatedUsers($perPage);
    }

    public function getUserById(int $id): User
    {
        $user = $this->userRepository->findById($id);
        $user->load(['unit', 'roles']);

        return $user;
    }

    public function createUser(array $data): User
    {
        if (isset($data['nama'])) {
            $data['name'] = $data['nama'];
            unset($data['nama']);
        }
        $data['email'] = $data['nip'].'@simpleplan.local';
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return DB::transaction(function () use ($data): User {
            return $this->userRepository->create($data);
        });
    }

    public function updateUser(User $user, array $data): User
    {
        if (isset($data['nama'])) {
            $data['name'] = $data['nama'];
            unset($data['nama']);
        }
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        return DB::transaction(function () use ($user, $data): User {
            return $this->userRepository->update($user, $data);
        });
    }

    public function toggleStatus(User $user, array $data): User
    {
        $riwayat = $user->riwayat_status_akun ?? [];

        $status = $data['status_user'];
        $alasan = $status === 'Nonaktif' ? ($data['alasan_nonaktif'] ?? null) : null;

        $riwayat[] = [
            'status' => $status,
            'alasan' => $alasan,
            'tanggal' => now()->toDateTimeString(),
        ];

        $updateData = [
            'status_user' => $status,
            'alasan_nonaktif' => $alasan,
            'riwayat_status_akun' => $riwayat,
        ];

        return $this->userRepository->toggleStatus($user, $updateData);
    }
}
