<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;
use App\Service\PdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PdfController extends AbstractController
{
    /**
     * @Route("/api/pdf/book/{id}", name="api_pdf_book", methods={"GET"})
     */
    public function generateBookPdf(int $id, BookRepository $bookRepository, PdfGenerator $pdfGenerator): Response
    {
        $book = $bookRepository->find($id);
        if (!$book) {
            return $this->json(['error' => 'Book not found'], 404);
        }

        // Génère le binaire PDF
        $pdfContent = $pdfGenerator->generateBookPdf($book);

        // Retourne le PDF directement
        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="book-'.$book->getId().'.pdf"'
        ]);
    }
}
