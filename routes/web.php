<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Pharmacy\DownloadPrescriptionAttachmentController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')
    ->get(
        '/pharmacy/prescription-attachments/{attachment}/download',
        DownloadPrescriptionAttachmentController::class,
    )
    ->name(
        'pharmacy.prescription-attachments.download',
    );