<div class="bg-white shadow-md rounded-lg overflow-hidden mb-8" id="tab-11">
    <h2 class="text-2xl font-bold text-center mb-4">Analisi tag:project/Developer</h2>
    @include('reports.partials.tab-header', ['id' => 'tab-11'])
    @include('reports.partials.table', [
        'id' => 'tab-year',
        'title' => 'Totale Annuo -' . $year,
        'thead' => $tab11QuarterTagDev['thead'],
        'tbody' => $tab11QuarterTagDev['tbody']['year'],
    ])
    @foreach ($availableQuarters as $quarter)
        @include('reports.partials.table', [
            'id' => 'tab-quarter-' . $quarter,
            'title' => 'Quarter Q' . $quarter . ' - ' . $year,
            'thead' => $tab11QuarterTagDev['thead'],
            'tbody' => $tab11QuarterTagDev['tbody']['q' . $quarter],
        ])
    @endforeach
</div>
