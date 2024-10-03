<div class="tab-content">
    <h3 class="text-xl font-bold text-center mb-4">{{ $title }}</h3>
    <!-- Contenitore scrollabile per la tabella -->
    <div class="overflow-x-auto">
        <table class="min-w-full leading-normal table-auto border-collapse border border-gray-400">
            @include('reports.partials.thead', ['elements' => $thead])
            <tbody>
                @foreach ($tbody as $row)
                <tr class="bg-white border-b">
                    @foreach ($row as $index => $cell)
                    @php
                    // Ottieni il totale (ultimo elemento della riga)
                    $total = $row[count($row) - 1];

                    // Calcola la percentuale solo se $cell è un numero e non è l'ultima cella
                    $percentage = is_numeric($cell) && $index !== count($row) - 1 && $total > 0
                    ? round(($cell / $total) * 100, 2)
                    : null;
                    @endphp
                    <td class="px-5 py-4 text-sm {{ $index === 0 || $index === count($row) - 1 ? 'font-bold' : '' }}">
                        {{ $cell }}
                        @if ($percentage && $percentage > 0)
                        <br>
                        <span class="text-gray-500">({{ $percentage  . '%'}})</span>
                        @endif
                    </td>

                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>