{{-- resources/views/pdf/developer-performance-report.blade.php --}}
@php
$logoPath = public_path('images/logo.png');
$logoSrc = file_exists($logoPath) ? 'file://' . $logoPath : '';
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Performance Report — {{ $developer->name }} — Q{{ $quarter }} {{ $year }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; margin: 0; padding: 20px; }
        h1 { font-size: 18px; color: #1e3a5f; margin-bottom: 4px; }
        h2 { font-size: 14px; color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin-top: 24px; }
        .subtitle { color: #6b7280; font-size: 11px; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #1e3a5f; color: white; padding: 6px 10px; text-align: left; font-size: 11px; }
        td { padding: 6px 10px; border-bottom: 1px solid #f3f4f6; }
        .above-avg { color: #16a34a; font-weight: bold; }
        .below-avg { color: #dc2626; font-weight: bold; }
        .na { color: #9ca3af; }
        .logo { float: right; }
        .logo img { max-width: 120px; }
        .clearfix::after { content: ""; display: table; clear: both; }
        .footer { margin-top: 40px; font-size: 10px; color: #9ca3af; text-align: center; }
        .generated-note { font-size: 10px; color: #9ca3af; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="clearfix">
        <div class="logo">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" alt="Webmapp">
            @endif
        </div>
        <h1>Performance Report — {{ $developer->name }}</h1>
        <div class="subtitle">Q{{ $quarter }} {{ $year }} · Generato il {{ $generatedAt }}</div>
    </div>

    <h2>Riepilogo metriche</h2>
    <p class="generated-note">I valori sono calcolati sulle storie chiuse (done/released) nel periodo. I valori N/D indicano dati insufficienti.</p>

    <table>
        <thead>
            <tr>
                <th>Metrica</th>
                <th>Valore</th>
                <th>Media team</th>
                <th>Delta</th>
            </tr>
        </thead>
        <tbody>
            @php
            $rows = [
                ['label' => 'Storie chiuse', 'key' => 'story_count', 'format' => fn($v) => $v, 'higher_is_better' => true],
                ['label' => 'Cycle time medio', 'key' => 'avg_cycle_time_minutes', 'format' => fn($v) => $v ? round($v/60,1).'h' : 'N/D', 'higher_is_better' => false],
                ['label' => 'On-time delivery %', 'key' => 'on_time_delivery_rate', 'format' => fn($v) => $v !== null ? $v.'%' : 'N/D', 'higher_is_better' => true],
                ['label' => 'Reopen rate %', 'key' => 'reopen_rate', 'format' => fn($v) => $v !== null ? $v.'%' : 'N/D', 'higher_is_better' => false],
                ['label' => 'Estimation accuracy', 'key' => 'avg_estimation_accuracy', 'format' => fn($v) => $v !== null ? $v.'x' : 'N/D', 'higher_is_better' => null],
                ['label' => 'Scrum follow-through (gg)', 'key' => 'avg_scrum_follow_through', 'format' => fn($v) => $v !== null ? $v : 'N/D', 'higher_is_better' => false],
                ['label' => 'Todo stagnation (gg)', 'key' => 'avg_todo_stagnation', 'format' => fn($v) => $v !== null ? $v : 'N/D', 'higher_is_better' => false],
                ['label' => 'PR change requests (media)', 'key' => 'avg_pr_change_requests', 'format' => fn($v) => $v !== null ? $v : 'N/D', 'higher_is_better' => false],
            ];
            @endphp
            @foreach($rows as $row)
            @php
            $val = $metrics[$row['key']] ?? null;
            $avg = $teamAverages[$row['key']] ?? null;
            $deltaClass = '';
            $deltaText = '—';
            if ($val !== null && $avg !== null && $avg != 0 && $row['key'] !== 'story_count') {
                $diff = $val - $avg;
                $pct = round(abs($diff) / $avg * 100);
                $isGood = $row['higher_is_better'] === true ? $diff >= 0 : ($row['higher_is_better'] === false ? $diff <= 0 : null);
                $deltaClass = $isGood === true ? 'above-avg' : ($isGood === false ? 'below-avg' : '');
                $sign = $diff >= 0 ? '+' : '-';
                $deltaText = "{$sign}{$pct}%";
            }
            @endphp
            <tr>
                <td>{{ $row['label'] }}</td>
                <td>{{ ($row['format'])($val) }}</td>
                <td class="na">{{ ($row['format'])($avg) }}</td>
                <td class="{{ $deltaClass }}">{{ $deltaText }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Webmapp · Dati calcolati da Orchestrator · Snapshot al {{ $generatedAt }}
    </div>
</body>
</html>
