<?php
// src/Service/PdfGenerator.php
namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PdfGenerator
{
    private Environment $twig;
    private Dompdf $dompdf;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
        
        // Configuration des options de Dompdf
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        // Si votre template utilise des images hébergées en externe, activez l'accès distant :
        $options->setIsRemoteEnabled(true);

        $this->dompdf = new Dompdf($options);
    }

    /**
     * Génère et retourne le PDF en affichage inline ou en téléchargement.
     *
     * @param string $template Le chemin du template Twig (ex: 'pdf/book.html.twig')
     * @param array $data Les données à injecter dans le template
     * @param string $outputMode 'stream' pour afficher dans le navigateur ou 'download' pour forcer le téléchargement
     * @param string $filename Le nom du fichier PDF généré
     *
     * @return void|string Retourne la réponse PDF (pour 'stream' ou 'download')
     */
    public function generatePdf(string $template, array $data, string $outputMode = 'stream', string $filename = 'document.pdf')
    {
        // Rendu du template Twig en HTML
        $html = $this->twig->render($template, $data);

        // Chargement du HTML dans Dompdf
        $this->dompdf->loadHtml($html);

        // Configuration du format de la page
        $this->dompdf->setPaper('A4', 'portrait');

        // Rendu du PDF
        $this->dompdf->render();

        // Selon le mode de sortie : 
        // - 'stream' : affichage inline dans le navigateur
        // - 'download' : téléchargement forcé
        if ($outputMode === 'download') {
            return $this->dompdf->stream($filename, ["Attachment" => true]);
        }

        return $this->dompdf->stream($filename, ["Attachment" => false]);
    }

    /**
     * Retourne le contenu du PDF sous forme de chaîne de caractères (pour sauvegarder le fichier par exemple)
     *
     * @param string $template
     * @param array $data
     * @return string
     */
    public function getPdfOutput(string $template, array $data): string
    {
        $html = $this->twig->render($template, $data);
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();

        return $this->dompdf->output();
    }
}
