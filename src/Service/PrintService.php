<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PrintService
{
    private $client;
    private $apiKey;

    public function __construct(HttpClientInterface $client, string $apiKey)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
    }

    public function createPrintOrder(Order $order): array
    {
        $response = $this->client->request('POST', 'https://api.gelato.com/v1/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'orderType' => 'print',
                'orderReference' => $order->getId(),
                'shippingAddress' => $order->getShippingAdress(),
                'items' => [
                    [
                        'productUid' => 'BOOK_A4_HARDCOVER',
                        'quantity' => 1,
                        'files' => [
                            [
                                'url' => 'url_to_pdf',
                                'type' => 'interior'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        return $response->toArray();
    }
}