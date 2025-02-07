<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class PdfGenerator
{
   private Environment $twig;
   private Options $options;
   private LoggerInterface $logger;

   public function __construct(Environment $twig, LoggerInterface $pdfLogger)
   {
       ini_set('memory_limit', '512M');
       
       $this->twig = $twig;
       $this->logger = $pdfLogger;
       
       $this->options = new Options();
       $this->options->set('isHtml5ParserEnabled', true);
       $this->options->set('isPhpEnabled', true);
       $this->options->set('defaultFont', 'DejaVu Sans');
       $this->options->set('isRemoteEnabled', true);
       $this->options->set('defaultMediaType', 'print');
       $this->options->set('isFontSubsettingEnabled', true);
       $this->options->set('chroot', [
           realpath(__DIR__ . '/../../public'),
           sys_get_temp_dir()
       ]);
       $this->options->set('tempDir', sys_get_temp_dir());
       $this->options->set('dpi', 96);
       
       $this->logger->info('PdfGenerator initialisé');
   }

   public function generatePdfResponse(array $data, string $filename = 'book.pdf'): Response
   {
       try {
           $this->logger->info('Début de la génération du PDF', ['filename' => $filename]);
           
           // Créer une copie des données pour éviter de modifier l'original
           $processedData = $data;
           
           // Log des images à traiter
           $this->logger->debug('Images à traiter', [
               'cover' => $processedData['bookContent']['coverImageUrl'] ?? 'no cover',
               'nb_pages' => count($processedData['bookContent']['pages'] ?? []),
               'paths' => array_map(function($page) {
                   return basename($page['imageUrl'] ?? 'no image');
               }, $processedData['bookContent']['pages'] ?? [])
           ]);

           // Set un timeout plus long
           set_time_limit(300);
           
           $dompdf = new Dompdf($this->options);
   
           // Configuration du contexte pour les requêtes HTTP
           $context = stream_context_create([
               'ssl' => [
                   'verify_peer' => false,
                   'verify_peer_name' => false,
               ],
               'http' => [
                   'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36',
                   'timeout' => 30,
                   'follow_location' => true
               ]
           ]);
   
           // Traitement des images
           if (!empty($processedData['bookContent'])) {
               // Image de couverture
               if (!empty($processedData['bookContent']['coverImageUrl'])) {
                   try {
                       $imagePath = __DIR__ . '/../../public' . $processedData['bookContent']['coverImageUrl'];
                       $this->logger->debug('Tentative de chargement de l\'image de couverture', [
                           'path' => $imagePath,
                           'exists' => file_exists($imagePath),
                           'readable' => is_readable($imagePath)
                       ]);
                       
                       if (file_exists($imagePath) && is_readable($imagePath)) {
                           $imageData = file_get_contents($imagePath);
                           if ($imageData !== false) {
                               $processedData['bookContent']['coverImageUrl'] = 'data:image/png;base64,' . base64_encode($imageData);
                               $this->logger->debug('Image de couverture chargée avec succès');
                           }
                       } else {
                           $this->logger->warning('Image de couverture inaccessible', [
                               'path' => $imagePath,
                               'exists' => file_exists($imagePath),
                               'readable' => is_readable($imagePath)
                           ]);
                       }
                   } catch (\Exception $e) {
                       $this->logger->warning('Erreur lors du chargement de l\'image de couverture', [
                           'error' => $e->getMessage(),
                           'path' => $imagePath ?? 'unknown'
                       ]);
                   }
               }
   
               // Images des pages
               if (!empty($processedData['bookContent']['pages'])) {
                   foreach ($processedData['bookContent']['pages'] as $key => $page) {
                       if (!empty($page['imageUrl'])) {
                           try {
                               $imagePath = __DIR__ . '/../../public' . $page['imageUrl'];
                               $this->logger->debug('Tentative de chargement de l\'image de la page ' . $key, [
                                   'path' => $imagePath,
                                   'exists' => file_exists($imagePath),
                                   'readable' => is_readable($imagePath)
                               ]);
                               
                               if (file_exists($imagePath) && is_readable($imagePath)) {
                                   $imageData = file_get_contents($imagePath);
                                   if ($imageData !== false) {
                                       $processedData['bookContent']['pages'][$key]['imageUrl'] = 'data:image/png;base64,' . base64_encode($imageData);
                                       $this->logger->debug('Image de la page ' . $key . ' chargée avec succès');
                                   }
                               } else {
                                   $this->logger->warning('Image de la page ' . $key . ' inaccessible', [
                                       'path' => $imagePath,
                                       'exists' => file_exists($imagePath),
                                       'readable' => is_readable($imagePath)
                                   ]);
                               }
                           } catch (\Exception $e) {
                               $this->logger->warning('Erreur lors du chargement de l\'image de la page ' . $key, [
                                   'error' => $e->getMessage(),
                                   'path' => $imagePath ?? 'unknown'
                               ]);
                           }
                       }
                   }
               }
           }

           // Log de la structure finale avant le rendu
           $this->logger->debug('Structure des données avant rendu', [
               'bookContent' => array_keys($processedData['bookContent']),
               'has_cover' => isset($processedData['bookContent']['coverImageUrl']),
               'first_page' => isset($processedData['bookContent']['pages'][0]) ? array_keys($processedData['bookContent']['pages'][0]) : 'no pages'
           ]);

           // Générer le HTML
           $html = $this->twig->render('pdf/book.html.twig', $processedData);
           
           // Log du HTML généré (sans le contenu complet)
           $this->logger->debug('HTML généré', [
               'length' => strlen($html),
               'has_base64_images' => (bool) strpos($html, 'data:image/png;base64')
           ]);
           
           // Charger le HTML
           $dompdf->loadHtml($html);
           $dompdf->setPaper('A4', 'portrait');
           
           // Rendu du PDF
           $dompdf->render();
   
           return new Response(
               $dompdf->output(),
               Response::HTTP_OK,
               [
                   'Content-Type' => 'application/pdf',
                   'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
                   'Cache-Control' => 'private, max-age=0, must-revalidate',
               ]
           );
   
       } catch (\Exception $e) {
           $this->logger->error('Erreur lors de la génération du PDF', [
               'error' => $e->getMessage(),
               'trace' => $e->getTraceAsString()
           ]);
           throw new \RuntimeException('Erreur lors de la génération du PDF: ' . $e->getMessage());
       }
   }
}