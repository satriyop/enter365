<?php

declare(strict_types=1);

namespace App\Services\Sales\Quotation;

use App\Models\Sales\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generates downloadable quotation PDFs.
 */
class QuotationPdfService
{
    /**
     * Generate and download a quotation PDF.
     */
    public function download(Quotation $quotation): Response
    {
        $quotation->load(['contact', 'items.product']);

        $pdf = Pdf::loadView('pdf.quotation', [
            'quotation' => $quotation,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($quotation->quotation_number.'.pdf');
    }
}
