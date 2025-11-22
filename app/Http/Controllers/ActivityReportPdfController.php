<?php

namespace App\Http\Controllers;

use App\Enums\OwnerType;
use App\Enums\ReportType;
use App\Models\ActivityReport;
use App\Models\Organization;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ActivityReportPdfController extends Controller
{
    /**
     * Generate and save PDF for activity report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function generate(Request $request, $id)
    {
        try {
            // Ensure DomPDF directories exist
            $this->ensureDomPdfDirectoriesExist();
            
            // Retrieve the activity report
            $activityReport = ActivityReport::findOrFail($id);

            // Check if there are associated stories
            if ($activityReport->stories()->count() === 0) {
                return redirect()->back()->with('error', __('No tickets associated with this report.'));
            }

            // Get the language preference from customer or organization
            $language = 'it'; // Default to Italian
            if ($activityReport->owner_type === OwnerType::Customer && $activityReport->customer_id) {
                $customer = User::find($activityReport->customer_id);
                if ($customer && $customer->activity_report_language) {
                    $language = $customer->activity_report_language;
                }
            } elseif ($activityReport->owner_type === OwnerType::Organization && $activityReport->organization_id) {
                $organization = Organization::find($activityReport->organization_id);
                if ($organization && $organization->activity_report_language) {
                    $language = $organization->activity_report_language;
                }
            }

            // Set the locale for PDF generation
            App::setLocale($language);

            // Generate PDF HTML
            $html = $this->generatePdfHtml($activityReport, $language);

            // Generate PDF with PHP enabled for inline scripts
            $pdf = Pdf::loadHTML($html)
                ->setPaper('a4', 'portrait')
                ->setOption('enable_php', true);

            // Generate filename: [platform_acronym]_YYYY_MM_[translated_report_type]_[owner_name].pdf
            $platformAcronym = config('orchestrator.platform_acronym', 'CSM');
            $ownerName = $activityReport->owner_name ?? 'Unknown';
            
            // Clean the platform acronym (remove special characters, spaces, etc.)
            $cleanPlatformAcronym = preg_replace('/[^a-zA-Z0-9_-]/', '_', $platformAcronym);
            $cleanPlatformAcronym = preg_replace('/_+/', '_', $cleanPlatformAcronym); // Replace multiple underscores with single
            $cleanPlatformAcronym = trim($cleanPlatformAcronym, '_'); // Remove leading/trailing underscores
            
            // Clean the owner name (remove special characters, spaces, etc.)
            $cleanOwnerName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $ownerName);
            $cleanOwnerName = preg_replace('/_+/', '_', $cleanOwnerName); // Replace multiple underscores with single
            $cleanOwnerName = trim($cleanOwnerName, '_'); // Remove leading/trailing underscores
            $cleanOwnerName = mb_substr($cleanOwnerName, 0, 50); // Limit length
            
            // Get translated report type text
            $reportTypeText = $this->getTranslatedReportTypeText($activityReport->report_type, $language);
            
            // Generate filename based on report type
            if ($activityReport->report_type === \App\Enums\ReportType::Monthly) {
                // Format month with leading zero
                $monthFormatted = str_pad($activityReport->month, 2, '0', STR_PAD_LEFT);
                $filename = $cleanPlatformAcronym . '_' . $activityReport->year . '_' . $monthFormatted . '_' . $reportTypeText . '_' . $cleanOwnerName . '.pdf';
            } else {
                $filename = $cleanPlatformAcronym . '_' . $activityReport->year . '_' . $reportTypeText . '_' . $cleanOwnerName . '.pdf';
            }

            // Ensure storage/app/public/activity-reports directory exists
            $storagePath = storage_path('app/public/activity-reports');
            if (!File::exists($storagePath)) {
                File::makeDirectory($storagePath, 0755, true);
            }

            // Delete old PDF file if it exists and has a different name
            if ($activityReport->pdf_url) {
                $oldFilename = basename(parse_url($activityReport->pdf_url, PHP_URL_PATH));
                $oldPdfPath = $storagePath . '/' . $oldFilename;
                if ($oldFilename !== $filename && File::exists($oldPdfPath)) {
                    File::delete($oldPdfPath);
                    Log::info('Deleted old PDF file', [
                        'old_filename' => $oldFilename,
                        'new_filename' => $filename,
                    ]);
                }
            }

            // Save PDF to storage
            $pdfPath = $storagePath . '/' . $filename;
            $pdf->save($pdfPath);

            // Generate public URL
            $pdfUrl = url('storage/activity-reports/' . $filename);

            // Update activity report with PDF URL
            $activityReport->pdf_url = $pdfUrl;
            $activityReport->save();

            return redirect()->back()->with('success', __('PDF generated successfully.'));
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Activity report not found for PDF generation', [
                'activity_report_id' => $id
            ]);

            return redirect()->back()->with('error', __('Activity report not found.'));
        } catch (\Exception $e) {
            Log::error('Unexpected error in PDF generation', [
                'activity_report_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', __('An error occurred while generating the PDF. Please try again later.'));
        }
    }

    /**
     * Generate HTML content for PDF.
     *
     * @param  \App\Models\ActivityReport  $activityReport
     * @param  string  $language
     * @return string
     */
    private function generatePdfHtml(ActivityReport $activityReport, string $language = 'it'): string
    {
        $logoPath = config('orchestrator.pdf_logo_path');
        $logoHtml = '';
        
        if ($logoPath && file_exists($logoPath)) {
            try {
                $logoData = base64_encode(file_get_contents($logoPath));
                $logoExtension = pathinfo($logoPath, PATHINFO_EXTENSION);
                $logoMimeType = 'image/' . ($logoExtension === 'jpg' || $logoExtension === 'jpeg' ? 'jpeg' : strtolower($logoExtension));
                $logoBase64 = 'data:' . $logoMimeType . ';base64,' . $logoData;
                $logoHtml = '<img style="width: 115px; height: auto; margin-right: 20px;" src="' . $logoBase64 . '" alt="logo">';
            } catch (\Exception $e) {
                // Continue without logo
            }
        }

        $footerText = config('orchestrator.pdf_footer', '');

        $style = '
        <style>
            @page {
                margin: 120px 50px 100px 50px;
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
                bottom: -60px;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 12px;
                color: #777;
            }
            
            .footer-pagination {
                text-align: right;
                font-size: 9px;
                color: #777;
                margin-top: 5px;
                padding-right: 20px;
            }

            .content {
                margin-top: 20px;
            }

            h1 {
                text-align: center;
                font-size: 24px;
                margin-bottom: 20px;
            }

            h2 {
                font-size: 18px;
                margin-top: 30px;
                margin-bottom: 15px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 10px;
                margin-top: 10px;
            }

            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }

            th {
                background-color: #f2f2f2;
                font-weight: bold;
            }

            tr:nth-child(even) {
                background-color: #f9f9f9;
            }

            .description {
                max-width: 200px;
                word-wrap: break-word;
            }

            .summary {
                margin-bottom: 30px;
            }

            .summary p {
                margin: 5px 0;
            }
        </style>';

        // Generate filename for pagination
        $platformAcronym = config('orchestrator.platform_acronym', 'CSM');
        $ownerNameForFilename = $activityReport->owner_name ?? 'Unknown';
        $cleanOwnerName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $ownerNameForFilename);
        $cleanOwnerName = preg_replace('/_+/', '_', $cleanOwnerName);
        $cleanOwnerName = trim($cleanOwnerName, '_');
        $cleanOwnerName = mb_substr($cleanOwnerName, 0, 50);
        $reportTypeText = $this->getTranslatedReportTypeText($activityReport->report_type, $language);
        
        if ($activityReport->report_type === \App\Enums\ReportType::Monthly) {
            $monthFormatted = str_pad($activityReport->month, 2, '0', STR_PAD_LEFT);
            $filenameForPagination = $platformAcronym . '_' . $activityReport->year . '_' . $monthFormatted . '_' . $reportTypeText . '_' . $cleanOwnerName . '.pdf';
        } else {
            $filenameForPagination = $platformAcronym . '_' . $activityReport->year . '_' . $reportTypeText . '_' . $cleanOwnerName . '.pdf';
        }
        
        $header = '<div class="header">' . $logoHtml . '</div>';
        // Footer with HTML support (not escaped) and pagination via inline PHP script
        $pageText = __('page');
        $footer = '<div class="footer"><p>' . $footerText . '</p>
            <script type="text/php">
            if (isset($pdf)) {
                $font = $fontMetrics->get_font("Helvetica", "normal");
                $fontSize = 9;
                $text = "' . htmlspecialchars($filenameForPagination) . ' : ' . htmlspecialchars($pageText) . ' {PAGE_NUM} / {PAGE_COUNT}";
                $textWidth = $fontMetrics->get_text_width($text, $font, $fontSize);
                $pageWidth = $pdf->get_width();
                $pageHeight = $pdf->get_height();
                $x = $pageWidth - $textWidth - 20; // Right aligned with 20px margin
                $y = $pageHeight - 15; // Bottom with 15px margin
                $pdf->page_text($x, $y, $text, $font, $fontSize, array(0.5, 0.5, 0.5));
            }
            </script></div>';

        // Generate summary page
        $ownerName = $activityReport->owner_name ?? '-';
        
        // Get owner type label (Customer or Organization) with translation
        $ownerTypeLabel = '';
        if ($activityReport->owner_type === \App\Enums\OwnerType::Customer) {
            $ownerTypeLabel = __('Customer');
        } elseif ($activityReport->owner_type === \App\Enums\OwnerType::Organization) {
            $ownerTypeLabel = __('Organization');
        }
        
        // Generate period with translated month name
        $period = '-';
        $periodForTitle = '-';
        if ($activityReport->report_type === \App\Enums\ReportType::Annual) {
            $period = (string) $activityReport->year;
            $periodForTitle = (string) $activityReport->year;
        } elseif ($activityReport->report_type === \App\Enums\ReportType::Monthly && $activityReport->month) {
            // Set locale for Carbon to translate month name
            $originalLocale = Carbon::getLocale();
            Carbon::setLocale($language);
            $periodDate = Carbon::createFromDate($activityReport->year, $activityReport->month);
            $monthName = $periodDate->translatedFormat('F'); // Gets translated month name
            $period = $monthName . ' ' . $activityReport->year;
            $periodForTitle = $monthName . ' / ' . $activityReport->year;
            Carbon::setLocale($originalLocale); // Restore original locale
        }
        
        $storiesCount = $activityReport->stories()->count();
        
        $summaryHtml = '
        <div class="summary">
            <h2>' . __('Report Summary') . '</h2>
            <p><strong>' . __('Owner') . ':</strong> ' . htmlspecialchars($ownerName) . ($ownerTypeLabel ? ' (' . $ownerTypeLabel . ')' : '') . '</p>
            <p><strong>' . __('Period') . ':</strong> ' . htmlspecialchars($period) . '</p>
            <p><strong>' . __('Report Type') . ':</strong> ' . ($activityReport->report_type->value === 'monthly' ? __('Monthly') : __('Annual')) . '</p>
            <p><strong>' . __('Number of Tickets') . ':</strong> ' . $storiesCount . '</p>
        </div>';

        // Generate table rows
        $tableRows = '';
        $baseUrl = config('app.url', 'http://localhost:8099');
        $stories = $activityReport->stories()->with('creator')->orderBy('created_at', 'asc')->get();
        foreach ($stories as $story) {
            $storyId = $story->id;
            $storyUrl = $baseUrl . '/resources/archived-story-showed-by-customers/' . $storyId;
            $dateFormat = $language === 'it' ? 'd/m/Y' : 'Y-m-d';
            $doneAt = $story->done_at ? $story->done_at->format($dateFormat) : '-';
            $creatorName = $story->creator ? htmlspecialchars($story->creator->name ?? '-') : '-';
            $title = htmlspecialchars($story->name ?? '-');
            $description = htmlspecialchars(strip_tags($story->customer_request ?? '-'));
            $description = mb_strimwidth($description, 0, 100, '...');

            $tableRows .= '
            <tr>
                <td><a href="' . $storyUrl . '" style="color: #2FBDA5; text-decoration: none; font-weight: bold;">#' . $storyId . '</a></td>
                <td>' . $doneAt . '</td>
                <td>' . $creatorName . '</td>
                <td>' . $title . '</td>
                <td class="description">' . $description . '</td>
            </tr>';
        }

        $html = '
        <html>
        <head>
            ' . $style . '
            <meta charset="utf-8">
        </head>
        <body>
            ' . $header . '
            ' . $footer . '
            <h1>' . htmlspecialchars($platformAcronym) . ' - ' . __('Activity Report') . ' - ' . htmlspecialchars($periodForTitle) . '</h1>
            <p style="text-align: center; font-size: 14px; color: #666; margin-top: -10px; margin-bottom: 20px;">' . __('Owner') . ': ' . htmlspecialchars($ownerName) . '</p>
            ' . $summaryHtml . '
            <h2>' . __('Tickets List') . '</h2>
            <div class="content">
                <table>
                    <thead>
                        <tr>
                            <th>' . __('ID') . '</th>
                            <th>' . __('Done At') . '</th>
                            <th>' . __('Creator') . '</th>
                            <th>' . __('Title') . '</th>
                            <th>' . __('Request') . '</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $tableRows . '
                    </tbody>
                </table>
            </div>
        </body>
        </html>';

        return $html;
    }

    /**
     * Get translated report type name for filename.
     *
     * @param  string  $language
     * @param  string  $type  'monthly' or 'annual'
     * @return string
     */
    private function getTranslatedReportTypeName(string $language, string $type): string
    {
        // Set locale temporarily to get translation
        $originalLocale = App::getLocale();
        App::setLocale($language);
        
        $translations = [
            'it' => [
                'monthly' => 'Relazione_attivita_mensile',
                'annual' => 'Relazione_attivita_annuale',
            ],
            'en' => [
                'monthly' => 'Activity_monthly_report',
                'annual' => 'Activity_annual_report',
            ],
            'fr' => [
                'monthly' => 'Rapport_activite_mensuel',
                'annual' => 'Rapport_activite_annuel',
            ],
            'es' => [
                'monthly' => 'Informe_actividad_mensual',
                'annual' => 'Informe_actividad_anual',
            ],
            'de' => [
                'monthly' => 'Aktivitaetsbericht_monatlich',
                'annual' => 'Aktivitaetsbericht_jaehrlich',
            ],
        ];
        
        // Restore original locale
        App::setLocale($originalLocale);
        
        // Return translation or fallback to English
        return $translations[$language][$type] ?? $translations['en'][$type] ?? 'Activity_report';
    }

    /**
     * Get translated report type text for filename.
     *
     * @param  ReportType  $reportType
     * @param  string  $language
     * @return string
     */
    private function getTranslatedReportTypeText(\App\Enums\ReportType $reportType, string $language): string
    {
        $translations = [
            'it' => [
                'monthly' => 'Report_mensile_attivita',
                'annual' => 'Report_annuale_attivita',
            ],
            'en' => [
                'monthly' => 'Monthly_activity_report',
                'annual' => 'Annual_activity_report',
            ],
            'fr' => [
                'monthly' => 'Rapport_activite_mensuel',
                'annual' => 'Rapport_activite_annuel',
            ],
            'es' => [
                'monthly' => 'Informe_actividad_mensual',
                'annual' => 'Informe_actividad_anual',
            ],
            'de' => [
                'monthly' => 'Aktivitaetsbericht_monatlich',
                'annual' => 'Aktivitaetsbericht_jaehrlich',
            ],
        ];
        
        $type = $reportType === \App\Enums\ReportType::Monthly ? 'monthly' : 'annual';
        
        // Return translation or fallback to English
        return $translations[$language][$type] ?? $translations['en'][$type] ?? 'Activity_report';
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

