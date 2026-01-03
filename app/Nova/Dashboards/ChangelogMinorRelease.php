<?php

namespace App\Nova\Dashboards;

use App\Services\ChangelogService;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class ChangelogMinorRelease extends Dashboard
{
    protected $minorVersion;

    /**
     * Constructor - accepts optional minor version
     */
    public function __construct($minorVersion = null)
    {
        parent::__construct();
        $this->minorVersion = $minorVersion;
    }

    /**
     * Get the minor version from the URI key
     */
    protected function getMinorVersion()
    {
        if ($this->minorVersion) {
            return $this->minorVersion;
        }

        // Extract version from URI key (e.g., 'changelog-1-21' -> '1.21')
        $uriKey = $this->uriKey();
        if (preg_match('/changelog-(\d+)-(\d+)/', $uriKey, $matches)) {
            $this->minorVersion = $matches[1] . '.' . $matches[2];
            return $this->minorVersion;
        }

        // Fallback: try to get from request
        $request = request();
        if ($request) {
            $path = $request->path();
            if (preg_match('/dashboards\/changelog-(\d+)-(\d+)/', $path, $matches)) {
                $this->minorVersion = $matches[1] . '.' . $matches[2];
                return $this->minorVersion;
            }
        }

        return null;
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        $minorVersion = $this->getMinorVersion();
        
        if (!$minorVersion) {
            // If no version found, try to get from request path one more time
            $request = request();
            if ($request) {
                $path = $request->path();
                // Check if it's the default changelog-minor-release path
                if (preg_match('/dashboards\/changelog-minor-release/', $path)) {
                    // Redirect to main changelog dashboard
                    $changelogService = app(ChangelogService::class);
                    $minorReleases = $changelogService->getMinorReleases();
                    
                    if (!empty($minorReleases)) {
                        $latestMinorVersion = array_key_first($minorReleases);
                        if ($latestMinorVersion) {
                            return [
                                (new HtmlCard)
                                    ->width('full')
                                    ->view('changelog-redirect', [
                                        'redirectUrl' => url('/dashboards/changelog-' . str_replace('.', '-', $latestMinorVersion)),
                                    ])
                                    ->center(true),
                            ];
                        }
                    }
                    
                    // Fallback: show main changelog
                    return [
                        (new HtmlCard)
                            ->width('full')
                            ->view('changelog-dashboard-index', [
                                'minorReleases' => $minorReleases ?? [],
                            ])
                            ->center(true),
                    ];
                }
            }
            
            // If still no version, show error
            return [
                (new HtmlCard)
                    ->width('full')
                    ->view('changelog-error', [
                        'message' => 'Versione non trovata. Si prega di selezionare una minor release dalla dashboard principale.',
                        'redirectUrl' => url('/dashboards/changelog'),
                    ])
                    ->center(true),
            ];
        }

        $changelogService = app(ChangelogService::class);
        $minorReleases = $changelogService->getMinorReleases();
        $patches = $changelogService->getPatchesForMinorRelease($minorVersion);
        
        // Load content for each patch
        $patchesWithContent = [];
        foreach ($patches as $patch) {
            $content = $changelogService->getChangelogContent($patch['version']);
            $date = $content ? $changelogService->getReleaseDate($content) : null;
            $htmlContent = $content ? $changelogService->markdownToHtml($content) : '';

            $patchesWithContent[] = [
                'version' => $patch['version'],
                'date' => $date,
                'content' => $htmlContent,
            ];
        }
        
        return [
            (new HtmlCard)
                ->width('full')
                ->view('changelog-dashboard-minor-release', [
                    'minorVersion' => $minorVersion,
                    'patches' => $patchesWithContent,
                    'minorReleases' => $minorReleases,
                ])
                ->center(true),
        ];
    }

    /**
     * Get the displayable name of the dashboard.
     *
     * @return string
     */
    public function name()
    {
        $minorVersion = $this->getMinorVersion();
        if ($minorVersion) {
            return __('Changelog') . ' MS-' . $minorVersion . '.x';
        }
        return __('Changelog');
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        // If version is set, use it
        if ($this->minorVersion) {
            return 'changelog-' . str_replace('.', '-', $this->minorVersion);
        }
        
        // Try to extract from request path
        $request = request();
        if ($request) {
            $path = $request->path();
            if (preg_match('/dashboards\/changelog-(\d+)-(\d+)/', $path, $matches)) {
                $version = $matches[1] . '.' . $matches[2];
                $this->minorVersion = $version;
                return 'changelog-' . str_replace('.', '-', $version);
            }
        }
        
        // If no version can be determined and we're being called during menu construction,
        // try to get the latest version as fallback
        try {
            $changelogService = app(ChangelogService::class);
            $minorReleases = $changelogService->getMinorReleases();
            if (!empty($minorReleases) && is_array($minorReleases)) {
                $latestVersion = array_key_first($minorReleases);
                if ($latestVersion && is_string($latestVersion)) {
                    $this->minorVersion = $latestVersion;
                    return 'changelog-' . str_replace('.', '-', $latestVersion);
                }
            }
        } catch (\Exception $e) {
            // Ignore errors during menu construction
        }
        
        // Last resort: return a safe default that will redirect in cards()
        return 'changelog-minor-release';
    }

    /**
     * Set the minor version (used when creating instances)
     */
    public function setMinorVersion($version)
    {
        $this->minorVersion = $version;
        return $this;
    }
}

