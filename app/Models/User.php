<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable {
        HasRoles::syncRoles as protected traitSyncRoles;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'nip',
        'email',
        'password',
        'avatar',
        'iam_id',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'string',
        ];
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'unit_user')
            ->withTimestamps();
    }

    public function unitKerjas(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'unit_user', 'user_id', 'unit_id')
            ->withTimestamps();
    }

    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if (! $value) {
                    return null;
                }

                if (
                    str_starts_with($value, 'http://')
                    || str_starts_with($value, 'https://')
                    || str_starts_with($value, '/storage/')
                ) {
                    return $value;
                }

                return asset('storage/'.ltrim($value, '/'));
            }
        );
    }

    /**
     * @return array<string, bool>
     */
    public function getPermissions(): array
    {
        return $this->getAllPermissions()
            ->mapWithKeys(fn ($permission): array => [$permission->name => true])
            ->all();
    }

    public function canImpersonate(): bool
    {
        return $this->hasPermissionTo('impersonate');
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->hasPermissionTo('impersonate');
    }

    public function syncRoles(...$roles): self
    {
        collect($roles)
            ->flatten()
            ->filter(fn ($role): bool => is_string($role))
            ->each(function (string $roleName): void {
                Role::firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => $this->getDefaultGuardName(),
                ]);
            });

        return $this->traitSyncRoles(...$roles);
    }
}
