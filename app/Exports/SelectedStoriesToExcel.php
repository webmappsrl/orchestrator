<?php

namespace App\Exports;

use App\Enums\StoryStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SelectedStoriesToExcel implements FromCollection, WithMapping, WithHeadings, ShouldAutoSize
{
    public function __construct(
        private readonly Collection $stories
    ) {
    }

    public function collection(): Collection
    {
        return $this->stories;
    }

    public function headings(): array
    {
        return [
            __('Ticket ID'),
            __('Ticket status'),
            __('Created at'),
            __('Tags list'),
            __('Creator'),
            __('Assigned to'),
            __('Tester'),
            __('Ticket title'),
            __('Request'),
            __('Ticket URL'),
        ];
    }

    public function map($story): array
    {
        $tags = $story->tags?->pluck('name')->filter()->values()->implode(', ') ?? '';

        $creator = $story->creator?->name ?? '';
        $assignedTo = $story->developer?->name ?? '';
        $tester = $story->tester?->name ?? '';

        $status = StoryStatus::tryFrom((string) $story->status)?->label() ?? (string) $story->status;
        $createdAt = $story->created_at?->format('d/m/Y') ?? '';

        $request = $this->sanitizeRichText($story->customer_request ?? '');

        $baseUrl = rtrim((string) config('app.url'), '/');
        $url = $baseUrl . '/resources/developer-stories/' . $story->id;

        return [
            $story->id,
            $status,
            $createdAt,
            $tags,
            $creator,
            $assignedTo,
            $tester,
            $story->name ?? '',
            $request,
            $url,
        ];
    }

    private function sanitizeRichText(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Collapse whitespace (keeps newlines)
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = trim((string) $text);

        return Str::limit($text, 1000000, ''); // Avoid extreme cases; Excel cells stay safe.
    }
}

