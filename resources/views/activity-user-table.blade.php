@php
    $daysOfWeek = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];
@endphp

<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Activity User Table</title>
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

        .daily-summary-row {
            background-color: #e8f5f3;
            font-weight: bold;
            color: #2FBDA5;
            font-size: 14px;
        }

        .daily-summary-row:hover {
            background-color: #d4ede9;
        }

        .user-row {
            background-color: #fff;
        }

        .user-row:hover {
            background-color: #f9f9f9;
        }
    </style>
</head>

<body>

    <h1>Attività Utenti: {{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }}</h1>

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
            <col style="width: 20%;">
            <col style="width: 12%;">
            <col style="width: 14%;">
            <col style="width: 14%;">
            <col style="width: 14%;">
            <col style="width: 14%;">
            <col style="width: 12%;">
        </colgroup>
        <thead>
            <tr>
                <th>{{ __('Utente') }}</th>
                <th>{{ __('Numero totale ticket') }}</th>
                <th>{{ __('Tempo totale speso') }}</th>
                <th>{{ __('Tempo medio per ticket') }}</th>
                <th>{{ __('Durata minima') }}</th>
                <th>{{ __('Durata massima') }}</th>
                <th>{{ __('Data') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groupedByDateAndUser as $dateString => $userActivities)
                @php
                    $activityDate = \Carbon\Carbon::parse($dateString);
                    $dayTotalMinutes = 0;
                    $dayTotalTickets = 0;
                @endphp
                @foreach($userActivities as $userId => $activities)
                    @php
                        $user = $activities->first()->user;
                        $userTotalMinutes = $activities->sum('elapsed_minutes');
                        $userTotalTickets = $activities->count();
                        $dayTotalMinutes += $userTotalMinutes;
                        $dayTotalTickets += $userTotalTickets;
                        
                        $userHours = floor($userTotalMinutes / 60);
                        $userMinutes = $userTotalMinutes % 60;
                        $timeDisplay = $userHours > 0 ? "{$userHours}h {$userMinutes}m" : "{$userMinutes}m";
                        
                        // Calculate average time per ticket
                        $avgMinutes = $userTotalTickets > 0 ? round($userTotalMinutes / $userTotalTickets) : 0;
                        $avgHours = floor($avgMinutes / 60);
                        $avgMinutesRemainder = $avgMinutes % 60;
                        $avgTimeDisplay = $avgHours > 0 ? "{$avgHours}h {$avgMinutesRemainder}m" : "{$avgMinutesRemainder}m";
                        
                        // Calculate min and max durations for this user
                        $userElapsedMinutes = $activities->pluck('elapsed_minutes')->toArray();
                        $userMinMinutes = !empty($userElapsedMinutes) ? min($userElapsedMinutes) : 0;
                        $userMaxMinutes = !empty($userElapsedMinutes) ? max($userElapsedMinutes) : 0;
                        $userMinHours = floor($userMinMinutes / 60);
                        $userMinMinutesRemainder = $userMinMinutes % 60;
                        $userMaxHours = floor($userMaxMinutes / 60);
                        $userMaxMinutesRemainder = $userMaxMinutes % 60;
                        $userMinTimeDisplay = $userMinHours > 0 ? "{$userMinHours}h {$userMinMinutesRemainder}m" : "{$userMinMinutesRemainder}m";
                        $userMaxTimeDisplay = $userMaxHours > 0 ? "{$userMaxHours}h {$userMaxMinutesRemainder}m" : "{$userMaxMinutesRemainder}m";
                    @endphp
                    <tr class="user-row">
                        <td>
                            @if ($user)
                                <div class="text-gray-900 font-medium">
                                    {{ $user->name }}
                                </div>
                            @else
                                <span class="text-gray-400 italic">{{ __('No User') }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="text-gray-700 font-semibold">
                                {{ $userTotalTickets }}
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
                            <div class="text-purple-600 font-semibold">
                                {{ $userMinTimeDisplay }}
                            </div>
                        </td>
                        <td>
                            <div class="text-orange-600 font-semibold">
                                {{ $userMaxTimeDisplay }}
                            </div>
                        </td>
                        <td>
                            <div class="text-gray-500">
                                {{ $activityDate->format('d/m/Y') }}
                                <br>
                                <span style="font-size: 11px; color: #999;">
                                    {{ $daysOfWeek[$activityDate->dayOfWeek - 1] ?? '' }}
                                </span>
                            </div>
                        </td>
                    </tr>
                @endforeach
                {{-- Add summary row after all users of the day with total for all users --}}
                @php
                    $dayHours = floor($dayTotalMinutes / 60);
                    $dayMinutes = $dayTotalMinutes % 60;
                    $dayTimeDisplay = $dayHours > 0 ? "{$dayHours}h {$dayMinutes}m" : "{$dayMinutes}m";
                    
                    // Calculate average time per ticket for the day
                    $dayAvgMinutes = $dayTotalTickets > 0 ? round($dayTotalMinutes / $dayTotalTickets) : 0;
                    $dayAvgHours = floor($dayAvgMinutes / 60);
                    $dayAvgMinutesRemainder = $dayAvgMinutes % 60;
                    $dayAvgTimeDisplay = $dayAvgHours > 0 ? "{$dayAvgHours}h {$dayAvgMinutesRemainder}m" : "{$dayAvgMinutesRemainder}m";
                    
                    // Calculate min and max for the day
                    $dayElapsedMinutes = [];
                    foreach ($userActivities as $userActivityList) {
                        foreach ($userActivityList as $activity) {
                            $dayElapsedMinutes[] = $activity->elapsed_minutes;
                        }
                    }
                    $dayMinMinutes = !empty($dayElapsedMinutes) ? min($dayElapsedMinutes) : 0;
                    $dayMaxMinutes = !empty($dayElapsedMinutes) ? max($dayElapsedMinutes) : 0;
                    $dayMinHours = floor($dayMinMinutes / 60);
                    $dayMinMinutesRemainder = $dayMinMinutes % 60;
                    $dayMaxHours = floor($dayMaxMinutes / 60);
                    $dayMaxMinutesRemainder = $dayMaxMinutes % 60;
                    $dayMinTimeDisplay = $dayMinHours > 0 ? "{$dayMinHours}h {$dayMinMinutesRemainder}m" : "{$dayMinMinutesRemainder}m";
                    $dayMaxTimeDisplay = $dayMaxHours > 0 ? "{$dayMaxHours}h {$dayMaxMinutesRemainder}m" : "{$dayMaxMinutesRemainder}m";
                @endphp
                <tr class="daily-summary-row">
                    <td colspan="2" style="text-align: right; padding-right: 20px;">
                        <strong>Totale giornaliero ({{ $activityDate->format('d/m/Y') }} - {{ $daysOfWeek[$activityDate->dayOfWeek - 1] ?? '' }}):</strong>
                    </td>
                    <td>
                        <strong>{{ $dayTimeDisplay }}</strong>
                    </td>
                    <td>
                        <strong>{{ $dayAvgTimeDisplay }}</strong>
                    </td>
                    <td>
                        <strong>{{ $dayMinTimeDisplay }}</strong>
                    </td>
                    <td>
                        <strong>{{ $dayMaxTimeDisplay }}</strong>
                    </td>
                    <td>
                        <strong>{{ $dayTotalTickets }} ticket</strong>
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
