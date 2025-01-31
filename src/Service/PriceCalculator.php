<?php

namespace App\Service;

class PriceCalculator
{
    private const PRICES = [
        'pdf' => 9.99,
        'print' => 24.99,
        'premium_print' => 34.99
    ];

    public function calculatePrice(string $format): float
    {
        if (!isset(self::PRICES[$format])) {
            throw new \InvalidArgumentException('Invalid format');
        }

        return self::PRICES[$format];
    }

    public function getAvailableFormats(): array
    {
        return array_keys(self::PRICES);
    }
}