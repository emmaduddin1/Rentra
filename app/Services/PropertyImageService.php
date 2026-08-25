<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SRS FR-PROP-003 - multiple gallery images with exactly one primary image.
 *
 * NFR-SEC-005: the form request validates extension, MIME type and size; this
 * service owns placement, ordering, and the primary-image invariant so the
 * future API reuses the same rules (NFR-MNT-001).
 */
class PropertyImageService
{
    public function disk(): string
    {
        return config('rental.uploads.images.disk');
    }

    public function maxPerProperty(): int
    {
        return (int) config('rental.uploads.images.max_per_property');
    }

    public function minPerProperty(): int
    {
        return (int) config('rental.uploads.images.min_per_property');
    }

    public function remainingSlots(Property $property): int
    {
        return max(0, $this->maxPerProperty() - $property->images()->count());
    }

    /**
     * Store uploaded files and attach them to the property in upload order.
     *
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, PropertyImage>
     */
    public function attach(Property $property, array $files): Collection
    {
        $files = array_values(array_filter($files));

        if ($files === []) {
            return collect();
        }

        if (count($files) > $this->remainingSlots($property)) {
            throw ValidationException::withMessages([
                'images' => __('A property may hold at most :max images.', ['max' => $this->maxPerProperty()]),
            ]);
        }

        $disk = $this->disk();
        $stored = [];

        try {
            foreach ($files as $file) {
                $stored[] = [
                    'path' => $file->store('property-images', $disk),
                    'file' => $file,
                ];
            }

            return DB::transaction(function () use ($property, $stored) {
                $sortOrder = (int) $property->images()->max('sort_order');
                $hasPrimary = $property->images()->where('is_primary', true)->exists();

                $created = collect();

                foreach ($stored as $item) {
                    /** @var UploadedFile $file */
                    $file = $item['file'];

                    $image = $property->images()->create([
                        'disk' => $this->disk(),
                        'file_path' => $item['path'],
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                        'sort_order' => ++$sortOrder,
                        // The first image a property ever receives is its primary one.
                        'is_primary' => ! $hasPrimary && $created->isEmpty(),
                    ]);

                    $created->push($image);
                }

                $property->unsetRelation('images')->unsetRelation('primaryImage');

                return $created;
            });
        } catch (Throwable $e) {
            // Never leave orphaned files behind when the write fails.
            foreach ($stored as $item) {
                Storage::disk($disk)->delete($item['path']);
            }

            throw $e;
        }
    }

    /**
     * Remove an image and promote another one when the primary is deleted.
     *
     * FR-PROP-003 - the gallery minimum holds here too, or an owner could empty
     * a listing one image at a time past a rule the form enforces.
     */
    public function delete(PropertyImage $image): void
    {
        $property = $image->property;

        if ($property !== null && $property->images()->count() - 1 < $this->minPerProperty()) {
            throw ValidationException::withMessages([
                'images' => __('A listing needs at least :min photos. Upload a replacement before removing this one.', [
                    'min' => $this->minPerProperty(),
                ]),
            ]);
        }

        $wasPrimary = $image->is_primary;
        $disk = $image->disk;
        $path = $image->file_path;

        DB::transaction(function () use ($image, $property, $wasPrimary) {
            $image->delete();

            if ($wasPrimary && $property !== null) {
                $next = $property->images()->first();
                $next?->forceFill(['is_primary' => true])->save();
            }
        });

        Storage::disk($disk)->delete($path);

        $property?->unsetRelation('images')->unsetRelation('primaryImage');
    }

    /**
     * FR-PROP-003 - exactly one image is primary at a time.
     */
    public function makePrimary(PropertyImage $image): void
    {
        DB::transaction(function () use ($image) {
            PropertyImage::where('property_id', $image->property_id)
                ->where('id', '!=', $image->id)
                ->update(['is_primary' => false]);

            $image->forceFill(['is_primary' => true])->save();
        });

        $image->property?->unsetRelation('images')->unsetRelation('primaryImage');
    }
}
