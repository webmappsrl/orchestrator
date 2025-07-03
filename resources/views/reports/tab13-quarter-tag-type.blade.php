<div class="bg-white shadow-md rounded-lg overflow-hidden mb-8" id="tab-13">
    <h2 class="text-2xl font-bold text-center mb-4">Analisi delle storie per progetto, tipo e quarter</h2>
    @include('reports.partials.tab-header', ['id' => 'tab-13'])
    @include('reports.partials.table', [
        'id' => 'tab-year',
        'title' => 'Totale Annuo -' . $year,
        'thead' => $tab13QuarterTagType['thead'],
        'tbody' => $tab13QuarterTagType['tbody']['year'],
    ])
    @foreach ($availableQuarters as $quarter)
        @include('reports.partials.table', [
            'id' => 'tab-quarter-' . $quarter,
            'title' => 'Quarter Q' . $quarter . ' - ' . $year,
            'thead' => $tab13QuarterTagType['thead'],
            'tbody' => $tab13QuarterTagType['tbody']['q' . $quarter],
        ])
    @endforeach
</div>
