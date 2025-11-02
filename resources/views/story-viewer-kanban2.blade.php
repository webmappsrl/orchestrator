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
            @if ((isset($showTester) && $showTester) || (isset($showAssignedTo) && $showAssignedTo))
                <col style="width: 4%;">
                <col style="width: 12%;">
                <col style="width: 15%;">
                <col style="width: 15%;">
                <col style="width: 20%;">
                <col style="width: 12%;">
                <col style="width: 12%;">
            @else
                <col style="width: 5%;">
                <col style="width: 15%;">
                <col style="width: 18%;">
                <col style="width: 22%;">
                <col style="width: 12%;">
                <col style="width: 12%;">
            @endif
        </colgroup>
        <thead>
            <tr>
                <th>{{ __('ID') }}</th>
                <th>{{ __('Tags') }}</th>
                @if ((isset($showTester) && $showTester) || (isset($showAssignedTo) && $showAssignedTo))
                    <th>{{ __('Creator') }}</th>
                    <th>
                        @if (isset($showAssignedTo) && $showAssignedTo)
                            {{ __('Assigned To') }}
                        @else
                            {{ __('Tester') }}
                        @endif
                    </th>
                @else
                    <th>{{ __('Creator') }}</th>
                @endif
                <th>{{ __('Ticket') }}</th>
                <th>{{ __('Created At') }}</th>
                <th>{{ __('Last Update') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($stories as $story)
                <tr onclick="window.open('{{ $urlNova . '/' . $story->id }}', '_blank');" style="cursor: pointer;">
                    <td>
                        <div class="text-500 font-bold" style="color:#2FBDA5;">{{ $story->id }}</div>
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
                    @if (isset($showTester) && $showTester)
                        <td>
                            @if ($story->tester)
                                <div class="text-purple-500 font-bold">
                                    {{ $story->tester->name }}
                                </div>
                            @else
                                <span class="text-gray-400 italic">{{ __('No Tester') }}</span>
                            @endif
                        </td>
                    @endif
                    @if (isset($showAssignedTo) && $showAssignedTo)
                        <td>
                            @if ($story->user)
                                <div class="text-green-500 font-bold">
                                    {{ $story->user->name }}
                                </div>
                            @else
                                <span class="text-gray-400 italic">{{ __('No Assignee') }}</span>
                            @endif
                        </td>
                    @endif
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
                        @if ($story->created_at)
                            <div class="text-gray-500">{{ $story->created_at->format('d/m/y') }}</div>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td>
                        @if ($story->updated_at)
                            <div class="text-gray-500">{{ $story->updated_at->format('d/m/y') }}</div>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ (isset($showTester) && $showTester) ? 7 : 6 }}"> {{ __('No Ticket Available') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>

</html>

