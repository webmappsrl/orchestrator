<!-- resources/views/reports/story-user.blade.php -->
<div class="bg-white shadow-md rounded-lg overflow-hidden mb-8" id="tab-4">
    <h2 class="text-2xl font-bold text-center mb-4">Analisi dei tickets per status e customer</h2>
    @include('reports.partials.tab-header',[ 'id'=> 'tab-4'])
    @include('reports.partials.table', [
    'title' => 'Totale Annuo -'.$year,
    'thead' => $reportByStatusCustomer['thead'],
    'tbody' => $reportByStatusCustomer['tbody']['year']
    ])
    @foreach ($availableQuarters as $quarter)
    @include('reports.partials.table', [
    'title' => 'Quarter Q' . $quarter . ' - ' . $year,
    'thead' => $reportByStatusCustomer['thead'],
    'tbody' => $reportByStatusCustomer['tbody']['q' . $quarter]
    ])
    @endforeach
</div>