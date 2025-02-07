<?php

namespace App\Controller;

use App\Entity\Book;
use Psr\Log\LoggerInterface;
use App\Service\PdfGenerator;
use App\Service\BookGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/api/books", name="api_books_")
 */
class BookController extends AbstractController
{
    private $entityManager;
    private $validator;

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator)
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
    }

    /**
     * @Route("", name="create", methods={"POST"})
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Invalid JSON data'
                ], 400);
            }

            /** @var \App\Entity\User $user */
            $user = $this->getUser();

            $book = new Book();
            $book->setUser($user);
            $book->setTitle($data['title'] ?? '');
            $book->setChildName($data['child_name'] ?? '');
            $book->setChildAge($data['child_age'] ?? 0);
            $book->setTheme($data['theme'] ?? '');
            $book->setStatus('draft');

            // Validation
            $errors = $this->validator->validate($book);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $errorMessages
                ], 400);
            }

            // Sauvegarde en base de données
            $this->entityManager->persist($book);
            $this->entityManager->flush();

            return $this->json([
                'status' => 'success',
                'message' => 'Book created successfully',
                'data' => [
                    'id' => $book->getId(),
                    'title' => $book->getTitle(),
                    'child_name' => $book->getChildName(),
                    'child_age' => $book->getChildAge(),
                    'theme' => $book->getTheme(),
                    'status' => $book->getStatus()
                ]
            ], 201);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @Route("", name="list", methods={"GET"})
     */
    public function list(): JsonResponse
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $books = $user->getBooks();

            $data = [];
            foreach ($books as $book) {
                $data[] = [
                    'id' => $book->getId(),
                    'title' => $book->getTitle(),
                    'child_name' => $book->getChildName(),
                    'child_age' => $book->getChildAge(),
                    'theme' => $book->getTheme(),
                    'status' => $book->getStatus()
                ];
            }

            return $this->json([
                'status' => 'success',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @Route("/{id}", name="show", methods={"GET"})
     */
    public function show(Book $book): JsonResponse
    {
        try {
            // Vérifier que le livre appartient bien à l'utilisateur connecté
            if ($book->getUser() !== $this->getUser()) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Access denied'
                ], 403);
            }

            return $this->json([
                'status' => 'success',
                'data' => [
                    'id' => $book->getId(),
                    'title' => $book->getTitle(),
                    'child_name' => $book->getChildName(),
                    'child_age' => $book->getChildAge(),
                    'theme' => $book->getTheme(),
                    'status' => $book->getStatus(),
                    'content' => $book->getContent()
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @Route("/{id}", name="update", methods={"PUT"})
     */
    public function update(Request $request, Book $book): JsonResponse
    {
        try {
            if ($book->getUser() !== $this->getUser()) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Access denied'
                ], 403);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Invalid JSON data'
                ], 400);
            }

            $book->setTitle($data['title'] ?? $book->getTitle());
            $book->setChildName($data['child_name'] ?? $book->getChildName());
            $book->setChildAge($data['child_age'] ?? $book->getChildAge());
            $book->setTheme($data['theme'] ?? $book->getTheme());

            $errors = $this->validator->validate($book);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $errorMessages
                ], 400);
            }

            $this->entityManager->flush();

            return $this->json([
                'status' => 'success',
                'message' => 'Book updated successfully',
                'data' => [
                    'id' => $book->getId(),
                    'title' => $book->getTitle(),
                    'child_name' => $book->getChildName(),
                    'child_age' => $book->getChildAge(),
                    'theme' => $book->getTheme(),
                    'status' => $book->getStatus()
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @Route("/{id}", name="delete", methods={"DELETE"})
     */
    public function delete(Book $book): JsonResponse
    {
        try {
            if ($book->getUser() !== $this->getUser()) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Access denied'
                ], 403);
            }

            // Vérifier si le livre a des commandes
            if (!$book->getOrders()->isEmpty()) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Cannot delete a book that has orders'
                ], 400);
            }

            $this->entityManager->remove($book);
            $this->entityManager->flush();

            return $this->json([
                'status' => 'success',
                'message' => 'Book deleted successfully'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @Route("/{id}/pdf", name="api_book_pdf", methods={"GET"})
     */
    public function generatePdf(Book $book, PdfGenerator $pdfGenerator, LoggerInterface $pdfLogger): Response
    {
        try {
            // Vérifier que le livre appartient bien à l'utilisateur connecté
            if ($book->getUser() !== $this->getUser()) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Access denied'
                ], Response::HTTP_FORBIDDEN);
            }

            $pdfLogger->info('Début de la génération du PDF pour le livre', [
                'book_id' => $book->getId(),
                'title' => $book->getTitle()
            ]);

            $bookContent = $book->getContent();
            
            if (is_string($bookContent)) {
                $bookContent = json_decode($bookContent, true);
            }

            $pdfLogger->debug('Structure du contenu du livre', [
                'content_keys' => is_array($bookContent) ? array_keys($bookContent) : 'not array',
                'content_type' => gettype($bookContent),
                'sample' => is_array($bookContent) ? json_encode(array_slice($bookContent, 0, 2)) : 'not array'
            ]);

            if (!is_array($bookContent)) {
                throw new \InvalidArgumentException('Le contenu du livre doit être un tableau');
            }

            $pdfData = [
                'book' => $book,
                'bookContent' => $bookContent
            ];

            $filename = sprintf(
                '%s_%s.pdf',
                $this->slugify($book->getTitle()),
                date('Y-m-d')
            );

            return $pdfGenerator->generatePdfResponse($pdfData, $filename);

        } catch (\Exception $e) {
            $pdfLogger->error('Erreur lors de la génération du PDF', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la génération du PDF: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @Route("/generate", name="api_generate_book", methods={"POST"})
     */
    public function generateBook(Request $request, BookGenerator $bookGenerator): Response
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            $book = $bookGenerator->generateBook(
                $data['child_name'],
                $data['child_age'],
                $data['theme']
            );

            return $this->json([
                'status' => 'success',
                'data' => $book
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function slugify(string $text): string
    {
        // Remplacer les caractères non alphanumériques
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        
        // Translittération
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        
        // Supprimer les caractères indésirables
        $text = preg_replace('~[^-\w]+~', '', $text);
        
        // Supprimer les tirets en début et fin
        $text = trim($text, '-');
        
        // Convertir en minuscules
        $text = strtolower($text);
        
        // Si la chaîne est vide, retourner 'book'
        return empty($text) ? 'book' : $text;
    }
}