<?php

use App\Enums\UserRole;

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | SRS 19 - "PKR configurable at application level". All money values are
    | stored as DECIMAL(14,2); this only controls presentation.
    |
    */

    'currency' => [
        'code' => env('RENTAL_CURRENCY', 'PKR'),
        'symbol' => env('RENTAL_CURRENCY_SYMBOL', 'Rs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    |
    | SRS 19 - self-registration is open to applicants; owner and renter
    | onboarding rules are configurable. Renters are normally created by an
    | owner (Phase 3), so they are not self-registerable by default.
    |
    */

    'registration' => [
        'allowed_roles' => [
            UserRole::Applicant->value,
            UserRole::Owner->value,
        ],
        'default_role' => UserRole::Applicant->value,
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | SRS 9.2 - allowed types and maximum size must be configurable, and
    | server-side validation is mandatory. Sizes are in kilobytes to match
    | Laravel's "max" validation rule.
    |
    */

    'uploads' => [
        'images' => [
            'disk' => env('RENTAL_PUBLIC_DISK', 'public'),
            'mimes' => ['jpeg', 'jpg', 'png', 'webp'],
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_kb' => (int) env('RENTAL_IMAGE_MAX_KB', 4096),
            // FR-PROP-003 - a listing carries a real gallery, never one token
            // photo, and never so many that the page becomes a slideshow.
            'min_per_property' => (int) env('RENTAL_IMAGES_MIN_PER_PROPERTY', 3),
            'max_per_property' => (int) env('RENTAL_IMAGES_PER_PROPERTY', 12),
        ],

        // FR-PROP-003 - a walkthrough video, kept beside the gallery but in its
        // own table and its own size budget: video files dwarf photos.
        'videos' => [
            'disk' => env('RENTAL_PUBLIC_DISK', 'public'),
            'mimes' => ['mp4', 'mov', 'webm'],
            'mime_types' => ['video/mp4', 'video/quicktime', 'video/webm'],
            // 200 MB. PHP's upload_max_filesize and post_max_size have to allow
            // at least this much, or the request never reaches Laravel.
            'max_kb' => (int) env('RENTAL_VIDEO_MAX_KB', 204800),
            'max_per_property' => (int) env('RENTAL_VIDEOS_PER_PROPERTY', 3),
        ],

        // SRS 10.3 / 17 - proof files are private and served through
        // authorised download routes only.
        'proofs' => [
            'disk' => env('RENTAL_PRIVATE_DISK', 'local'),
            'mimes' => ['jpeg', 'jpg', 'png', 'webp', 'pdf'],
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
            'max_kb' => (int) env('RENTAL_PROOF_MAX_KB', 8192),
            'max_per_category' => (int) env('RENTAL_PROOFS_PER_CATEGORY', 5),
        ],

        // FR-MNT-002 - issue photos can show the inside of a home, so they use
        // the private disk and the authorised download route too.
        'maintenance' => [
            'max_per_request' => (int) env('RENTAL_MAINTENANCE_FILES', 6),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Listings
    |--------------------------------------------------------------------------
    */

    'listings' => [
        'per_page' => 12,
        // SRS 19 - hide full owner contact until the applicant is logged in.
        'require_login_for_contact' => true,
    ],

    'pagination' => [
        'per_page' => 15,
    ],

];
