<?php

namespace App\Http\Requests\Maintenance;

use App\Enums\MaintenanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SRS FR-MNT-003 - Pending, In Progress, Completed, or Cancelled. Which of
 * those is legal from the current status is decided by the action (SRS 12).
 */
class UpdateMaintenanceStatusRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(MaintenanceStatus::class)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
