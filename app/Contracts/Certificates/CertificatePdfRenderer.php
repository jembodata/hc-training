<?php

namespace App\Contracts\Certificates;

use App\Data\Certificates\CertificatePdfDocument;
use App\Models\IssuedCertificate;

interface CertificatePdfRenderer
{
    public function render(
        IssuedCertificate $certificate
    ): CertificatePdfDocument;
}
