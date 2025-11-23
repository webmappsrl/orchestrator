<?php

namespace App\Services;

use App\Models\Documentation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DocumentationPdfService
{
    /**
     * Generate PDF for a documentation and save it to storage.
     *
     * @param Documentation $documentation
     * @return string|null The PDF URL or null if generation failed
     */
    public function generatePdf(Documentation $documentation): ?string
    {
        try {
            // Ensure DomPDF directories exist
            $this->ensureDomPdfDirectoriesExist();

            // Generate PDF HTML
            $html = $this->generatePdfHtml($documentation);

            // Generate PDF
            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

            // Generate filename: acronimo_doc_[nome o titolo della documentazione].pdf
            $platformAcronym = config('orchestrator.platform_acronym', 'CSM');
            $cleanAcronym = preg_replace('/[^a-zA-Z0-9_-]/', '_', $platformAcronym);
            $cleanAcronym = preg_replace('/_+/', '_', $cleanAcronym);
            $cleanAcronym = trim($cleanAcronym, '_');

            // Clean documentation name/title for filename
            $docName = $documentation->name ?? 'documentation_' . $documentation->id;
            $cleanDocName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $docName);
            $cleanDocName = preg_replace('/_+/', '_', $cleanDocName);
            $cleanDocName = trim($cleanDocName, '_');
            $cleanDocName = mb_substr($cleanDocName, 0, 100); // Limit length

            $filename = $cleanAcronym . '_doc_' . $cleanDocName . '.pdf';

            // Ensure storage/app/public/documentations directory exists
            $storagePath = storage_path('app/public/documentations');
            if (!File::exists($storagePath)) {
                File::makeDirectory($storagePath, 0755, true);
            }

            // Delete old PDF file if it exists
            if ($documentation->pdf_url) {
                $oldFilename = basename(parse_url($documentation->pdf_url, PHP_URL_PATH));
                $oldPdfPath = $storagePath . '/' . $oldFilename;
                if ($oldFilename !== $filename && File::exists($oldPdfPath)) {
                    File::delete($oldPdfPath);
                    Log::info('Deleted old PDF file (different filename)', [
                        'documentation_id' => $documentation->id,
                        'old_filename' => $oldFilename,
                        'new_filename' => $filename,
                    ]);
                }
            }

            // Always delete PDF if it exists with the same filename (to ensure fresh generation)
            $pdfPath = $storagePath . '/' . $filename;
            if (File::exists($pdfPath)) {
                File::delete($pdfPath);
                Log::info('Deleted existing PDF file (same filename) for regeneration', [
                    'documentation_id' => $documentation->id,
                    'filename' => $filename,
                ]);
            }

            // Save PDF to storage
            $pdf->save($pdfPath);

            // Generate public URL
            $pdfUrl = url('storage/documentations/' . $filename);

            Log::info('Documentation PDF generated successfully', [
                'documentation_id' => $documentation->id,
                'pdf_url' => $pdfUrl,
            ]);

            return $pdfUrl;

        } catch (\Exception $e) {
            Log::error('Failed to generate documentation PDF', [
                'documentation_id' => $documentation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Generate HTML content for PDF.
     */
    private function generatePdfHtml(Documentation $documentation): string
    {
        $description = $documentation->description;
        $description = mb_convert_encoding($description, 'UTF-8', 'UTF-8');
        $description = str_replace('<img', '<img style="max-width: 100%; height: auto;max-height:300px"', $description);
        // Add inline style for <pre> blocks to handle line length
        $description = str_replace('<pre><code>', '<pre style="white-space: pre-wrap; word-wrap: break-word;"><code>', $description);
        $description = '<div style="padding: 15px 0;">' . $description . '</div>';

        $title = $documentation->name;

        // Get logo path from configuration
        $logoPath = config('orchestrator.pdf_logo_path');
        
        // Generate logo HTML (base64 if exists, otherwise empty)
        $logoHtml = '';
        if ($logoPath) {
            try {
                // Check if path is absolute or relative to storage
                if (file_exists($logoPath)) {
                    $logoData = base64_encode(file_get_contents($logoPath));
                    $logoExtension = pathinfo($logoPath, PATHINFO_EXTENSION);
                    $logoMimeType = 'image/' . ($logoExtension === 'jpg' || $logoExtension === 'jpeg' ? 'jpeg' : strtolower($logoExtension));
                    $logoBase64 = 'data:' . $logoMimeType . ';base64,' . $logoData;
                    $logoHtml = '<img style="width: 115px; height: auto; margin-right: 20px;" src="' . $logoBase64 . '" alt="logo">';
                } else {
                    Log::warning('PDF Logo path does not exist', [
                        'path' => $logoPath,
                        'documentation_id' => $documentation->id
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error loading PDF logo', [
                    'path' => $logoPath,
                    'error' => $e->getMessage(),
                    'documentation_id' => $documentation->id
                ]);
                // Continue without logo
            }
        }

        $style = '
        <style>
            @page {
                margin: 120px 50px 80px 50px;
            }

            .header {
                display: flex;
                justify-content: flex-end;
                align-items: flex-start;
                position: fixed;
                top: -80px;
                left: 0;
                right: 0;
                width: 100%;
                text-align: right;
            }
        
            .footer {
                position: fixed;
                bottom: -50px;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 12px;
                color: #777;
            }

            .content {
                margin-top: 20px;
                margin-bottom: 20px;
            }

            h1 {
                text-align: center;
            }
        </style>';

        $header = '
        <div class="header">
                ' . $logoHtml . '
        </div>';

        // Get footer from configuration
        $footerText = config('orchestrator.pdf_footer');
        
        $footer = '
        <div class="footer">
            <p>' . $footerText . '</p>
        </div>';

        $html = '
        <html>
        <head>
            ' . $style . '
            <meta charset="utf-8">
        </head>
        <body>
            ' . $header . '
            ' . $footer . '
            <h1>' . htmlspecialchars($title) . '</h1>
            <div class="content">' . $description . '</div>
        </body>
        </html>';

        return $html;
    }

    /**
     * Ensure DomPDF directories exist and are writable.
     */
    private function ensureDomPdfDirectoriesExist()
    {
        $directories = [
            storage_path('app/dompdf/fonts'),
            storage_path('app/dompdf/tmp'),
        ];

        foreach ($directories as $directory) {
            if (!File::exists($directory)) {
                try {
                    File::makeDirectory($directory, 0755, true);
                    Log::info('Created DomPDF directory', ['directory' => $directory]);
                } catch (\Exception $e) {
                    Log::error('Failed to create DomPDF directory', [
                        'directory' => $directory,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Check write permissions
            if (!is_writable($directory)) {
                try {
                    chmod($directory, 0755);
                    Log::info('Fixed DomPDF directory permissions', ['directory' => $directory]);
                } catch (\Exception $e) {
                    Log::warning('Could not fix DomPDF directory permissions', [
                        'directory' => $directory,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }
}

