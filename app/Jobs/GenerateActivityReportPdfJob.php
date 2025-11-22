<?php

namespace App\Jobs;

use App\Enums\OwnerType;
use App\Enums\ReportType;
use App\Models\ActivityReport;
use App\Models\Organization;
use App\Models\Story;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GenerateActivityReportPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public OwnerType $ownerType;
    public ?int $customerId;
    public ?int $organizationId;
    public int $year;
    public int $month;

    /**
     * Create a new job instance.
     */
    public function __construct(
        OwnerType $ownerType,
        ?int $customerId,
        ?int $organizationId,
        int $year,
        int $month
    ) {
        $this->ownerType = $ownerType;
        $this->customerId = $customerId;
        $this->organizationId = $organizationId;
        $this->year = $year;
        $this->month = $month;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Ensure DomPDF directories exist
            $this->ensureDomPdfDirectoriesExist();

            // Create or find activity report
            $activityReport = ActivityReport::firstOrCreate(
                [
                    'owner_type' => $this->ownerType->value,
                    'customer_id' => $this->customerId,
                    'organization_id' => $this->organizationId,
                    'report_type' => ReportType::Monthly->value,
                    'year' => $this->year,
                    'month' => $this->month,
                ],
                [
                    'pdf_url' => null,
                ]
            );

            // Sync stories (this will be triggered automatically via observer)
            $activityReport->syncStories();

            // Check if there are associated stories
            if ($activityReport->stories()->count() === 0) {
                Log::info('No tickets associated with activity report, skipping PDF generation', [
                    'activity_report_id' => $activityReport->id,
                    'owner_type' => $this->ownerType->value,
                    'customer_id' => $this->customerId,
                    'organization_id' => $this->organizationId,
                    'year' => $this->year,
                    'month' => $this->month,
                ]);
                return; // Skip PDF generation if no tickets
            }

            // Get the language preference from customer or organization
            // Reload activity report with relationships to ensure we have fresh data
            $activityReport->refresh();
            $activityReport->load(['customer', 'organization']);
            
            $language = 'it'; // Default to Italian
            
            Log::info('Activity report PDF generation - checking language', [
                'activity_report_id' => $activityReport->id,
                'owner_type' => $this->ownerType->value,
                'customer_id' => $this->customerId,
                'organization_id' => $this->organizationId,
                'activity_report_customer_id' => $activityReport->customer_id,
                'activity_report_organization_id' => $activityReport->organization_id,
                'customer_loaded' => $activityReport->customer ? 'yes' : 'no',
                'organization_loaded' => $activityReport->organization ? 'yes' : 'no',
            ]);
            
            if ($this->ownerType === OwnerType::Customer && $this->customerId) {
                // Reload customer to ensure we have fresh data
                $customer = User::find($this->customerId);
                if ($customer && $customer->activity_report_language) {
                    $language = $customer->activity_report_language;
                }
            } elseif ($this->ownerType === OwnerType::Organization && $this->organizationId) {
                // Reload organization to ensure we have fresh data
                $organization = Organization::find($this->organizationId);
                if ($organization && $organization->activity_report_language) {
                    $language = $organization->activity_report_language;
                }
            }
            
            Log::info('Activity report PDF generation language', [
                'activity_report_id' => $activityReport->id,
                'owner_type' => $this->ownerType->value,
                'customer_id' => $this->customerId,
                'organization_id' => $this->organizationId,
                'language' => $language,
            ]);

            // Set the locale for PDF generation
            App::setLocale($language);

            // Generate PDF HTML
            $html = $this->generatePdfHtml($activityReport);

            // Generate PDF
            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

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
            if ($activityReport->report_type === ReportType::Monthly) {
                // Format month with leading zero
                $monthFormatted = str_pad($this->month, 2, '0', STR_PAD_LEFT);
                $filename = $cleanPlatformAcronym . '_' . $this->year . '_' . $monthFormatted . '_' . $reportTypeText . '_' . $cleanOwnerName . '.pdf';
            } else {
                $filename = $cleanPlatformAcronym . '_' . $this->year . '_' . $reportTypeText . '_' . $cleanOwnerName . '.pdf';
            }
            
            Log::info('Activity report PDF filename generation', [
                'activity_report_id' => $activityReport->id,
                'language' => $language,
                'report_type' => $activityReport->report_type->value,
                'filename' => $filename,
            ]);
            
            Log::info('Activity report PDF filename final', [
                'activity_report_id' => $activityReport->id,
                'filename' => $filename,
            ]);

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

            Log::info('Activity report PDF generated successfully', [
                'activity_report_id' => $activityReport->id,
                'pdf_url' => $pdfUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate activity report PDF', [
                'owner_type' => $this->ownerType->value,
                'customer_id' => $this->customerId,
                'organization_id' => $this->organizationId,
                'year' => $this->year,
                'month' => $this->month,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate HTML content for PDF (same logic as controller).
     */
    private function generatePdfHtml(ActivityReport $activityReport): string
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

        $header = '<div class="header">' . $logoHtml . '</div>';
        $footer = '<div class="footer"><p>' . htmlspecialchars($footerText) . '</p></div>';

        // Generate summary page
        $ownerName = $activityReport->owner_name ?? '-';
        $period = $activityReport->period ?? '-';
        $storiesCount = $activityReport->stories()->count();
        
        $summaryHtml = '
        <div class="summary">
            <h2>' . __('Report Summary') . '</h2>
            <p><strong>' . __('Owner') . ':</strong> ' . htmlspecialchars($ownerName) . '</p>
            <p><strong>' . __('Period') . ':</strong> ' . htmlspecialchars($period) . '</p>
            <p><strong>' . __('Report Type') . ':</strong> ' . __('' . ucfirst($activityReport->report_type->value)) . '</p>
            <p><strong>' . __('Number of Tickets') . ':</strong> ' . $storiesCount . '</p>
        </div>';

        // Generate table rows
        $tableRows = '';
        $baseUrl = config('app.url', 'http://localhost:8099');
        $stories = $activityReport->stories()->orderBy('created_at', 'asc')->get();
        foreach ($stories as $story) {
            $storyId = $story->id;
            $storyUrl = $baseUrl . '/resources/archived-story-showed-by-customers/' . $storyId;
            $doneAt = $story->done_at ? $story->done_at->format('d/m/Y') : '-';
            $title = htmlspecialchars($story->name ?? '-');
            $description = htmlspecialchars(strip_tags($story->customer_request ?? '-'));
            $description = mb_strimwidth($description, 0, 100, '...');

            $tableRows .= '
            <tr>
                <td><a href="' . $storyUrl . '" style="color: #2FBDA5; text-decoration: none; font-weight: bold;">#' . $storyId . '</a></td>
                <td>' . $doneAt . '</td>
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
            <h1>' . __('Activity Report') . '</h1>
            ' . $summaryHtml . '
            <h2>' . __('Tickets List') . '</h2>
            <div class="content">
                <table>
                    <thead>
                        <tr>
                            <th>' . __('ID') . '</th>
                            <th>' . __('Done At') . '</th>
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
    private function getTranslatedReportTypeText(ReportType $reportType, string $language): string
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
        
        $type = $reportType === ReportType::Monthly ? 'monthly' : 'annual';
        
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

