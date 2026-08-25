<?php

namespace App\Http\Requests\Maintenance;

use App\Enums\MaintenancePriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * SRS FR-MNT-002 - title, description, optional images, and priority.
 */
class StoreMaintenanceRequestRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $proofs = config('rental.uploads.proofs');

        return [
            'tenancy_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'priority' => ['required', Rule::enum(MaintenancePriority::class)],

            'attachments' => ['nullable', 'array', 'max:'.config('rental.uploads.maintenance.max_per_request')],
            'attachments.*' => [
                'file',
                'mimes:'.implode(',', $proofs['mimes']),
                'mimetypes:'.implode(',', $proofs['mime_types']),
                'max:'.$proofs['max_kb'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tenancy_id' => __('property'),
            'attachments.*' => __('file'),
        ];
    }

    /**
     * @return array{title: string, description: string, priority: string}
     */
    public function requestData(): array
    {
        return [
            'title' => (string) $this->validated('title'),
            'description' => (string) $this->validated('description'),
            'priority' => (string) $this->validated('priority'),
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function uploadedAttachments(): array
    {
        return $this->file('attachments') ?? [];
    }
}
