<!-- resources/views/reports/story-user.blade.php -->
<div class="bg-white shadow-md rounded-lg overflow-hidden mb-8">
    <h2 class="text-2xl font-bold text-center mb-4">Analisi delle storie per utente, stato e quarter</h2>

    <!-- Sezione dei Tab -->
    <div class="flex justify-center mb-4">
        <button class="tab-btn px-4 py-2 m-2 bg-blue-500 text-white font-bold" onclick="showTab('year')">Totale Annuo</button>
        @foreach ($availableQuarters as $quarter)
        <button class="tab-btn px-4 py-2 m-2 bg-blue-500 text-white font-bold" onclick="showTab('quarter-{{ $quarter }}')">Q{{ $quarter }}</button>
        @endforeach
    </div>

    <!-- Contenuto dei Tab -->

    <!-- Tab per Totale Annuo -->
    <div id="tab-year" class="tab-content">
        <h3 class="text-xl font-bold text-center mb-4">Totale Annuo - {{ $year }}</h3>
        <table class="min-w-full leading-normal table-auto border-collapse border border-gray-400">
            <thead>
                <tr class="bg-gray-100">
                    <th class="px-5 py-3 border-b-2 border-gray-200 text-gray-600 text-left text-sm uppercase font-semibold">Nome Utente</th>
                    @foreach (App\Enums\StoryStatus::cases() as $status)
                    <th class="px-5 py-3 border-b-2 border-gray-200 text-gray-600 text-left text-sm uppercase font-semibold">{{ ucfirst($status->value) }}</th>
                    @endforeach
                    <th class="px-5 py-3 border-b-2 border-gray-200 text-gray-600 text-left text-sm uppercase font-semibold">Totale</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reportByUser['year'] as $data)
                <tr class="bg-white border-b">
                    <td class="px-5 py-4 text-sm font-bold">{{ $data['user_id'] }}</td>
                    @foreach (App\Enums\StoryStatus::cases() as $status)
                    <td class="px-5 py-4 text-sm">{{ $data[$status->value . '_total'] ?? 0 }}</td>
                    @endforeach
                    <td class="px-5 py-4 text-sm font-bold">{{ $data['total'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Tab per ogni Quarter -->
    @foreach ($availableQuarters as $quarter)
    <div id="tab-quarter-{{ $quarter }}" class="tab-content hidden">
        <h3 class="text-xl font-bold text-center mb-4">Quarter Q{{ $quarter }} - {{ $year }}</h3>
        <table class="min-w-full leading-normal table-auto border-collapse border border-gray-400">
            <thead>
                <tr class="bg-gray-100">
                    <th class="px-5 py-3 border-b-2 border-gray-200 text-gray-600 text-left text-sm uppercase font-semibold">Nome Utente</th>
                    @foreach (App\Enums\StoryStatus::cases() as $status)
                    <th class="px-5 py-3 border-b-2 border-gray-200 text-gray-600 text-left text-sm uppercase font-semibold">{{ ucfirst($status->value) }}</th>
                    @endforeach
                    <th class="px-5 py-3 border-b-2 border-gray-200 text-gray-600 text-left text-sm uppercase font-semibold">Totale</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reportByUser['q' . $quarter] as $data)
                <tr class="bg-white border-b">
                    <td class="px-5 py-4 text-sm font-bold">{{ $data['user_id'] }}</td>
                    @foreach (App\Enums\StoryStatus::cases() as $status)
                    <td class="px-5 py-4 text-sm">{{ $data[$status->value . '_total'] ?? 0 }}</td>
                    @endforeach
                    <td class="px-5 py-4 text-sm font-bold">{{ $data['total'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endforeach
</div>

<!-- Script per gestire i tab -->
<script>
    function showTab(tabId) {
        const contents = document.querySelectorAll('.tab-content');
        contents.forEach(content => content.classList.add('hidden')); // Nascondi tutti i contenuti
        document.getElementById('tab-' + tabId).classList.remove('hidden'); // Mostra solo il tab corrente
    }

    // Mostra il tab del totale annuo per default
    document.addEventListener('DOMContentLoaded', function() {
        showTab('year');
    });
</script>