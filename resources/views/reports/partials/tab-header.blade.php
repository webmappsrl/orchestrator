<!--- resources/views/reports/partials/tab-header.blade.php --->
<div class="flex justify-center mb-4">
    <button class="tab-btn px-4 py-2 m-2 bg-blue-500 text-white font-bold" onclick="showTab('year')">Totale Annuo</button>
    @foreach ($availableQuarters as $quarter)
    <button class="tab-btn px-4 py-2 m-2 bg-blue-500 text-white font-bold" onclick="showTab('quarter-{{ $quarter }}')">Q{{ $quarter }}</button>
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