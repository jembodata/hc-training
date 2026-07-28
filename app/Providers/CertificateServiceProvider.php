<?php

namespace App\Providers;

use App\Contracts\Certificates\CertificatePdfRenderer;
use App\Services\Certificates\GotenbergCertificatePdfRenderer;
use Illuminate\Support\ServiceProvider;

class CertificateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CertificatePdfRenderer::class,
            GotenbergCertificatePdfRenderer::class
        );
    }
}
