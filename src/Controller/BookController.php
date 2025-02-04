<?php

namespace App\Controller;

use App\Entity\Book;
use App\Service\PdfGenerator;
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
     * @Route("/{id}/pdf", name="book_pdf")
     */
    public function generatePdf(Book $book, PdfGenerator $pdfGenerator): Response
    {   
        // Vérifiez que le livre appartient bien à l'utilisateur connecté
        if ($book->getUser() !== $this->getUser()) {
            return $this->json([
                'status' => 'error',
                'message' => 'Access denied'
            ], 403);
        }
        
        // Préparez les données à passer au template.
        // Ici, nous passons l'objet book directement.
        $data = [
            'book' => $book
        ];

        // Générez et affichez le PDF dans le navigateur (mode stream inline)
        return new Response(
            $pdfGenerator->generatePdf('pdf/book.html.twig', $data, 'stream', 'livre_personnalise.pdf'),
            200,
            ['Content-Type' => 'application/pdf']
        );
    }
}