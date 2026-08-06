@php
    $mode = $mode ?? 'otc';
    $details = match($mode) {
        'prescription_required' => ['Prescription required', 'badge-prescription'],
        'pharmacist_review' => ['Pharmacist review', 'badge-review'],
        'in_store_only' => ['In-store only', 'badge-store'],
        default => ['No prescription needed', 'badge-otc'],
    };
@endphp
<span class="medicine-mode-badge {{ $details[1] }}">{{ $details[0] }}</span>
