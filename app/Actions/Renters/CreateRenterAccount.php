<?php

namespace App\Actions\Renters;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SRS 19 - "Owner invites renter by email/phone or links an existing account".
 *
 * Renters do not self-register (config/rental.php), so the owner creates the
 * account. A temporary password is generated and returned once so the owner can
 * pass it on; the renter can also use the normal password reset flow
 * (FR-AUTH-003). NFR-SEC-008 - the password is never audited or logged.
 */
class CreateRenterAccount
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{name: string, email?: string|null, phone?: string|null}  $data
     * @return array{0: User, 1: string} the account and its temporary password
     */
    public function handle(User $owner, array $data): array
    {
        $temporaryPassword = Str::password(12, symbols: false);

        $renter = DB::transaction(function () use ($data, $owner, $temporaryPassword) {
            $renter = User::create([
                'role' => UserRole::Renter,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => $temporaryPassword,
                'status' => UserStatus::Active,
            ]);

            $this->auditLogger->record(
                action: 'renter.account_created',
                subject: $renter,
                newValues: ['role' => $renter->role->value],
                actor: $owner,
            );

            app(Notifier::class)->send($renter, 'account.created', 'Welcome to Tenantism',
                'Your account is ready.', idempotencyKey: 'account.created:'.$renter->id);

            return $renter;
        });

        return [$renter, $temporaryPassword];
    }
}
