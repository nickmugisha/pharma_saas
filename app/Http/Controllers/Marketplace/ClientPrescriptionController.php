<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\ClientPrescription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientPrescriptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $this->client($request);
        $data = $request->validate([
            'prescriber_name' => ['nullable', 'string', 'max:191'],
            'prescriber_facility' => ['nullable', 'string', 'max:191'],
            'issued_at' => ['nullable', 'date', 'before_or_equal:today'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'document' => [
                'required',
                File::types(['pdf', 'jpg', 'jpeg', 'png', 'webp'])->max(10 * 1024),
            ],
        ]);

        $file = $request->file('document');
        $path = $file->store("client-prescriptions/{$user->id}", 'local');

        ClientPrescription::create([
            'user_id' => $user->id,
            'status' => 'submitted',
            'prescriber_name' => $data['prescriber_name'] ?? null,
            'prescriber_facility' => $data['prescriber_facility'] ?? null,
            'issued_at' => $data['issued_at'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Prescription uploaded securely.');
    }

    public function download(
        Request $request,
        ClientPrescription $prescription,
    ): StreamedResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active, 403);

        $clientOwns = $user->hasRole('client')
            && $prescription->user_id === $user->id;

        $pharmacyMayReview = $user->can(
            'marketplace.prescriptions.review',
        )
            && filled($user->pharmacy_id)
            && $prescription->orderItems()
                ->whereHas(
                    'order',
                    function ($query) use ($user): void {
                        $query->where(
                            'pharmacy_id',
                            $user->pharmacy_id,
                        );

                        if (! $user->hasRole('pharmacy_owner')) {
                            $query->where(
                                'pharmacy_branch_id',
                                $user->pharmacy_branch_id,
                            );
                        }
                    },
                )
                ->exists();

        abort_unless($clientOwns || $pharmacyMayReview, 404);
        abort_unless(Storage::disk($prescription->disk)->exists($prescription->path), 404);

        return Storage::disk($prescription->disk)->download(
            $prescription->path,
            $prescription->original_name,
        );
    }

    private function client(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active && $user->hasRole('client'), 403);
        return $user;
    }
}
