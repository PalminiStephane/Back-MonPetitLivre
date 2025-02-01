<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploader
{
    private $targetDirectory;
    private $slugger;

    public function __construct(string $targetDirectory, SluggerInterface $slugger)
    {
        $this->targetDirectory = $targetDirectory;
        $this->slugger = $slugger;
    
        // S'assurer que le dossier de destination existe
        if (!is_dir($this->targetDirectory)) {
            mkdir($this->targetDirectory, 0777, true);
        }
    
        // S'assurer qu'il est accessible en écriture
        if (!is_writable($this->targetDirectory)) {
            throw new \Exception("Le dossier de destination n'est pas accessible en écriture");
        }
    }
    
    /**
     * Upload un fichier, renvoie info sous forme de tableau
     */
    public function upload(UploadedFile $file, string $prefix = ''): array
    {
        try {
            // Debug avant le traitement
            if (!file_exists($file->getPathname())) {
                throw new \Exception("Fichier temporaire introuvable: " . $file->getPathname());
            }
            
            // Vérification du dossier de destination
            if (!is_dir($this->targetDirectory)) {
                mkdir($this->targetDirectory, 0777, true);
            }
            
            if (!is_writable($this->targetDirectory)) {
                throw new \Exception("Le dossier de destination n'est pas accessible en écriture: " . $this->targetDirectory);
            }
    
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $fileName = $prefix . $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
    
            // Essayer de copier le fichier plutôt que de le déplacer
            if (!copy($file->getPathname(), $this->targetDirectory . '/' . $fileName)) {
                throw new \Exception("Impossible de copier le fichier vers sa destination");
            }
    
            return [
                'fileName' => $fileName,
                'filePath' => '/uploads/' . $fileName,
                'mimeType' => $file->getMimeType(),
            ];
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de l\'upload du fichier: ' . $e->getMessage());
        }
    }
        public function validateImage(UploadedFile $file): void
{
    // Log complet pour le debug
    dump([
        'mimeType' => $file->getMimeType(),
        'clientMimeType' => $file->getClientMimeType(),
        'extension' => $file->guessExtension(),
        'size' => $file->getSize(),
        'error' => $file->getError()
    ]);
    
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'image/gif', 'image/x-gif'];
    
    if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
        throw new \Exception('Format de fichier non autorisé. Utilisez JPG, PNG ou WebP. MIME Type détecté : ' . $file->getMimeType());
    }
    
    if ($file->getSize() > 5 * 1024 * 1024) { // 5 MB
        throw new \Exception('Le fichier est trop volumineux. Taille maximale: 5MB');
    }
}

    public function deleteFile(string $fileName): void
    {
        $filePath = $this->targetDirectory . '/' . $fileName;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
