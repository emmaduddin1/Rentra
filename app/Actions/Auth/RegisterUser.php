<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * SRS NFR-MNT-001 - registration lives in an action so the future API can
 * reuse it instead of duplicating controller logic.
 */
class RegisterUser
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{role: string, name: string, email?: string|null, phone?: string|null, password: string}  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'role' => UserRole::from($data['role']),
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'status' => UserStatus::Active,
            ]);

            $this->auditLogger->record(
                action: 'user.registered',
                subject: $user,
                newValues: ['role' => $user->role->value],
                actor: $user,
            );

            return $user;
        });
    }
}
