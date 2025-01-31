<?php

namespace App\Controller;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class StripeWebhookController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/api/webhook/stripe", name="stripe_webhook", methods={"POST"})
     */
    public function stripeWebhook(Request $request): Response
    {
        try {
            $payload = $request->getContent();
            $sig_header = $request->headers->get('stripe-signature');
            $event = Webhook::constructEvent(
                $payload, 
                $sig_header, 
                $this->getParameter('app.stripe_webhook_secret')
            );

            switch ($event->type) {
                case 'checkout.session.completed':
                    $session = $event->data->object;
                    $orderId = $session->metadata->order_id;
                    $order = $this->entityManager->getRepository(Order::class)->find($orderId);
                    
                    if ($order) {
                        $order->setStatus('paid');
                        $order->setPaymentId($session->payment_intent);
                        $this->entityManager->flush();
                    }
                    break;

                case 'checkout.session.expired':
                    $session = $event->data->object;
                    $orderId = $session->metadata->order_id;
                    $order = $this->entityManager->getRepository(Order::class)->find($orderId);
                    
                    if ($order) {
                        $order->setStatus('expired');
                        $this->entityManager->flush();
                    }
                    break;

                case 'checkout.session.failed':
                    $session = $event->data->object;
                    $orderId = $session->metadata->order_id;
                    $order = $this->entityManager->getRepository(Order::class)->find($orderId);
                    
                    if ($order) {
                        $order->setStatus('failed');
                        $this->entityManager->flush();
                    }
                    break;
            }

            return new Response('Webhook handled', 200);

        } catch (\Exception $e) {
            // Log l'erreur pour le débogage
            error_log('Stripe webhook error: ' . $e->getMessage());
            return new Response('Webhook error: ' . $e->getMessage(), 400);
        }
    }
}