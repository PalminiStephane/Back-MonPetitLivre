<?php

namespace App\Controller;

use App\Service\FileUploader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/upload", name="api_upload_")
 */
class UploadController extends AbstractController
{
    private $fileUploader;

    public function __construct(FileUploader $fileUploader)
    {
        $this->fileUploader = $fileUploader;
    }

    /**
     * @Route("/photo", name="photo", methods={"POST"})
     */
    public function uploadPhoto(Request $request): Response
    {
        try {
            $debug = [];
            $debug['request_files'] = $request->files->all();
            
            $file = $request->files->get('photo');
            if (!$file) {
                $debug['error'] = 'Pas de fichier dans la requête';
                return $this->json([
                    'status' => 'error',
                    'message' => 'No file uploaded',
                    'debug' => $debug
                ], 400);
            }
    
            $debug['file_info'] = [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'temp_path' => $file->getPathname(),
                'size' => $file->getSize(),
                'error_code' => $file->getError(),
                'exists' => file_exists($file->getPathname()),
                'readable' => is_readable($file->getPathname())
            ];
    
            $this->fileUploader->validateImage($file);
            $uploadedFile = $this->fileUploader->upload($file, 'child-photo-');
    
            return $this->json([
                'status' => 'success',
                'data' => $uploadedFile,
                'debug' => $debug
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'debug' => $debug ?? [],
                'trace' => $e->getTraceAsString()
            ], 400);
        }
    }
}
