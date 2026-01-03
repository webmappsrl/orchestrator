<?php

namespace App\Http\Controllers;

use App\Services\ChangelogService;
use Illuminate\Http\Request;

class ChangelogController extends Controller
{
    protected $changelogService;

    public function __construct(ChangelogService $changelogService)
    {
        $this->changelogService = $changelogService;
    }

    /**
     * Show the main changelog page with minor releases menu
     */
    public function index()
    {
        $minorReleases = $this->changelogService->getMinorReleases();

        return view('changelog.index', [
            'minorReleases' => $minorReleases,
        ]);
    }

    /**
     * Show a specific minor release page with all its patches
     */
    public function showMinorRelease(string $minorVersion)
    {
        $patches = $this->changelogService->getPatchesForMinorRelease($minorVersion);
        $minorReleases = $this->changelogService->getMinorReleases();

        if (empty($patches)) {
            abort(404, 'Minor release not found');
        }

        // Load content for each patch
        $patchesWithContent = [];
        foreach ($patches as $patch) {
            $content = $this->changelogService->getChangelogContent($patch['version']);
            $date = $content ? $this->changelogService->getReleaseDate($content) : null;
            $htmlContent = $content ? $this->changelogService->markdownToHtml($content) : '';

            $patchesWithContent[] = [
                'version' => $patch['version'],
                'date' => $date,
                'content' => $htmlContent,
            ];
        }

        return view('changelog.minor-release', [
            'minorVersion' => $minorVersion,
            'patches' => $patchesWithContent,
            'minorReleases' => $minorReleases,
        ]);
    }
}

