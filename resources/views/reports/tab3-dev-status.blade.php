<div class="bg-white shadow-md rounded-lg overflow-hidden mb-8" id="tab-3">
    <h2 class="text-2xl font-bold text-center mb-4">analisi per dev/status</h2>
    @include('reports.partials.tab-header',['id'=> 'tab-3'])
    @include('reports.partials.table', [
    'id'=> 'tab-year',
    'title' => 'Totale Annuo -'.$year,
    'thead' => $tab3DevStatus['thead'],
    'tbody' => $tab3DevStatus['tbody']['year']
    ])
    @foreach ($availableQuarters as $quarter)
    @include('reports.partials.table', [
    'id' => 'tab-quarter-'.$quarter,
    'title' => 'Quarter Q' . $quarter . ' - ' . $year,
    'thead' => $tab3DevStatus['thead'],
    'tbody' => $tab3DevStatus['tbody']['q' . $quarter]
    ])
    @endforeach
</div>