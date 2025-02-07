<?php
namespace App\Service;

use App\Entity\Book;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\SerializerInterface;

class BookGenerator
{
    private $chatGPTGenerator;
    private $dallEGenerator;
    private $entityManager;
    private $serializer;
    private $security;
    private $projectDir;

    public function __construct(
        ChatGPTGenerator $chatGPTGenerator,
        DallEGenerator $dallEGenerator,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        Security $security,
        string $projectDir
    ) {
        $this->chatGPTGenerator = $chatGPTGenerator;
        $this->dallEGenerator = $dallEGenerator;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->security = $security;
        $this->projectDir = $projectDir;
    }

    private function saveImage(string $imageUrl, string $filename): string
    {
        $uploadDir = $this->projectDir . '/public/uploads/book_images/';
        $imageData = file_get_contents($imageUrl);
        $filePath = $uploadDir . $filename;
        file_put_contents($filePath, $imageData);
        return '/uploads/book_images/' . $filename;
    }

    public function generateBook(string $childName, int $childAge, string $theme): array
    {
        $user = $this->security->getUser();
        if (!$user) {
            throw new \Exception('User must be authenticated to generate a book');
        }

        // Générer l'histoire et la conclusion
        $storyData = $this->chatGPTGenerator->generateStory($childName, $childAge, $theme);
        
        // Générer et sauvegarder l'image de couverture
        $title = "L'aventure de " . $childName;
        $coverImageUrl = $this->dallEGenerator->generateCoverImage($title, $theme);
        $localCoverPath = $this->saveImage(
            $coverImageUrl, 
            'cover_' . uniqid() . '.png'
        );

        // Générer et sauvegarder les images pour chaque page
        $pages = $storyData['pages'];
        foreach ($pages as $index => &$page) {
            $pageImageUrl = $this->dallEGenerator->generatePageImage($page['text'], $theme);
            $localImagePath = $this->saveImage(
                $pageImageUrl,
                'page_' . $index . '_' . uniqid() . '.png'
            );
            $page['imageUrl'] = $localImagePath;
        }

        // Préparer le contenu complet du livre
        $bookContent = [
            'title' => $title,
            'coverImageUrl' => $localCoverPath,
            'pages' => $pages,
            'conclusion' => $storyData['conclusion']
        ];

        // Créer et sauvegarder le livre
        $book = new Book();
        $book->setTitle($title);
        $book->setChildName($childName);
        $book->setChildAge($childAge);
        $book->setTheme($theme);
        $book->setContent(json_decode($this->serializer->serialize($bookContent, 'json'), true));
        $book->setStatus('completed');
        $book->setUser($user);

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        return array_merge(['id' => $book->getId()], $bookContent);
    }
}