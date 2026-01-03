<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;

class ChangelogService
{
    protected $changelogPath;
    protected $converter;

    public function __construct()
    {
        $this->changelogPath = base_path('changelog');
        $this->converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Get all minor releases grouped by version
     *
     * @return array
     */
    public function getMinorReleases(): array
    {
        $files = File::files($this->changelogPath);
        $releases = [];

        foreach ($files as $file) {
            $filename = $file->getFilename();
            
            // Skip non-changelog files
            if (!Str::startsWith($filename, 'CHANGELOG-MS-') || !Str::endsWith($filename, '.md')) {
                continue;
            }

            // Extract version from filename (e.g., CHANGELOG-MS-1.21.7.md -> 1.21.7)
            if (preg_match('/CHANGELOG-MS-(\d+\.\d+\.\d+)\.md/', $filename, $matches)) {
                $version = $matches[1];
                $parts = explode('.', $version);
                
                // Group by minor release (e.g., 1.21.x)
                $minorRelease = $parts[0] . '.' . $parts[1];
                
                if (!isset($releases[$minorRelease])) {
                    $releases[$minorRelease] = [
                        'minor_version' => $minorRelease,
                        'patches' => [],
                    ];
                }

                $releases[$minorRelease]['patches'][] = [
                    'version' => $version,
                    'filename' => $filename,
                    'path' => $file->getPathname(),
                ];
            }
        }

        // Sort patches within each minor release (newest first)
        foreach ($releases as &$release) {
            usort($release['patches'], function ($a, $b) {
                return version_compare($b['version'], $a['version']);
            });
        }

        // Sort minor releases (newest first)
        uksort($releases, function ($a, $b) {
            return version_compare($b, $a);
        });

        return $releases;
    }

    /**
     * Get patches for a specific minor release
     *
     * @param string $minorVersion (e.g., "1.21")
     * @return array
     */
    public function getPatchesForMinorRelease(string $minorVersion): array
    {
        $releases = $this->getMinorReleases();
        
        if (!isset($releases[$minorVersion])) {
            return [];
        }

        return $releases[$minorVersion]['patches'];
    }

    /**
     * Get changelog content for a specific version
     *
     * @param string $version
     * @return string|null
     */
    public function getChangelogContent(string $version): ?string
    {
        $filename = "CHANGELOG-MS-{$version}.md";
        $filePath = $this->changelogPath . '/' . $filename;

        if (!File::exists($filePath)) {
            return null;
        }

        return File::get($filePath);
    }

    /**
     * Get release date from changelog content
     *
     * @param string $content
     * @return string|null
     */
    public function getReleaseDate(string $content): ?string
    {
        if (preg_match('/\*\*Release Date:\*\*\s*(.+?)\s*\n/', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Convert markdown content to HTML
     *
     * @param string $markdown
     * @return string
     */
    public function markdownToHtml(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}

