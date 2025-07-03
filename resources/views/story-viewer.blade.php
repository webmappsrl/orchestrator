@php
    $urlNova = url('/resources/assigned-to-me-stories');
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
        }

        table {
            background-color: #fff;
            border-collapse: collapse;
            margin-bottom: 10px;
            width: 100%;
        }

        th {
            background-color: #2FBDA5;
            color: #fff;
            font-size: 14px;
            font-weight: bold;
            padding: 5px;
            text-align: left;
        }

        td {
            border: 1px solid #ddd;
            padding: 5px;
            vertical-align: top;
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
            <col style="width: 50px;">
            <col style="width: 150px;">
            <col style="width: 200px;">
            <col style="width: 400px;">
            <col style="width: 100px;">
            <col style="width: 100px;">
        </colgroup>
        <thead>
            <tr>
                <th>{{ __('ID') }}</th>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Tags') }}</th>
                <th>{{ __('Description') }}</th>
                <th>{{ __('SAL') }}</th>
                <th>{{ __('Created At') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($stories as $story)
                <tr onclick="window.open('{{ $urlNova . '/' . $story->id }}', '_blank');" style="cursor: pointer;">
                    <td>
                        <div class="text-500 font-bold" style="color:#2FBDA5;">{{ $story->id }}</div>
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
                        <div class="text-gray-500 font-bold">
                            @forelse ($story->tags as $tags)
                                <div class="text-yellow-500 font-bold">
                                    {{ $tags->name }}
                                </div>
                            @empty
                                <span class="text-gray-400 italic">{{ __('No Tags') }}</span>
                            @endforelse
                        </div>
                    </td>
                    <td>
                        <div style="max-height: 100px; overflow-y: auto; padding-right: 5px;">
                            @if ($story->description)
                                {!! $story->description !!}
                            @elseif($story->customer_request)
                                <div class="max-height: 100px; overflow-y: auto; padding-right: 5px;">
                                    {!! $story->customer_request !!}
                                </div>
                            @else
                                <span class="text-gray-400 italic">{{ __('No Description') }}</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="text-gray-500">
                            @if ($story->hours || $story->effective_hours)
                                {{ number_format($story->hours, 2) }} /
                                {{ number_format($story->estimated_hours, 0) }}
                            @else
                                <span class="text-gray-500">0 / 0</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        @if ($story->created_at)
                            <div class="text-gray-500">{{ $story->created_at->format('d/m/y') }}</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6"> {{ __('No Ticket Available') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>

</html>
