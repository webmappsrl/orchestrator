<?php

namespace App\Http\Controllers;

use App\Enums\OwnerType;
use App\Models\ActivityReport;
use App\Models\Organization;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
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
            $html = $this->generatePdfHtml($activityReport);

            // Generate PDF
            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

            // Generate filename: [APP_NAME]_[name]_Activity_monthly_report_YYYY_MM.pdf
            $appName = config('app.name', 'Orchestrator');
            $ownerName = $activityReport->owner_name ?? 'Unknown';
            
            // Clean the owner name (remove special characters, spaces, etc.)
            $cleanOwnerName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $ownerName);
            $cleanOwnerName = preg_replace('/_+/', '_', $cleanOwnerName); // Replace multiple underscores with single
            $cleanOwnerName = trim($cleanOwnerName, '_'); // Remove leading/trailing underscores
            $cleanOwnerName = mb_substr($cleanOwnerName, 0, 50); // Limit length
            
            // Format month with leading zero
            $monthFormatted = str_pad($activityReport->month, 2, '0', STR_PAD_LEFT);
            
            $filename = $appName . '_' . $cleanOwnerName . '_Activity_monthly_report_' . $activityReport->year . '_' . $monthFormatted . '.pdf';

            // Ensure storage/app/public/activity-reports directory exists
            $storagePath = storage_path('app/public/activity-reports');
            if (!File::exists($storagePath)) {
                File::makeDirectory($storagePath, 0755, true);
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
     * @return string
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
        $stories = $activityReport->stories()->orderBy('created_at', 'asc')->get();
        foreach ($stories as $story) {
            $createdAt = $story->created_at ? $story->created_at->format('d/m/Y') : '-';
            $releasedAt = $story->released_at ? $story->released_at->format('d/m/Y') : '-';
            $doneAt = $story->done_at ? $story->done_at->format('d/m/Y') : '-';
            $title = htmlspecialchars($story->name ?? '-');
            $description = htmlspecialchars(strip_tags($story->customer_request ?? '-'));
            $description = mb_strimwidth($description, 0, 100, '...');

            $tableRows .= '
            <tr>
                <td>' . $createdAt . '</td>
                <td>' . $releasedAt . '</td>
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
                            <th>' . __('Created At') . '</th>
                            <th>' . __('Released At') . '</th>
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

