<!-- resources/views/reports/story-user.blade.php -->
<div class="bg-white shadow-md rounded-lg overflow-hidden mb-8" id="tab-12">
    <h2 class="text-2xl font-bold text-center mb-4">Analisi customer/tag:project</h2>
    @include('reports.partials.tab-header', ['id' => 'tab-12'])
    @include('reports.partials.table', [
        'title' => 'Totale Annuo -' . $year,
        'thead' => $tab12QuarterCustomerTag['thead'],
        'tbody' => $tab12QuarterCustomerTag['tbody']['year'],
    ])
    @foreach ($availableQuarters as $quarter)
        @include('reports.partials.table', [
            'title' => 'Quarter Q' . $quarter . ' - ' . $year,
            'thead' => $tab12QuarterCustomerTag['thead'],
            'tbody' => $tab12QuarterCustomerTag['tbody']['q' . $quarter],
        ])
    @endforeach
</div>
