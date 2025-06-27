{{-- 
    VISTA STORY VIEWER - COMMIT DI FORMAZIONE
    
    PROBLEMA PRINCIPALE RISOLTO:
    Il problema iniziale era nel metodo storyCard() in Main.php:
    
    ❌ SBAGLIATO (codice originale):
    return (new HtmlCard)->view('story-viewer')->withMeta(['stories' => $stories]);
    
    ✅ CORRETTO:
    return (new HtmlCard)->view('story-viewer', ['stories' => $stories]);
    
    SPIEGAZIONE:
    - withMeta() passa i dati come metadati Nova (per JS/componenti), NON come variabili Blade
    - Il secondo parametro di view() passa i dati direttamente alla vista Blade
    - Ecco perché $stories e $statusLabel erano undefined nella vista originale
--}}

<div class="bg-white rounded-lg shadow">
    <div class="px-6 py-4 border-b border-gray-200">
        {{-- 
            BEST PRACTICE: Usare sempre ?? per valori di fallback 
            Evita errori se $statusLabel non è definita
        --}}
        <h3 class="text-lg font-medium text-gray-900">{{ $statusLabel ?? 'Storie' }}</h3>
    </div>
    
    @if(isset($stories) && count($stories) > 0)
        {{-- 
            OTTIMIZZAZIONE UX: Controllo altezza e scroll
            - max-height: 400px limita l'altezza delle card per consistenza visiva
            - overflow-y: auto aggiunge scroll quando necessario
            - Migliora la densità informativa nella dashboard
        --}}
        <div class="overflow-hidden" style="max-height: 400px; overflow-y: auto;">
            <table class="min-w-full divide-y divide-gray-200">
                {{-- 
                    MIGLIORAMENTO UI: Header fisso e contrasto
                    - sticky top-0: mantiene l'header visibile durante lo scroll
                    - text-white: migliora il contrasto con lo sfondo verde Nova
                    - Larghezze definite (w-20, w-1/4, etc.) per layout consistente
                --}}
                <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-20">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-1/4">Titolo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-32">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider w-24">Data</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Descrizione</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($stories as $story)
                        {{-- 
                            INTERATTIVITÀ: Click per aprire dettaglio
                            - cursor-pointer: indica che l'elemento è cliccabile
                            - transition-colors: animazione fluida al hover
                            - onclick: apre la story in nuova tab (UX migliore)
                        --}}
                        <tr class="hover:bg-gray-50 cursor-pointer transition-colors duration-150" onclick="window.open('/resources/stories/{{ $story->id }}', '_blank');">
                            
                            {{-- ID COLUMN: Badge visivo per miglior identificazione --}}
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-indigo-100 text-indigo-800">
                                    #{{ $story->id }}
                                </span>
                            </td>
                            
                            {{-- 
                                TITOLO COLUMN: Informazioni gerarchiche
                                - Titolo principale in bold
                                - Assegnato come informazione secondaria con icona emoji
                                - Controllo relazioni con && per evitare errori
                            --}}
                            <td class="px-4 py-4">
                                <div class="text-sm font-medium text-gray-900 leading-5" title="{{ $story->name }}">
                                    {{ $story->name }}
                                </div>
                                @if($story->assigned_to && $story->assignedTo)
                                    <div class="text-xs text-gray-500 mt-1">
                                        👤 {{ $story->assignedTo->name }}
                                    </div>
                                @endif
                            </td>
                            
                            {{-- 
                                STATUS COLUMN: Badge colorati con logica
                                - Array di configurazione per mantenere consistenza
                                - Fallback per status non previsti (?? operatore)
                                - Icone emoji per identificazione visiva rapida
                                - Colori semantici (rosso=errore, giallo=attenzione, verde=successo)
                            --}}
                            <td class="px-4 py-4 whitespace-nowrap">
                                @php
                                    $statusConfig = [
                                        'todo' => ['class' => 'bg-gray-100 text-gray-800 border-gray-300', 'label' => 'Todo', 'icon' => '📋'],
                                        'progress' => ['class' => 'bg-blue-100 text-blue-800 border-blue-300', 'label' => 'In Progress', 'icon' => '⚡'],
                                        'tobetested' => ['class' => 'bg-yellow-100 text-yellow-800 border-yellow-300', 'label' => 'To Test', 'icon' => '🧪'],
                                        'tested' => ['class' => 'bg-green-100 text-green-800 border-green-300', 'label' => 'Tested', 'icon' => '✅'],
                                    ];
                                    $config = $statusConfig[strtolower($story->status)] ?? ['class' => 'bg-gray-100 text-gray-800 border-gray-300', 'label' => $story->status, 'icon' => '❓'];
                                @endphp
                                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium border {{ $config['class'] }}">
                                    <span class="mr-1">{{ $config['icon'] }}</span>
                                    {{ $config['label'] }}
                                </span>
                            </td>
                            
                            {{-- 
                                DATA COLUMN: Formattazione data user-friendly
                                - Separazione data/ora per leggibilità
                                - Controllo esistenza prima del format per evitare errori
                                - Format italiano d/m/Y
                            --}}
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="text-xs">{{ $story->created_at ? $story->created_at->format('d/m/Y') : 'N/A' }}</div>
                                @if($story->created_at)
                                    <div class="text-xs text-gray-400">{{ $story->created_at->format('H:i') }}</div>
                                @endif
                            </td>
                            
                            {{-- 
                                DESCRIZIONE COLUMN: Gestione contenuto HTML
                                
                                PROBLEMA RISOLTO: Renderizzazione HTML
                                ❌ SBAGLIATO: {{ $story->description }} (escapa l'HTML)
                                ✅ CORRETTO: {!! $story->description !!} (renderizza l'HTML)
                                
                                OTTIMIZZAZIONI IMPLEMENTATE:
                                - max-height: 60px per righe compatte
                                - overflow-y: auto per scroll quando necessario
                                - max-width: 400px per controllo larghezza
                                - Fallback da description a customer_request
                                - Gestione caso vuoto con messaggio esplicativo
                                - Classe prose per tipografia migliorata
                            --}}
                            <td class="px-4 py-4" style="max-width: 400px;">
                                <div class="text-sm text-gray-700 prose prose-sm" style="max-height: 60px; overflow-y: auto; max-width: none;">
                                    @if($story->description)
                                        {{-- IMPORTANTE: {!! !!} renderizza HTML, {{ }} lo escapa --}}
                                        {!! $story->description !!}
                                    @elseif($story->customer_request)
                                        <div class="italic text-gray-600">
                                            {!! $story->customer_request !!}
                                        </div>
                                    @else
                                        <span class="text-gray-400 italic">Nessuna descrizione</span>
                                    @endif
                                </div>
                                {{-- INFORMAZIONE AGGIUNTIVA: Ore stimate quando disponibili --}}
                                @if($story->estimated_hours)
                                    <div class="text-xs text-blue-600 mt-1">
                                        ⏱️ {{ $story->estimated_hours }}h stimate
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        {{-- 
            STATO VUOTO: Design compatto ed efficace
            - py-4 invece di py-12 per risparmiare spazio verticale
            - Icona SVG più piccola (h-8 w-8 invece di h-12 w-12)
            - Messaggio chiaro e diretto
        --}}
        <div class="px-6 py-4 text-center">
            <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="text-sm text-gray-500">Nessuna storia disponibile</p>
        </div>
    @endif
</div>

{{-- 
    RIEPILOGO LEZIONI APPRESE:
    
    1. PASSAGGIO DATI LARAVEL NOVA:
       - withMeta() → metadati Nova (JS)
       - view($nome, $dati) → variabili Blade
    
    2. RENDERIZZAZIONE HTML:
       - {{ }} → escapa HTML (sicuro ma non renderizza)
       - {!! !!} → renderizza HTML (solo per contenuto trusted)
    
    3. GESTIONE ERRORI:
       - Sempre controllare isset() e relazioni
       - Usare ?? per fallback
       - Controllare esistenza prima di chiamare metodi
    
    4. UX/UI BEST PRACTICES:
       - Hover states per feedback visivo
       - Sticky headers per usabilità
       - Colori semantici per status
       - Scroll controllato per layout consistente
       - Click targets chiari
    
    5. PERFORMANCE:
       - Evitare query N+1 (verificare eager loading)
       - Limitare altezze per performance rendering
       - CSS inline solo quando necessario
--}} 