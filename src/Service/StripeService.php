<?php

namespace App\Service;

use App\Entity\Order;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class StripeService
{
    private $privateKey;

    public function __construct(string $stripeSecretKey)
    {
        $this->privateKey = $stripeSecretKey;
        Stripe::setApiKey($this->privateKey);
    }

    public function createPaymentIntent(Order $order): array
    {
        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $order->getTotalAmount() * 100, // Stripe utilise les centimes
                'currency' => 'eur',
                'metadata' => [
                    'order_id' => $order->getId(),
                    'book_id' => $order->getBook()->getId()
                ]
            ]);

            return [
                'clientSecret' => $paymentIntent->client_secret,
                'paymentIntentId' => $paymentIntent->id,
            ];
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la création du paiement: ' . $e->getMessage());
        }
    }
}