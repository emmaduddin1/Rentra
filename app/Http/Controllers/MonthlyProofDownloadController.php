<?php

namespace App\Http\Controllers;

use App\Models\MonthlyProof;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SRS NFR-SEC-007 / 9.2 - the only route out of the private proof disk.
 *
 * The policy runs first, so a proof is readable by the renter who uploaded it
 * and the owner of the property it belongs to, and by nobody else (BR-010).
 */
class MonthlyProofDownloadController extends Controller
{
    public function __invoke(MonthlyProof $proof): StreamedResponse
    {
        $record = $proof->monthlyRecord;

        abort_if($record === null, 404);

        Gate::authorize('downloadProof', $record);

        $disk = Storage::disk($proof->disk);

        abort_unless($disk->exists($proof->file_path), 404);

        // Inline so images and PDFs preview in the browser rather than
        // downloading; the file itself never becomes publicly reachable.
        return $disk->response(
            $proof->file_path,
            $proof->original_name,
            ['Content-Type' => $proof->mime_type ?? 'application/octet-stream'],
        );
    }
}
