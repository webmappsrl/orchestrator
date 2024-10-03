<!--- resources/views/reports/partials/tab-header.blade.php --->
<div class="flex justify-center mb-4">
    <button class="tab-btn px-4 py-2 m-2 bg-blue-500 text-white font-bold" onclick="showTab('{{$id}}',0)">Totale Annuo</button>
    @foreach ($availableQuarters as $quarter)
    <button class="tab-btn px-4 py-2 m-2 bg-blue-500 text-white font-bold" onclick="showTab('{{$id}}','{{$quarter}}')">Q{{ $quarter }}</button>
    @endforeach
</div>

<!-- Script per gestire i tab -->
<script>
    function showTab(containerId, index) {
        // Seleziona il contenitore
        const container = document.getElementById(containerId);

        // Seleziona tutti i contenuti delle tab all'interno del contenitore
        const contents = container.querySelectorAll('.tab-content');

        // Nascondi tutti i contenuti
        contents.forEach(content => content.classList.add('hidden'));

        // Mostra solo il tab corrente
        contents[index].classList.remove('hidden');
    }

    // Mostra il tab del totale annuo per default
    document.addEventListener('DOMContentLoaded', function() {
        showTab('{{$id}}', 0);
    });
</script>