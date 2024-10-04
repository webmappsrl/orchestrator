<div class="bg-white shadow-md rounded-lg overflow-hidden mb-8" id="tab-7">
    <h2 class="text-2xl font-bold text-center mb-4">Analisi tag:project/customer</h2>
    @include('reports.partials.tab-header',['id'=> 'tab-7'])
    @include('reports.partials.table', [
    'id'=> 'tab-year',
    'title' => 'Totale Annuo -'.$year,
    'thead' => $tab7TagCustomer['thead'],
    'tbody' => $tab7TagCustomer['tbody']['year']
    ])
    @foreach ($availableQuarters as $quarter)
    @include('reports.partials.table', [
    'id' => 'tab-quarter-'.$quarter,
    'title' => 'Quarter Q' . $quarter . ' - ' . $year,
    'thead' => $tab7TagCustomer['thead'],
    'tbody' => $tab7TagCustomer['tbody']['q' . $quarter]
    ])
    @endforeach
</div>