<div class="bg-white shadow-md rounded-lg overflow-hidden mb-8" id="tab-5">
    <h2 class="text-2xl font-bold text-center mb-4">Analisi delle cliente/status</h2>
    @include('reports.partials.tab-header',['id'=> 'tab-5'])
    @include('reports.partials.table', [
    'id'=> 'tab-year',
    'title' => 'Totale Annuo -'.$year,
    'thead' => $tab5CustomerStatus['thead'],
    'tbody' => $tab5CustomerStatus['tbody']['year']
    ])
    @foreach ($availableQuarters as $quarter)
    @include('reports.partials.table', [
    'id' => 'tab-quarter-'.$quarter,
    'title' => 'Quarter Q' . $quarter . ' - ' . $year,
    'thead' => $tab5CustomerStatus['thead'],
    'tbody' => $tab5CustomerStatus['tbody']['q' . $quarter]
    ])
    @endforeach
</div>