<?php

namespace App\Actions\Properties;

use App\Enums\PublicationStatus;
use App\Models\Property;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PropertyImageService;
use App\Services\PropertyVideoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * SRS FR-PROP-001/002/003 - create a listing with its gallery.
 *
 * NFR-MNT-001 / NFR-REL-002: the property and its images are written inside one
 * transaction so a failed upload never leaves a half-created listing.
 */
class CreateProperty
{
    public function __construct(
        private readonly PropertyImageService $images,
        private readonly PropertyVideoService $videos,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $images
     * @param  array<int, UploadedFile>  $videos
     */
    public function handle(User $owner, array $data, array $images = [], array $videos = []): Property
    {
        return DB::transaction(function () use ($owner, $data, $images, $videos) {
            $property = new Property($data);
            $property->owner_id = $owner->id;

            // BR-013 - a house created as "floor by floor" is a container from
            // the start: it holds no rent and never reaches the applicant feed.
            if (! $property->isRentable()) {
                $property->rent_amount = 0;
                $property->publication_status = PublicationStatus::Draft;
            }

            // FR-PROP-004 - publishing stamps the moment the listing went public.
            $property->published_at = $property->publication_status === PublicationStatus::Published
                ? now()
                : null;

            $property->save();

            if ($images !== []) {
                $this->images->attach($property, $images);
            }

            if ($videos !== []) {
                $this->videos->attach($property, $videos);
            }

            $this->auditLogger->record(
                action: 'property.created',
                subject: $property,
                newValues: [
                    'title' => $property->title,
                    'type' => $property->type->value,
                    'rental_mode' => $property->rental_mode->value,
                    'status' => $property->status->value,
                    'publication_status' => $property->publication_status->value,
                ],
                actor: $owner,
            );

            return $property;
        });
    }
}
