<!-- resources/views/reports/story-user.blade.php -->
<div class="bg-white shadow-md rounded-lg overflow-hidden mb-8">
    <h2 class="text-2xl font-bold text-center mb-4">Analisi delle storie per utente, stato e quarter</h2>

    <!-- Tabella per l'anno intero -->
    <div class="mb-8">
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
                @foreach($reportByUser['year'] as $data) <!-- Utilizziamo reportByUser -->
                <tr class="bg-white border-b">
                    <td class="px-5 py-4 text-sm font-bold">{{ $data['user_id'] }}</td>
                    @foreach (App\Enums\StoryStatus::cases() as $status)
                    <td class="px-5 py-4 text-sm">
                        {{ $data[$status->value . '_total'] ?? 0 }}
                    </td>
                    @endforeach
                    <td class="px-5 py-4 text-sm font-bold">{{ $data['total'] }}</td>
                </tr>
                @endforeach

                <!-- Riga Totale per l'anno -->
                <tr class="bg-gray-200 text-black font-bold">
                    <td class="px-5 py-4 text-sm">TOT</td>
                    @foreach (App\Enums\StoryStatus::cases() as $status)
                    <td class="px-5 py-4 text-sm">{{ $totalOverall }}</td> <!-- Totale complessivo -->
                    @endforeach
                    <td class="px-5 py-4 text-sm font-bold">{{ $totalOverall }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Tabelle per ogni quarter -->
    @foreach ($availableQuarters as $quarter)
    <div class="mb-8">
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
                    <td class="px-5 py-4 text-sm">
                        {{ $data[$status->value . '_total'] ?? 0 }}
                    </td>
                    @endforeach
                    <td class="px-5 py-4 text-sm font-bold">{{ $data['total'] }}</td>
                </tr>
                @endforeach

                <!-- Riga Totale per il quarter -->
                <tr class="bg-gray-200 text-black font-bold">
                    <td class="px-5 py-4 text-sm">TOT</td>
                    @foreach (App\Enums\StoryStatus::cases() as $status)
                    <td class="px-5 py-4 text-sm">{{ $totalOverall }}</td> <!-- Totale complessivo per il quarter -->
                    @endforeach
                    <td class="px-5 py-4 text-sm font-bold">{{ $totalOverall }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endforeach
</div>