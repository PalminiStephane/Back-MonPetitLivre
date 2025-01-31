<?php

namespace App\Controller;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

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
            $endpointSecret = $this->getParameter('app.stripe_webhook_secret');

            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpointSecret
            );

            if ($event->type === 'payment_intent.succeeded') {
                $paymentIntent = $event->data->object;
                $orderId = $paymentIntent->metadata->order_id;

                $order = $this->entityManager->getRepository(Order::class)->find($orderId);
                if ($order) {
                    $order->setStatus('paid');
                    $order->setPaymentId($paymentIntent->id);
                    $this->entityManager->flush();
                }
            }

            return new Response('Webhook handled', 200);

        } catch (\Exception $e) {
            // Log l'erreur
            error_log('Stripe webhook error: ' . $e->getMessage());
            return new Response('Webhook error: ' . $e->getMessage(), 500);
        }
    }
}