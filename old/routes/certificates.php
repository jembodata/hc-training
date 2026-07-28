<?php

use App\Http\Controllers\Certificates\IssuedCertificateFileController;
use App\Support\Auth\Permissions;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('/certificates', 'training.issued-certificates')
        ->middleware(
            'permission:'.Permissions::VIEW_CERTIFICATE
        )
        ->name('certificates.index');

    Route::get(
        '/certificates/{issuedCertificate}/preview',
        [IssuedCertificateFileController::class, 'preview']
    )
        ->middleware(
            'permission:'.Permissions::DOWNLOAD_CERTIFICATE
        )
        ->name('certificates.preview');

    Route::get(
        '/certificates/{issuedCertificate}/download',
        [IssuedCertificateFileController::class, 'download']
    )
        ->middleware(
            'permission:'.Permissions::DOWNLOAD_CERTIFICATE
        )
        ->name('certificates.download');
});
