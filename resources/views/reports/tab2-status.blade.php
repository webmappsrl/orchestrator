<!-- resources/views/reports/story-status.blade.php -->
<div class="bg-white shadow-md rounded-lg overflow-hidden mb-8">
    <h2 class="text-2xl font-bold text-center mb-4">Analisi per stato</h2>
    <table class="min-w-full leading-normal table-auto border-collapse border border-gray-400">
        <thead>
            <tr class="bg-gray-100">
                <th class="px-5 py-3 border-b-2 border-gray-200 text-gray-600 text-left text-sm uppercase font-semibold">Status</th>
                <th class="px-5 py-3 border-b-2 border-gray-200 text-gray-600 text-left text-sm uppercase font-semibold">{{ $year }}</th>
                @foreach ($availableQuarters as $quarter)
                <th class="px-5 py-3 border-b-2 border-gray-200 text-gray-600 text-left text-sm uppercase font-semibold">Q{{ $quarter }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($tab2Status as $data)
            <tr class="bg-white border-b">
                <td class="px-5 py-4 text-sm font-bold">{{ ucfirst(__($data['status'])) }}</td>
                <td class="px-5 py-4 text-sm">
                    {{ $data['year_total'] }}<br>
                    <span class="text-gray-500">{{ number_format($data['year_percentage'], 2) }}%</span>
                </td>
                @foreach ($availableQuarters as $quarter)
                <td class="px-5 py-4 text-sm">
                    {{ $data['q' . $quarter] ?? '-' }}<br>
                    <span class="text-gray-500">{{ number_format($data['q' . $quarter . '_percentage'], 2) }}%</span>
                </td>
                @endforeach
            </tr>
            @endforeach

            <!-- Riga Totale -->
            <tr class="bg-gray-200 text-black font-bold">
                <td class="px-5 py-4 text-sm">TOT</td>
                <td class="px-5 py-4 text-sm">{{ $tab2StatusTotals['year_total'] }}</td>
                @foreach ($availableQuarters as $quarter)
                <td class="px-5 py-4 text-sm">{{ $tab2StatusTotals['q' . $quarter] }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
</div>