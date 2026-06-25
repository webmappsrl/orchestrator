<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SlackService
{
    private string $token;

    public function __construct()
    {
        $this->token = config('services.slack.bot_token');
    }

    public function getPresence(string $slackUserId): string
    {
        $response = Http::withToken($this->token)
            ->get('https://slack.com/api/users.getPresence', [
                'user' => $slackUserId,
            ]);

        if (! $response->successful()) {
            throw new \Exception("Slack API HTTP error: {$response->status()}");
        }

        $data = $response->json();

        if (! ($data['ok'] ?? false)) {
            throw new \Exception("Slack API error: " . ($data['error'] ?? 'unknown'));
        }

        return $data['presence'];
    }
}
