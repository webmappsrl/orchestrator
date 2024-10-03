<!-- resources/views/reports/story-user.blade.php -->
<div class="bg-white shadow-md rounded-lg overflow-hidden mb-8" id="tab-2">
    <h2 class="text-2xl font-bold text-center mb-4">Analisi dei tickets per status e developer</h2>
    @include('reports.partials.tab-header',[ 'id'=> 'tab-2'])
    @include('reports.partials.table', [
    'title' => 'Totale Annuo -'.$year,
    'thead' => $reportByStatusUser['thead'],
    'tbody' => $reportByStatusUser['tbody']['year']
    ])
    @foreach ($availableQuarters as $quarter)
    @include('reports.partials.table', [
    'title' => 'Quarter Q' . $quarter . ' - ' . $year,
    'thead' => $reportByStatusUser['thead'],
    'tbody' => $reportByStatusUser['tbody']['q' . $quarter]
    ])
    @endforeach
</div>