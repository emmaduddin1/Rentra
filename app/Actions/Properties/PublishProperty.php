<?php

namespace App\Actions\Properties;

use App\Enums\PublicationStatus;
use App\Models\Property;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS FR-PROP-004 / BR-001 - publication is the owner's decision; visibility to
 * applicants additionally requires the property to be Available.
 *
 * Publishing an occupied property is allowed: the listing simply stays out of
 * the feed until the tenancy ends, instead of forcing the owner to republish.
 *
 * BR-013 - a house that is let floor by floor is published through its floors,
 * so it cannot be published on its own.
 */
class PublishProperty
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Property $property, bool $publish): Property
    {
        if ($publish && ! $property->isRentable()) {
            throw ValidationException::withMessages([
                'publication_status' => __('This house is rented floor by floor. Publish each floor instead.'),
            ]);
        }

        $target = $publish ? PublicationStatus::Published : PublicationStatus::Draft;

        if ($property->publication_status === $target) {
            return $property;
        }

        return DB::transaction(function () use ($property, $target, $publish) {
            $from = $property->publication_status;

            $property->publication_status = $target;
            $property->published_at = $publish ? ($property->published_at ?? now()) : null;
            $property->save();

            // BR-008 - publication is an audited action.
            $this->auditLogger->record(
                action: $publish ? 'property.published' : 'property.unpublished',
                subject: $property,
                oldValues: ['publication_status' => $from->value],
                newValues: ['publication_status' => $target->value],
            );

            return $property;
        });
    }
}
