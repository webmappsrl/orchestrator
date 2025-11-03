<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Activity Customer Table</title>
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
            padding: 8px 12px;
            text-align: left;
        }

        td {
            border: 1px solid #ddd;
            padding: 8px 12px;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
            text-align: left;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .customer-row {
            background-color: #fff;
        }

        .customer-row:hover {
            background-color: #f9f9f9;
        }
    </style>
</head>

<body>

    <h1>Attività per Customer: {{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }}</h1>

    <div class="summary">
        <h2>📊 Riepilogo Periodo</h2>
        @php
            $avgTimeDisplay = $avgHours > 0 ? "{$avgHours}h {$avgMinutes}m" : "{$avgMinutes}m";
            $minTimeDisplay = $minHours > 0 ? "{$minHours}h {$minMinutes}m" : "{$minMinutes}m";
            $maxTimeDisplay = $maxHours > 0 ? "{$maxHours}h {$maxMinutes}m" : "{$maxMinutes}m";
        @endphp
        <p>
            <strong>Tempo totale:</strong> {{ $totalHours }}h {{ $totalMinutes }}m | 
            <strong>Numero totale ticket:</strong> {{ $totalTickets }} | 
            <strong>Durata media ticket:</strong> {{ $avgTimeDisplay }} | 
            <strong>Durata minima:</strong> {{ $minTimeDisplay }} | 
            <strong>Durata massima:</strong> {{ $maxTimeDisplay }}
        </p>
    </div>

    <table>
        <colgroup>
            <col style="width: 30%;">
            <col style="width: 15%;">
            <col style="width: 18%;">
            <col style="width: 18%;">
            <col style="width: 19%;">
        </colgroup>
        <thead>
            <tr>
                <th>{{ __('Customer') }}</th>
                <th>{{ __('Numero totale ticket') }}</th>
                <th>{{ __('Tempo totale speso') }}</th>
                <th>{{ __('Tempo medio per ticket') }}</th>
                <th>{{ __('Durata minima / massima') }}</th>
            </tr>
        </thead>
        <tbody>
            @php
                // Sort customers by name alphabetically
                usort($customerStats, function ($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });
            @endphp
            @forelse($customerStats as $customerStat)
                @php
                    $totalHours = floor($customerStat['total_minutes'] / 60);
                    $totalMinutes = $customerStat['total_minutes'] % 60;
                    $timeDisplay = $totalHours > 0 ? "{$totalHours}h {$totalMinutes}m" : "{$totalMinutes}m";
                    
                    // Calculate average time per ticket
                    $avgMinutes = $customerStat['ticket_count'] > 0 ? round($customerStat['total_minutes'] / $customerStat['ticket_count']) : 0;
                    $avgHours = floor($avgMinutes / 60);
                    $avgMinutesRemainder = $avgMinutes % 60;
                    $avgTimeDisplay = $avgHours > 0 ? "{$avgHours}h {$avgMinutesRemainder}m" : "{$avgMinutesRemainder}m";
                    
                    // Calculate min and max durations
                    $minMinutes = !empty($customerStat['elapsed_minutes']) ? min($customerStat['elapsed_minutes']) : 0;
                    $maxMinutes = !empty($customerStat['elapsed_minutes']) ? max($customerStat['elapsed_minutes']) : 0;
                    $minHours = floor($minMinutes / 60);
                    $minMinutesRemainder = $minMinutes % 60;
                    $maxHours = floor($maxMinutes / 60);
                    $maxMinutesRemainder = $maxMinutes % 60;
                    $minTimeDisplay = $minHours > 0 ? "{$minHours}h {$minMinutesRemainder}m" : "{$minMinutesRemainder}m";
                    $maxTimeDisplay = $maxHours > 0 ? "{$maxHours}h {$maxMinutesRemainder}m" : "{$maxMinutesRemainder}m";
                @endphp
                <tr class="customer-row">
                    <td>
                        <div class="text-gray-900 font-medium">
                            {{ $customerStat['name'] }}
                        </div>
                    </td>
                    <td>
                        <div class="text-gray-700 font-semibold">
                            {{ $customerStat['ticket_count'] }}
                        </div>
                    </td>
                    <td>
                        <div class="text-blue-600 font-bold">
                            {{ $timeDisplay }}
                        </div>
                    </td>
                    <td>
                        <div class="text-green-600 font-semibold">
                            {{ $avgTimeDisplay }}
                        </div>
                    </td>
                    <td>
                        <div class="text-purple-600 font-semibold" style="margin-bottom: 2px;">
                            {{ $minTimeDisplay }}
                        </div>
                        <div class="text-orange-600 font-semibold">
                            {{ $maxTimeDisplay }}
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">{{ __('No Activity Available') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>

</html>

