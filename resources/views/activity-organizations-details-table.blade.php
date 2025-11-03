<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Activity Organizations Details Table</title>
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

        .ticket-row {
            background-color: #fff;
        }

        .ticket-row:hover {
            background-color: #f9f9f9;
        }

        a {
            color: #4099de;
            text-decoration: none;
            font-weight: bold;
        }

        a:hover {
            color: #2d6fa3;
            text-decoration: underline;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            color: #333;
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
    </style>
</head>

<body>

    <h1>
        @if($selectedOrganization)
            Ticket per Organization: {{ $selectedOrganization->name }} ({{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }})
        @else
            Seleziona un'organizzazione per visualizzare i ticket
        @endif
    </h1>

    @if($selectedOrganization && isset($totalTickets))
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
    @endif

    @if($selectedOrganization && $tickets->count() > 0)
        <table>
            <colgroup>
                <col style="width: 5%;">
                <col style="width: 25%;">
                <col style="width: 15%;">
                <col style="width: 15%;">
                <col style="width: 15%;">
                <col style="width: 15%;">
                <col style="width: 10%;">
            </colgroup>
            <thead>
                <tr>
                    <th>{{ __('ID') }}</th>
                    <th>{{ __('Ticket') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Ultima attività') }}</th>
                    <th>{{ __('Tempo totale') }}</th>
                    <th>{{ __('Assegnato a') }}</th>
                    <th>{{ __('Creatore') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tickets as $ticketData)
                    @php
                        $story = $ticketData['story'];
                        $lastActivityDate = $ticketData['last_activity_date'];
                        $totalMinutes = $ticketData['total_minutes'];
                        $totalHours = floor($totalMinutes / 60);
                        $totalMinutesRemainder = $totalMinutes % 60;
                        $timeDisplay = $totalHours > 0 ? "{$totalHours}h {$totalMinutesRemainder}m" : "{$totalMinutesRemainder}m";
                        
                        // Get status color from enum
                        $statusColor = '';
                        if ($story->status) {
                            try {
                                $statusEnum = \App\Enums\StoryStatus::from($story->status);
                                $statusColor = $statusEnum->color();
                            } catch (\Exception $e) {
                                $statusColor = '#cccccc';
                            }
                        } else {
                            $statusColor = '#cccccc';
                        }
                    @endphp
                    <tr class="ticket-row">
                        <td>
                            <a href="/resources/customer-stories/{{ $story->id }}" target="_blank">
                                {{ $story->id }}
                            </a>
                        </td>
                        <td>
                            <a href="/resources/customer-stories/{{ $story->id }}" target="_blank">
                                {{ $story->name }}
                            </a>
                        </td>
                        <td>
                            <span class="status-badge" style="background-color: {{ $statusColor }}50; color: #333;">
                                {{ $story->status ?? 'N/A' }}
                            </span>
                        </td>
                        <td>
                            {{ $lastActivityDate ? \Carbon\Carbon::parse($lastActivityDate)->format('d/m/Y') : 'N/A' }}
                        </td>
                        <td>
                            <strong>{{ $timeDisplay }}</strong>
                        </td>
                        <td>
                            {{ $story->user ? $story->user->name : 'N/A' }}
                        </td>
                        <td>
                            {{ $story->creator ? $story->creator->name : 'N/A' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($selectedOrganization && $tickets->count() == 0)
        <p>Nessun ticket trovato per l'organizzazione selezionata nel periodo indicato.</p>
    @else
        <p>Seleziona un'organizzazione per visualizzare i ticket.</p>
    @endif

</body>

</html>

