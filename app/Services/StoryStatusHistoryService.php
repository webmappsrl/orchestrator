<?php

namespace App\Services;

use App\Enums\StoryStatus;
use App\Models\Story;
use Illuminate\Support\Carbon;

/**
 * Cronologia degli stati di una Story, per periodi e per giorno di calendario (oc:8636).
 *
 * Serve a capire in quali giorni un ticket è stato in quale stato, non a misurare il tempo
 * lavorato: i minuti sono di calendario, 24 ore su 24, nel fuso Europe/Rome.
 */
class StoryStatusHistoryService
{
    public const TIMEZONE = 'Europe/Rome';

    public function forStory(Story $story, ?StoryStatus $status = null): array
    {
        $intervals = $this->intervals($story);
        $days = $this->days($intervals);

        if ($status !== null) {
            $intervals = array_values(array_filter(
                $intervals,
                fn (array $interval) => $interval['status'] === $status->value
            ));
            $days = $this->filterDays($days, $status->value);
        }

        return [
            'story_id' => $story->id,
            'timezone' => self::TIMEZONE,
            'current_status' => $story->status,
            'intervals' => array_map(fn (array $interval) => [
                'status' => $interval['status'],
                'from' => $interval['from']->toIso8601String(),
                'to' => $interval['to']?->toIso8601String(),
            ], $intervals),
            'days' => $days,
        ];
    }

    /**
     * La cronologia parte sempre da stories.created_at. Alla creazione lo stato non viene
     * registrato: se esistono cambi di stato, il primo periodo è assunto `new` (default della
     * colonna); se non ne esistono, l'unico periodo ha lo stato attuale.
     *
     * @return array<array{status: string, from: Carbon, to: Carbon|null}>
     */
    private function intervals(Story $story): array
    {
        $createdAt = $this->local($story->created_at);

        $logs = $story->storyLogs()
            ->whereRaw("jsonb_exists(changes::jsonb, 'status')")
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'created_at', 'changes']);

        if ($logs->isEmpty()) {
            return [['status' => $story->status, 'from' => $createdAt, 'to' => null]];
        }

        $intervals = [];
        $open = ['status' => StoryStatus::New->value, 'from' => $createdAt, 'to' => null];

        foreach ($logs as $log) {
            $status = $log->changes['status'];

            // Stesso stato del periodo aperto: promemoria waiting ripetuti, riga di creazione.
            if ($status === $open['status']) {
                continue;
            }

            $at = $this->local($log->created_at);
            $open['to'] = $at;
            $intervals[] = $open;
            $open = ['status' => $status, 'from' => $at, 'to' => null];
        }

        $intervals[] = $open;

        return $intervals;
    }

    /**
     * Ogni periodo si divide alle mezzanotti di Europe/Rome. I minuti si calcolano sui timestamp,
     * così i giorni del cambio d'ora valgono 1380 o 1500 minuti.
     *
     * @param array<array{status: string, from: Carbon, to: Carbon|null}> $intervals
     * @return array<array{date: string, statuses: array<string, int>}>
     */
    private function days(array $intervals): array
    {
        $byDate = [];
        $now = $this->local(now());

        foreach ($intervals as $interval) {
            $cursor = $interval['from']->copy();
            $end = $interval['to'] ?? $now;

            while ($cursor->lt($end)) {
                $midnight = $cursor->copy()->addDay()->startOfDay();
                $pieceEnd = $midnight->lt($end) ? $midnight : $end;
                $minutes = intdiv($pieceEnd->getTimestamp() - $cursor->getTimestamp(), 60);

                $date = $cursor->toDateString();
                $byDate[$date][$interval['status']] = ($byDate[$date][$interval['status']] ?? 0) + $minutes;

                $cursor = $pieceEnd;
            }
        }

        ksort($byDate);

        $days = [];
        foreach ($byDate as $date => $statuses) {
            $days[] = ['date' => $date, 'statuses' => $statuses];
        }

        return $days;
    }

    /**
     * @param array<array{date: string, statuses: array<string, int>}> $days
     * @return array<array{date: string, statuses: array<string, int>}>
     */
    private function filterDays(array $days, string $status): array
    {
        $filtered = [];

        foreach ($days as $day) {
            if (array_key_exists($status, $day['statuses'])) {
                $filtered[] = ['date' => $day['date'], 'statuses' => [$status => $day['statuses'][$status]]];
            }
        }

        return $filtered;
    }

    private function local(\DateTimeInterface $date): Carbon
    {
        return Carbon::instance($date)->timezone(self::TIMEZONE);
    }
}
