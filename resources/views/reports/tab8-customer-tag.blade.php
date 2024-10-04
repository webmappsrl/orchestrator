<!-- resources/views/reports/story-user.blade.php -->
<div class="bg-white shadow-md rounded-lg overflow-hidden mb-8" id="tab-8">
    <h2 class="text-2xl font-bold text-center mb-4">Analisi customer/tag:project</h2>
    @include('reports.partials.tab-header',[ 'id'=> 'tab-8'])
    @include('reports.partials.table', [
    'title' => 'Totale Annuo -'.$year,
    'thead' => $tab8CustomerTag['thead'],
    'tbody' => $tab8CustomerTag['tbody']['year']
    ])
    @foreach ($availableQuarters as $quarter)
    @include('reports.partials.table', [
    'title' => 'Quarter Q' . $quarter . ' - ' . $year,
    'thead' => $tab8CustomerTag['thead'],
    'tbody' => $tab8CustomerTag['tbody']['q' . $quarter]
    ])
    @endforeach
</div>