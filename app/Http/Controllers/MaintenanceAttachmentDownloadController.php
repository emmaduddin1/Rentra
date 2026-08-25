<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SRS NFR-SEC-007 - maintenance attachments live on the private disk and are
 * only reachable through this route, after MaintenanceRequestPolicy::view.
 */
class MaintenanceAttachmentDownloadController extends Controller
{
    public function __invoke(MaintenanceAttachment $attachment): StreamedResponse
    {
        $request = $attachment->maintenanceRequest;

        abort_if($request === null, 404);

        Gate::authorize('view', $request);

        $disk = Storage::disk($attachment->disk);

        abort_unless($disk->exists($attachment->file_path), 404);

        return $disk->response(
            $attachment->file_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?? 'application/octet-stream'],
        );
    }
}
