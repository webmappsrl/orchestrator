<?php
// app/Services/Metrics/GitHubMetricsService.php

namespace App\Services\Metrics;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubMetricsService
{
    private string $token;
    private string $org;
    private string $baseUrl = 'https://api.github.com';

    public function __construct()
    {
        $this->token = config('services.github.token', '');
        $this->org = config('services.github.org', '');
    }

    /**
     * Cerca tutti i commit nell'org GitHub che menzionano oc:{storyId}
     * nel messaggio di commit. Restituisce array di commit trovati.
     */
    public function getCommitsForStory(int $storyId): array
    {
        if (empty($this->token) || empty($this->org)) {
            Log::warning('GitHubMetricsService: GITHUB_TOKEN o GITHUB_ORG non configurati');
            return [];
        }

        $query = "oc:{$storyId} org:{$this->org}";
        $commits = [];
        $page = 1;

        do {
            $response = $this->searchCommits($query, $page);

            if ($response === null) {
                break;
            }

            foreach ($response['items'] ?? [] as $item) {
                $commits[] = [
                    'sha'          => $item['sha'],
                    'repo'         => $item['repository']['full_name'],
                    'author_email' => $item['commit']['author']['email'] ?? null,
                    'committed_at' => $item['commit']['author']['date'] ?? null,
                ];
            }

            $totalCount = $response['total_count'] ?? 0;
            $page++;
        } while (count($commits) < $totalCount && $page <= 10);

        return $commits;
    }

    private function searchCommits(string $query, int $page): ?array
    {
        $maxRetries = 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $response = Http::withToken($this->token)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get("{$this->baseUrl}/search/commits", [
                    'q'        => $query,
                    'per_page' => 100,
                    'page'     => $page,
                ]);

            if ($response->status() === 422) {
                // Query non valida — nessun retry
                Log::warning('GitHubMetricsService: query non valida', ['query' => $query]);
                return null;
            }

            if ($response->status() === 403 || $response->status() === 429) {
                // Rate limit — backoff esponenziale
                $retryAfter = (int) ($response->header('Retry-After') ?: (2 ** $attempt));
                Log::info("GitHubMetricsService: rate limited, retry in {$retryAfter}s (tentativo {$attempt})");
                sleep($retryAfter);
                continue;
            }

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('GitHubMetricsService: risposta inattesa', [
                'status' => $response->status(),
                'attempt' => $attempt,
            ]);

            if ($attempt < $maxRetries) {
                sleep(2 ** $attempt);
            }
        }

        return null;
    }

    /**
     * Cerca le PR nell'org GitHub che menzionano oc:{storyId} nel titolo o body.
     * Per ogni PR recupera il numero di CHANGES_REQUESTED ricevuti.
     */
    public function getPrsForStory(int $storyId): array
    {
        if (empty($this->token) || empty($this->org)) {
            return [];
        }

        $query = "oc:{$storyId} org:{$this->org} is:pr";
        $response = $this->searchIssues($query);

        if ($response === null) {
            return [];
        }

        $prs = [];
        foreach ($response['items'] ?? [] as $item) {
            $repo = $this->repoFromUrl($item['repository_url'] ?? '');
            $prNumber = $item['number'];
            $changeRequestsCount = $this->countChangeRequests($repo, $prNumber);

            // Fetch PR info per merged_at — /search/issues non lo restituisce
            $prResponse = Http::withToken($this->token)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get("{$this->baseUrl}/repos/{$repo}/pulls/{$prNumber}");

            $mergedAt = $prResponse->successful() ? ($prResponse->json()['merged_at'] ?? null) : null;

            $prs[] = [
                'repo'                  => $repo,
                'pr_number'             => $prNumber,
                'change_requests_count' => $changeRequestsCount,
                'merged_at'             => $mergedAt,
            ];
        }

        return $prs;
    }

    private function searchIssues(string $query): ?array
    {
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $response = Http::withToken($this->token)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get("{$this->baseUrl}/search/issues", [
                    'q'        => $query,
                    'per_page' => 100,
                ]);

            if ($response->status() === 422) {
                // Query non valida — nessun retry
                Log::warning('GitHubMetricsService: query non valida', ['query' => $query]);
                return null;
            }

            if ($response->status() === 403 || $response->status() === 429) {
                // Rate limit — backoff esponenziale
                $retryAfter = (int) ($response->header('Retry-After') ?: (2 ** $attempt));
                Log::info("GitHubMetricsService: rate limited, retry in {$retryAfter}s (tentativo {$attempt})");
                sleep($retryAfter);
                continue;
            }

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('GitHubMetricsService: risposta inattesa', [
                'status'  => $response->status(),
                'attempt' => $attempt,
            ]);

            if ($attempt < $maxRetries) {
                sleep(2 ** $attempt);
            }
        }
        return null;
    }

    private function countChangeRequests(string $repo, int $prNumber): int
    {
        if (empty($repo)) {
            return 0;
        }

        $response = Http::withToken($this->token)
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->get("{$this->baseUrl}/repos/{$repo}/pulls/{$prNumber}/reviews");

        if (!$response->successful()) {
            return 0;
        }

        return collect($response->json())->count();
    }

    private function repoFromUrl(string $url): string
    {
        // "https://api.github.com/repos/org/repo-name" → "org/repo-name"
        return preg_replace('#.*/repos/#', '', $url);
    }

    /**
     * Fallback: estrae PR GitHub da testo libero (es. campo description della story).
     * Cerca pattern https://github.com/{org}/{repo}/pull/{number}
     * Restituisce stessa struttura di getPrsForStory().
     */
    public function getPrsFromText(string $text): array
    {
        $pattern = '#https://github\.com/([\w.\-]+/[\w.\-]+)/pull/(\d+)#';
        if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $prs = [];
        foreach ($matches as $match) {
            $repo     = $match[1];
            $prNumber = (int) $match[2];
            $changeRequestsCount = $this->countChangeRequests($repo, $prNumber);

            // Fetch PR info per merged_at
            $prResponse = Http::withToken($this->token)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get("{$this->baseUrl}/repos/{$repo}/pulls/{$prNumber}");

            $mergedAt = $prResponse->successful() ? ($prResponse->json()['merged_at'] ?? null) : null;

            $prs[] = [
                'repo'                  => $repo,
                'pr_number'             => $prNumber,
                'change_requests_count' => $changeRequestsCount,
                'merged_at'             => $mergedAt,
            ];
        }

        return $prs;
    }
}
