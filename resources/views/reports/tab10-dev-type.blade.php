<div class="bg-white shadow-md rounded-lg overflow-hidden mb-8" id="tab-10">
    <h2 class="text-2xl font-bold text-center mb-4">analisi per dev/type</h2>
    @include('reports.partials.tab-header',['id'=> 'tab-10'])
    @include('reports.partials.table', [
    'id'=> 'tab-year',
    'title' => 'Totale Annuo -'.$year,
    'thead' => $tab10DevType['thead'],
    'tbody' => $tab10DevType['tbody']['year']
    ])
    @foreach ($availableQuarters as $quarter)
    @include('reports.partials.table', [
    'id' => 'tab-quarter-'.$quarter,
    'title' => 'Quarter Q' . $quarter . ' - ' . $year,
    'thead' => $tab10DevType['thead'],
    'tbody' => $tab10DevType['tbody']['q' . $quarter]
    ])
    @endforeach
</div>