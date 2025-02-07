<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class DallEGenerator
{
    private $httpClient;
    private $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $apiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
    }

    public function generateCoverImage(string $title, string $theme): string
    {
        $prompt = sprintf(
            "Create a children's book cover illustration for a story titled '%s' with a %s theme. Make it colorful and child-friendly.",
            $title,
            $theme
        );

        return $this->generateImage($prompt);
    }

    public function generatePageImage(string $pageText, string $theme): string
    {
        $prompt = sprintf(
            "Create a children's book illustration for this text: '%s'. Theme is %s. Make it colorful and child-friendly.",
            substr($pageText, 0, 500), // Limiter la longueur du texte
            $theme
        );

        return $this->generateImage($prompt);
    }

    private function generateImage(string $prompt): string
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'dall-e-3', // Spécifier le modèle
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '1024x1024',
                    'quality' => 'standard',
                ]
            ]);

            $content = $response->getContent();
            $data = json_decode($content, true);

            if (isset($data['error'])) {
                throw new \Exception('DALL-E API Error: ' . $data['error']['message']);
            }

            if (isset($data['data'][0]['url'])) {
                return $data['data'][0]['url'];
            } else {
                throw new \Exception('No image URL in response');
            }

        } catch (\Exception $e) {
            throw new \Exception('DALL-E generation failed: ' . $e->getMessage());
        }
    }
}