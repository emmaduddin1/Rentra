<?php

namespace App\Services;

use App\Models\MaintenanceAttachment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SRS FR-MNT-002 - optional images and documents on a maintenance request.
 *
 * These can show the inside of someone's home, so they follow the proof rules
 * rather than the property-gallery rules: private disk, authorised download
 * only (NFR-SEC-007).
 */
class MaintenanceAttachmentService
{
    public function disk(): string
    {
        return config('rental.uploads.proofs.disk');
    }

    public function maxPerRequest(): int
    {
        return (int) config('rental.uploads.maintenance.max_per_request');
    }

    public function remainingSlots(MaintenanceRequest $request): int
    {
        return max(0, $this->maxPerRequest() - $request->attachments()->count());
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, MaintenanceAttachment>
     */
    public function attach(MaintenanceRequest $request, array $files, User $uploader): Collection
    {
        $files = array_values(array_filter($files));

        if ($files === []) {
            return collect();
        }

        if (count($files) > $this->remainingSlots($request)) {
            throw ValidationException::withMessages([
                'attachments' => __('A request may hold at most :max files.', ['max' => $this->maxPerRequest()]),
            ]);
        }

        $disk = $this->disk();
        $stored = [];

        try {
            foreach ($files as $file) {
                $stored[] = [
                    'path' => $file->store("maintenance/{$request->id}", $disk),
                    'file' => $file,
                ];
            }

            return DB::transaction(function () use ($request, $stored, $uploader) {
                $created = collect();

                foreach ($stored as $item) {
                    /** @var UploadedFile $file */
                    $file = $item['file'];

                    $created->push($request->attachments()->create([
                        'disk' => $this->disk(),
                        'file_path' => $item['path'],
                        'mime_type' => $file->getMimeType(),
                        'original_name' => $file->getClientOriginalName(),
                        'size_bytes' => $file->getSize(),
                        'uploaded_by' => $uploader->id,
                    ]));
                }

                $request->unsetRelation('attachments');

                return $created;
            });
        } catch (Throwable $e) {
            foreach ($stored as $item) {
                Storage::disk($disk)->delete($item['path']);
            }

            throw $e;
        }
    }
}
