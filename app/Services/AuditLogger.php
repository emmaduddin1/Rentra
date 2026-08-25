<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * SRS BR-008 / 13 - central place to record sensitive actions.
 *
 * NFR-SEC-008: never pass passwords, tokens, or other secrets in the value
 * arrays. Use `Model::getOriginal()`/`getChanges()` to capture before/after.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        string $action,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?User $actor = null,
    ): AuditLog {
        $actor ??= Auth::user();

        return AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'old_values' => $this->scrub($oldValues),
            'new_values' => $this->scrub($newValues),
            'reason' => $reason,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255) ?: null,
        ]);
    }

    /**
     * Record the before/after state of a model that has just been saved.
     */
    public function recordChanges(string $action, Model $subject, ?string $reason = null, ?User $actor = null): AuditLog
    {
        $changes = $subject->getChanges();

        return $this->record(
            action: $action,
            subject: $subject,
            oldValues: array_intersect_key($subject->getOriginal(), $changes),
            newValues: $changes,
            reason: $reason,
            actor: $actor,
        );
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    protected function scrub(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $secret = ['password', 'password_confirmation', 'remember_token', 'token', 'api_token'];

        return array_diff_key($values, array_flip($secret));
    }
}
