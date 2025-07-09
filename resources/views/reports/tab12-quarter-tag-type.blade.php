<div class="bg-white shadow-md rounded-lg overflow-hidden mb-8" id="tab-12">
    <h2 class="text-2xl font-bold text-center mb-4">Analisi delle storie per progetto, tipo e quarter</h2>
    @include('reports.partials.tab-header', ['id' => 'tab-12'])
    @include('reports.partials.table', [
        'id' => 'tab-year',
        'title' => 'Totale Annuo -' . $year,
        'thead' => $tab12QuarterTagType['thead'],
        'tbody' => $tab12QuarterTagType['tbody']['year'],
    ])
    @foreach ($availableQuarters as $quarter)
        @include('reports.partials.table', [
            'id' => 'tab-quarter-' . $quarter,
            'title' => 'Quarter Q' . $quarter . ' - ' . $year,
            'thead' => $tab12QuarterTagType['thead'],
            'tbody' => $tab12QuarterTagType['tbody']['q' . $quarter],
        ])
    @endforeach
</div>
