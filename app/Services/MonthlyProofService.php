<?php

namespace App\Services;

use App\Enums\PaymentCategory;
use App\Models\MonthlyProof;
use App\Models\MonthlyRecord;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SRS FR-MON-003 - proof files attached to a payment category.
 *
 * NFR-SEC-007 / SRS 17 - proofs live on the private disk and are never served
 * directly; MonthlyProofDownloadController is the only way out, and it runs the
 * policy first. File names are randomised so a path is never guessable.
 */
class MonthlyProofService
{
    public function disk(): string
    {
        return config('rental.uploads.proofs.disk');
    }

    public function maxPerCategory(): int
    {
        return (int) config('rental.uploads.proofs.max_per_category');
    }

    public function remainingSlots(MonthlyRecord $record, PaymentCategory $category): int
    {
        $used = $record->proofs()->where('category', $category->value)->count();

        return max(0, $this->maxPerCategory() - $used);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, MonthlyProof>
     */
    public function attach(MonthlyRecord $record, PaymentCategory $category, array $files, User $uploader): Collection
    {
        $files = array_values(array_filter($files));

        if ($files === []) {
            return collect();
        }

        // BR-004 / BR-006 - a pending or frozen record takes no new evidence.
        if (! $record->isEditableByRenter()) {
            throw ValidationException::withMessages([
                'files' => $record->isFrozen()
                    ? __('This record is frozen and cannot take new proofs.')
                    : __('This record is awaiting approval and cannot take new proofs.'),
            ]);
        }

        if (count($files) > $this->remainingSlots($record, $category)) {
            throw ValidationException::withMessages([
                'files' => __('You can attach at most :max files per category.', ['max' => $this->maxPerCategory()]),
            ]);
        }

        $disk = $this->disk();
        $stored = [];

        try {
            foreach ($files as $file) {
                $stored[] = [
                    // store() gives the file a random name on the private disk.
                    'path' => $file->store("monthly-proofs/{$record->id}", $disk),
                    'file' => $file,
                ];
            }

            return DB::transaction(function () use ($record, $category, $stored, $uploader) {
                $created = collect();

                foreach ($stored as $item) {
                    /** @var UploadedFile $file */
                    $file = $item['file'];

                    $created->push($record->proofs()->create([
                        'category' => $category,
                        'disk' => $this->disk(),
                        'file_path' => $item['path'],
                        'mime_type' => $file->getMimeType(),
                        'original_name' => $file->getClientOriginalName(),
                        'size_bytes' => $file->getSize(),
                        'uploaded_by' => $uploader->id,
                    ]));
                }

                $record->unsetRelation('proofs');

                return $created;
            });
        } catch (Throwable $e) {
            // SRS 12 - a failed upload never leaves half-written evidence behind.
            foreach ($stored as $item) {
                Storage::disk($disk)->delete($item['path']);
            }

            throw $e;
        }
    }

    public function delete(MonthlyProof $proof): void
    {
        $record = $proof->monthlyRecord;

        if ($record !== null && ! $record->isEditableByRenter()) {
            throw ValidationException::withMessages([
                'files' => __('This record can no longer be changed.'),
            ]);
        }

        $disk = $proof->disk;
        $path = $proof->file_path;

        $proof->delete();

        Storage::disk($disk)->delete($path);

        $record?->unsetRelation('proofs');
    }
}
