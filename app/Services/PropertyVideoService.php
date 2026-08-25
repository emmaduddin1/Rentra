<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyVideo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SRS FR-PROP-003 / NFR-SEC-005 - walkthrough videos for a listing.
 *
 * The mirror of PropertyImageService, minus the primary-file invariant: videos
 * are simply ordered. A floor is a property, so a house and each of its floors
 * keep their own videos with no special casing here.
 */
class PropertyVideoService
{
    public function disk(): string
    {
        return config('rental.uploads.videos.disk');
    }

    public function maxPerProperty(): int
    {
        return (int) config('rental.uploads.videos.max_per_property');
    }

    public function maxMegabytes(): float
    {
        return round((int) config('rental.uploads.videos.max_kb') / 1024, 1);
    }

    public function remainingSlots(Property $property): int
    {
        return max(0, $this->maxPerProperty() - $property->videos()->count());
    }

    /**
     * Store uploaded clips and attach them to the property in upload order.
     *
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, PropertyVideo>
     */
    public function attach(Property $property, array $files): Collection
    {
        $files = array_values(array_filter($files));

        if ($files === []) {
            return collect();
        }

        if (count($files) > $this->remainingSlots($property)) {
            throw ValidationException::withMessages([
                'videos' => __('A listing may hold at most :max videos.', ['max' => $this->maxPerProperty()]),
            ]);
        }

        $disk = $this->disk();
        $stored = [];

        try {
            foreach ($files as $file) {
                $stored[] = [
                    'path' => $file->store('property-videos', $disk),
                    'file' => $file,
                ];
            }

            return DB::transaction(function () use ($property, $stored) {
                $sortOrder = (int) $property->videos()->max('sort_order');
                $created = collect();

                foreach ($stored as $item) {
                    /** @var UploadedFile $file */
                    $file = $item['file'];

                    $created->push($property->videos()->create([
                        'disk' => $this->disk(),
                        'file_path' => $item['path'],
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                        'sort_order' => ++$sortOrder,
                    ]));
                }

                $property->unsetRelation('videos');

                return $created;
            });
        } catch (Throwable $e) {
            // NFR-REL-002 - a failed write never leaves a 200 MB orphan on disk.
            foreach ($stored as $item) {
                Storage::disk($disk)->delete($item['path']);
            }

            throw $e;
        }
    }

    public function delete(PropertyVideo $video): void
    {
        $property = $video->property;
        $disk = $video->disk;
        $path = $video->file_path;

        $video->delete();

        Storage::disk($disk)->delete($path);

        $property?->unsetRelation('videos');
    }
}
