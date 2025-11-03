@php
    $urlNova = url('/resources/customer-stories');
    $daysOfWeek = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];
@endphp

<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Activity Table</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        h1 {
            color: #343434ad;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
            text-align: left;
        }

        .summary {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .summary h2 {
            color: #2FBDA5;
            font-size: 16px;
            margin: 0 0 10px 0;
        }

        .summary p {
            margin: 5px 0;
            color: #333;
        }

        table {
            background-color: #fff;
            border-collapse: collapse;
            margin-bottom: 10px;
            width: 100%;
            table-layout: fixed;
            overflow: hidden;
        }

        th {
            background-color: #2FBDA5;
            color: #fff;
            font-size: 14px;
            font-weight: bold;
            padding: 5px 5px 5px 12px;
            text-align: left;
        }

        td {
            border: 1px solid #ddd;
            padding: 5px 5px 5px 12px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            text-align: left;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .daily-summary-row {
            background-color: #e8f5f3;
            font-weight: bold;
            color: #2FBDA5;
        }

        .daily-summary-row:hover {
            background-color: #d4ede9;
        }

        a {
            color: #333;
            text-decoration: none;
        }

        a:hover {
            color: #666;
        }

        .clickable-row {
            cursor: pointer;
        }
    </style>
</head>

<body>

    <h1>Attività: {{ $selectedUser->name }} ({{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }})</h1>

    <div class="summary">
        <h2>📊 Riepilogo Periodo</h2>
        <p><strong>Tempo totale:</strong> {{ $totalHours }}h {{ $totalMinutes }}m</p>
        <p><strong>Numero attività:</strong> {{ $activities->count() }}</p>
    </div>

    <table>
        <colgroup>
            <col style="width: 5%;">
            <col style="width: 12%;">
            <col style="width: 15%;">
            <col style="width: 18%;">
            <col style="width: 20%;">
            <col style="width: 15%;">
            <col style="width: 15%;">
        </colgroup>
        <thead>
            <tr>
                <th>{{ __('ID') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Tags') }}</th>
                <th>{{ __('Creator') }}</th>
                <th>{{ __('Ticket') }}</th>
                <th>{{ __('Tempo Speso') }}</th>
                <th>{{ __('Data') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groupedByDate as $dateString => $dayActivities)
                @php
                    $dayActivities = $dayActivities->sortByDesc('created_at');
                @endphp
                @foreach($dayActivities as $activity)
                    @php
                        $story = $activity->story;
                    @endphp
                    <tr class="clickable-row" onclick="window.open('{{ $urlNova . '/' . $story->id }}', '_blank');">
                    <td>
                        <div class="text-500 font-bold" style="color:#2FBDA5;">{{ $story->id }}</div>
                    </td>
                    <td>
                        @php
                            try {
                                $statusEnum = \App\Enums\StoryStatus::from($story->status);
                                $statusColor = $statusEnum->color();
                                $statusIcon = $statusEnum->icon();
                                $statusLabel = strtoupper(__(ucfirst($story->status)));
                            } catch (\ValueError $e) {
                                $statusColor = '#000000';
                                $statusIcon = '❓';
                                $statusLabel = strtoupper($story->status);
                            }
                        @endphp
                        <span style="
                            background-color: {{ $statusColor }}80;
                            color: #374151;
                            font-weight: bold;
                            padding: 4px 12px;
                            border-radius: 12px;
                            display: inline-flex;
                            align-items: center;
                            gap: 6px;
                            border: 1px solid {{ $statusColor }};
                            text-transform: uppercase;
                            font-size: 11px;
                        ">
                            <span>{{ $statusIcon }}</span>
                            <span>{{ $statusLabel }}</span>
                        </span>
                    </td>
                    <td>
                        <div class="text-gray-500">
                            @forelse ($story->tags as $tag)
                                <div class="text-yellow-500 font-bold" style="font-size: 12px; margin-bottom: 2px;">
                                    {{ $tag->name }}
                                </div>
                            @empty
                                <span class="text-gray-400 italic" style="font-size: 12px;">{{ __('No Tags') }}</span>
                            @endforelse
                        </div>
                    </td>
                    <td>
                        @if ($story->creator)
                            <div class="text-gray-500">
                                {{ $story->creator->name }}
                            </div>
                        @else
                            <span class="text-gray-400 italic">{{ __('No Creator') }}</span>
                        @endif
                    </td>
                    @php
                        $typeColors = [
                            'Bug' => 'color:red',
                            'Help desk' => 'color:green',
                            'Feature' => 'color:blue',
                        ];
                        $type = ucfirst(strtolower(trim($story->type ?? '')));
                        $typeColorStyle = $typeColors[$type] ?? 'color:gray';
                    @endphp
                    <td>
                        <div class="text-xs text-500 font-bold" style="{{ $typeColorStyle }}">
                            {{ $story->type ?? __('No Type') }}
                        </div>
                        <div class="text-gray-500">
                            {{ $story->name }}
                        </div>
                    </td>
                    <td>
                        @php
                            $hours = floor($activity->elapsed_minutes / 60);
                            $minutes = $activity->elapsed_minutes % 60;
                            $timeDisplay = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                        @endphp
                        <div class="text-blue-500 font-bold">
                            {{ $timeDisplay }}
                        </div>
                    </td>
                    <td>
                        @if ($activity->date)
                            <div class="text-gray-500">
                                {{ $activity->date->format('d/m/y') }}
                                <br>
                                <span style="font-size: 11px; color: #999;">
                                    {{ $daysOfWeek[$activity->date->dayOfWeek - 1] ?? '' }}
                                </span>
                            </div>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                </tr>
                @endforeach
                {{-- Add summary row after all activities of the day --}}
                @php
                    $dailyTotalMinutes = $dayActivities->sum('elapsed_minutes');
                    $dailyHours = floor($dailyTotalMinutes / 60);
                    $dailyMinutes = $dailyTotalMinutes % 60;
                @endphp
                <tr class="daily-summary-row">
                    <td colspan="6" style="text-align: right; padding-right: 20px;">
                        <strong>Totale giornaliero:</strong>
                    </td>
                    <td>
                        <strong>{{ $dailyHours }}h {{ $dailyMinutes }}m</strong>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">{{ __('No Activity Available') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>

</html>

