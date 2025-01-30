<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Book;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @Route("/api/orders", name="api_orders_")
 */
class OrderController extends AbstractController
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

            // Vérifier que le livre existe et appartient à l'utilisateur
            $book = $this->entityManager->getRepository(Book::class)->find($data['book_id'] ?? 0);
            if (!$book || $book->getUser() !== $user) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Book not found or access denied'
                ], 404);
            }

            $order = new Order();
            $order->setUser($user);
            $order->setBook($book);
            $order->setFormat($data['format'] ?? 'pdf');
            $order->setStatus('pending');
            $order->setShippingAdress($data['shipping_address'] ?? []);
            $order->setTotalAmount($data['total_amount'] ?? 0);

            // Validation
            $errors = $this->validator->validate($order);
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

            $this->entityManager->persist($order);
            $this->entityManager->flush();

            return $this->json([
                'status' => 'success',
                'message' => 'Order created successfully',
                'data' => [
                    'id' => $order->getId(),
                    'format' => $order->getFormat(),
                    'status' => $order->getStatus(),
                    'total_amount' => $order->getTotalAmount(),
                    'book' => [
                        'id' => $book->getId(),
                        'title' => $book->getTitle()
                    ]
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
            $orders = $user->getOrders();

            $data = [];
            foreach ($orders as $order) {
                $data[] = [
                    'id' => $order->getId(),
                    'format' => $order->getFormat(),
                    'status' => $order->getStatus(),
                    'total_amount' => $order->getTotalAmount(),
                    'book' => [
                        'id' => $order->getBook()->getId(),
                        'title' => $order->getBook()->getTitle()
                    ]
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
    public function show(Order $order): JsonResponse
    {
        try {
            if ($order->getUser() !== $this->getUser()) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Access denied'
                ], 403);
            }

            return $this->json([
                'status' => 'success',
                'data' => [
                    'id' => $order->getId(),
                    'format' => $order->getFormat(),
                    'status' => $order->getStatus(),
                    'total_amount' => $order->getTotalAmount(),
                    'shipping_address' => $order->getShippingAdress(),
                    'book' => [
                        'id' => $order->getBook()->getId(),
                        'title' => $order->getBook()->getTitle()
                    ]
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
    public function update(Request $request, Order $order): JsonResponse
    {
        try {
            if ($order->getUser() !== $this->getUser()) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Access denied'
                ], 403);
            }

            // Vérifier si la commande peut être modifiée
            if ($order->getStatus() !== 'pending') {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Only pending orders can be updated'
                ], 400);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Invalid JSON data'
                ], 400);
            }

            $order->setFormat($data['format'] ?? $order->getFormat());
            $order->setShippingAdress($data['shipping_address'] ?? $order->getShippingAdress());

            $errors = $this->validator->validate($order);
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
                'message' => 'Order updated successfully',
                'data' => [
                    'id' => $order->getId(),
                    'format' => $order->getFormat(),
                    'status' => $order->getStatus(),
                    'shipping_address' => $order->getShippingAdress(),
                    'total_amount' => $order->getTotalAmount()
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
    public function delete(Order $order): JsonResponse
    {
        try {
            if ($order->getUser() !== $this->getUser()) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Access denied'
                ], 403);
            }

            // Vérifier si la commande peut être supprimée
            if ($order->getStatus() !== 'pending') {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Only pending orders can be deleted'
                ], 400);
            }

            $this->entityManager->remove($order);
            $this->entityManager->flush();

            return $this->json([
                'status' => 'success',
                'message' => 'Order deleted successfully'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}