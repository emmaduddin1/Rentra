<?php

namespace App\Http\Requests\MonthlyRecord;

use App\Enums\PaymentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SRS FR-MON-003 / 9.2 - proof uploads per payment category.
 *
 * NFR-SEC-005 - extension, MIME type, and size are all checked server side.
 */
class StoreMonthlyProofRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $proofs = config('rental.uploads.proofs');

        return [
            'category' => ['required', Rule::enum(PaymentCategory::class)],
            'files' => ['required', 'array', 'min:1', 'max:'.$proofs['max_per_category']],
            'files.*' => [
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
        return ['files.*' => __('file')];
    }
}
