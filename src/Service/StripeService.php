<?php

namespace App\Service;

use App\Entity\Order;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeService
{
    private $privateKey;
    private $webhookSecret;

    public function __construct(string $stripeSecretKey, string $webhookSecret)
    {
        $this->privateKey = $stripeSecretKey;
        $this->webhookSecret = $webhookSecret;
        Stripe::setApiKey($this->privateKey);
    }

    public function createCheckoutSession(Order $order, string $successUrl, string $cancelUrl): Session
    {
        return Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $order->getTotalAmount() * 100,
                    'product_data' => [
                        'name' => $order->getBook()->getTitle(),
                        'description' => 'Format: ' . $order->getFormat()
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'order_id' => $order->getId()
            ],
        ]);
    }
}