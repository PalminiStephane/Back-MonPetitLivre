<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChatGPTGenerator
{
    private $httpClient;
    private $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $apiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
    }

    public function generateStory(string $childName, int $childAge, string $theme): array
    {
        // Générer l'histoire complète
        $mainStoryPrompt = $this->createMainStoryPrompt($childName, $childAge, $theme);
        $mainStory = $this->callChatGPT($mainStoryPrompt);

        // Diviser l'histoire en pages (environ 13 pages)
        $pages = $this->splitIntoPages($mainStory);

        // Générer la conclusion
        $conclusionPrompt = $this->createConclusionPrompt($childName, $theme);
        $conclusion = $this->callChatGPT($conclusionPrompt);

        return [
            'pages' => $pages,
            'conclusion' => $conclusion
        ];
    }

    private function createMainStoryPrompt(string $childName, int $childAge, string $theme): string
    {
        return "Écris une histoire pour enfant en 13 parties courtes et simples. 
                L'histoire est pour {$childName}, qui a {$childAge} ans.
                Le thème est: {$theme}.
                L'histoire doit être adaptée à son âge, captivante et éducative.
                Chaque partie doit faire environ 3-4 phrases.
                L'histoire doit avoir un début, un développement et une fin.";
    }

    private function createConclusionPrompt(string $childName, string $theme): string
    {
        return "Écris une courte conclusion positive et personnalisée pour {$childName} 
                en lien avec le thème {$theme}. La conclusion doit être encourageante 
                et faire 2-3 phrases maximum.";
    }

    private function callChatGPT(string $prompt): string
    {
        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
            ]
        ]);

        $data = $response->toArray();
        return $data['choices'][0]['message']['content'];
    }

    private function splitIntoPages(string $story): array
    {
        // Diviser l'histoire en paragraphes
        $parts = explode("\n\n", $story);
        
        // S'assurer qu'on a 13 parties
        $parts = array_slice($parts, 0, 13);
        
        return array_map(function($text) {
            return ['text' => trim($text)];
        }, $parts);
    }
}