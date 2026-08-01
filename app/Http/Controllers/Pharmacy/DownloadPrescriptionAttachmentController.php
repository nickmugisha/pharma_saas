<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\PrescriptionAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadPrescriptionAttachmentController extends Controller
{
    public function __invoke(
        PrescriptionAttachment $attachment,
    ): StreamedResponse {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        abort_unless(
            $user->is_active
                && filled($user->pharmacy_id)
                && $user->can('prescriptions.view'),
            403,
        );

        $attachment->loadMissing('prescription');

        abort_unless(
            (int) $attachment->prescription->pharmacy_id
                === (int) $user->pharmacy_id,
            404,
        );

        abort_unless(
            in_array(
                $attachment->disk,
                ['local', 'public'],
                true,
            ),
            404,
        );

        $disk = Storage::disk($attachment->disk);

        abort_unless(
            $disk->exists($attachment->path),
            404,
        );

        return $disk->download(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' =>
                    $attachment->mime_type
                    ?: 'application/octet-stream',
            ],
        );
    }
}