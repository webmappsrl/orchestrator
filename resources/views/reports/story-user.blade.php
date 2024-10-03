<div class="bg-white shadow-md rounded-lg overflow-hidden mb-8" id="tab-1">
    <h2 class="text-2xl font-bold text-center mb-4">Analisi delle storie per utente, stato e quarter</h2>
    @include('reports.partials.tab-header',['id'=> 'tab-1'])
    @include('reports.partials.table', [
    'id'=> 'tab-year',
    'title' => 'Totale Annuo -'.$year,
    'thead' => $reportByUser['thead'],
    'tbody' => $reportByUser['tbody']['year']
    ])
    @foreach ($availableQuarters as $quarter)
    @include('reports.partials.table', [
    'id' => 'tab-quarter-'.$quarter,
    'title' => 'Quarter Q' . $quarter . ' - ' . $year,
    'thead' => $reportByUser['thead'],
    'tbody' => $reportByUser['tbody']['q' . $quarter]
    ])
    @endforeach
</div>