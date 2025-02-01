<?php

namespace App\Service;

use App\Entity\Book; // ou n’importe quelle entité
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PdfGenerator
{
    private $templating;

    public function __construct(Environment $templating)
    {
        $this->templating = $templating;
    }

    /**
     * Génère un PDF pour un Book, renvoie le binaire PDF
     */
    public function generateBookPdf(Book $book): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);

        $dompdf = new Dompdf($options);

        // On suppose que vous avez un template Twig "pdf/book.html.twig"
        $html = $this->templating->render('pdf/book.html.twig', [
            'book' => $book
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Renvoie le PDF binaire
        return $dompdf->output();
    }
}
