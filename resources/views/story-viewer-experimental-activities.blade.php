@php
    $urlNova = url('/resources/customer-stories');
@endphp

<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>{{ $statusLabel }}</title>
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

        a {
            color: #333;
            text-decoration: none;
        }

        a:hover {
            color: #666;
        }
    </style>
</head>

<body>

    <h1>{{ $statusLabel }}</h1>

    <table>
        <colgroup>
            <col style="width: 5%;">
            <col style="width: 12%;">
            <col style="width: 15%;">
            <col style="width: 18%;">
            <col style="width: 20%;">
            <col style="width: 12%;">
            <col style="width: 18%;">
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
            @forelse($stories as $story)
                <tr onclick="window.open('{{ $urlNova . '/' . $story->id }}', '_blank');" style="cursor: pointer;">
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
                            // Calculate total time from UsersStoriesLog for this story
                            $totalMinutes = \App\Models\UsersStoriesLog::where('story_id', $story->id)
                                ->where('elapsed_minutes', '>', 0)
                                ->sum('elapsed_minutes');
                            $hours = floor($totalMinutes / 60);
                            $minutes = $totalMinutes % 60;
                            $timeDisplay = $hours > 0 ? "{$hours}h {$minutes}m" : ($minutes > 0 ? "{$minutes}m" : '-');
                        @endphp
                        <div class="text-blue-500 font-bold">
                            {{ $timeDisplay }}
                        </div>
                    </td>
                    <td>
                        @php
                            // Use released_at if available, otherwise done_at
                            $date = $story->released_at ?? $story->done_at;
                        @endphp
                        @if ($date)
                            <div class="text-gray-500">
                                {{ $date->format('d/m/y') }}
                                <br>
                                <span style="font-size: 11px; color: #999;">
                                    {{ $date->locale('it')->dayName }}
                                </span>
                            </div>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">{{ __('No Ticket Available') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>

</html>

