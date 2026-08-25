<?php

namespace App\Http\Requests\Property;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SRS FR-PROP-003 - adding walkthrough videos to an existing listing.
 *
 * NFR-SEC-005 - extension, MIME type, size and per-listing count are all
 * checked server side. A 200 MB cap only holds if PHP's upload_max_filesize
 * and post_max_size allow at least as much.
 */
class StorePropertyVideosRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $videos = config('rental.uploads.videos');
        $property = $this->route('property');

        $remaining = $property instanceof Property
            ? max(0, (int) $videos['max_per_property'] - $property->videos()->count())
            : (int) $videos['max_per_property'];

        return [
            'videos' => ['required', 'array', 'min:1', 'max:'.$remaining],
            'videos.*' => [
                'file',
                'mimes:'.implode(',', $videos['mimes']),
                'mimetypes:'.implode(',', $videos['mime_types']),
                'max:'.$videos['max_kb'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $videos = config('rental.uploads.videos');

        return [
            'videos.max' => __('A listing may hold at most :max videos.', ['max' => $videos['max_per_property']]),
            'videos.*.max' => __('Each video must be :size MB or smaller.', [
                'size' => round((int) $videos['max_kb'] / 1024, 1),
            ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['videos.*' => __('video')];
    }
}
