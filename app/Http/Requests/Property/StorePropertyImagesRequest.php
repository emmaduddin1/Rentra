<?php

namespace App\Http\Requests\Property;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SRS FR-PROP-003 - adding images to an existing listing.
 *
 * NFR-SEC-005 - server-side validation is mandatory even though the form also
 * previews the selection client side (SRS 9.2).
 */
class StorePropertyImagesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $images = config('rental.uploads.images');

        return [
            'images' => ['required', 'array', 'min:1', 'max:'.$images['max_per_property']],
            'images.*' => [
                'image',
                'mimes:'.implode(',', $images['mimes']),
                'mimetypes:'.implode(',', $images['mime_types']),
                'max:'.$images['max_kb'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['images.*' => __('image')];
    }
}
