<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Documentation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class DocumentationPdfController extends Controller
{
    public function download(Request $request, $id)
    {
        try {
            // Recupera la documentazione dal database
            $documentation = Documentation::findOrFail($id);

            // Check if PDF exists
            if (!$documentation->pdf_url) {
                Log::warning('PDF not found for documentation, redirecting to generation', [
                    'documentation_id' => $id
                ]);

                return redirect()->back()->with('error', __('PDF not yet generated. Please wait a moment and try again.'));
            }

            // Extract filename from URL
            $filename = basename(parse_url($documentation->pdf_url, PHP_URL_PATH));
            $pdfPath = storage_path('app/public/documentations/' . $filename);

            // Check if file exists
            if (!File::exists($pdfPath)) {
                Log::error('PDF file not found on disk', [
                    'documentation_id' => $id,
                    'pdf_url' => $documentation->pdf_url,
                    'pdf_path' => $pdfPath
                ]);

                return redirect()->back()->with('error', __('PDF file not found. Please regenerate the PDF.'));
            }

            // Return PDF for download
            return response()->download($pdfPath, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Documentation not found for PDF export', [
                'documentation_id' => $id
            ]);

            return redirect()->back()->with('error', __('Documentation not found.'));
        } catch (\Exception $e) {
            Log::error('Unexpected error in PDF export', [
                'documentation_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', __('An error occurred while downloading the PDF. Please try again later.'));
        }
    }

}
