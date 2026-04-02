<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'identifier',
        'email',
        'password_hash',
        'government_id',
        'phone',
        'government_id_encrypted',
        'phone_encrypted',
        'role',
        'site_id',
        'department_id',
        'is_banned',
        'muted_until',
        'locked_until',
        'failed_attempts',
    ];

    protected $hidden = [
        'password_hash',
        'government_id_encrypted',
        'phone_encrypted',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['site_id', 'created_at']);
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            if (! Schema::hasTable('user_roles') || ! Schema::hasTable('roles')) {
                return;
            }

            $role = Role::query()->where('name', $user->role)->first();

            if (! $role) {
                return;
            }

            UserRole::query()->firstOrCreate([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'site_id' => $user->site_id,
            ], [
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_banned' => 'boolean',
            'muted_until' => 'datetime',
            'locked_until' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function setGovernmentIdAttribute(?string $value): void
    {
        $this->attributes['government_id_encrypted'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getGovernmentIdAttribute(): ?string
    {
        $encrypted = $this->attributes['government_id_encrypted'] ?? null;
        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone_encrypted'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getPhoneAttribute(): ?string
    {
        $encrypted = $this->attributes['phone_encrypted'] ?? null;
        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }
}
