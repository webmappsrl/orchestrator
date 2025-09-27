<div class="bg-white overflow-hidden shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
            <i class="fas fa-bullhorn text-blue-500 mr-2"></i>
            Opportunità di Finanziamento Attive
        </h3>
        
        @if($opportunities->count() > 0)
            <div class="space-y-3">
                @foreach($opportunities as $opportunity)
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900">{{ $opportunity->name }}</h4>
                                <p class="text-sm text-gray-600 mt-1">
                                    <strong>Sponsor:</strong> {{ $opportunity->sponsor ?? 'Non specificato' }}
                                </p>
                                <p class="text-sm text-gray-600">
                                    <strong>Programma:</strong> {{ $opportunity->program_name ?? 'Non specificato' }}
                                </p>
                                @if($opportunity->max_contribution)
                                    <p class="text-sm text-green-600 font-medium">
                                        <strong>Contributo Max:</strong> € {{ number_format($opportunity->max_contribution, 2, ',', '.') }}
                                    </p>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-500">
                                    Scadenza: {{ $opportunity->deadline->format('d/m/Y') }}
                                </div>
                                @if($opportunity->isExpired())
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Scaduto
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Attivo
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            @if($opportunities->count() >= 5)
                <div class="mt-4 text-center">
                    <a href="/resources/customer-fundraising-opportunities" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        Vedi tutte le opportunità →
                    </a>
                </div>
            @endif
        @else
            <div class="text-center py-8">
                <i class="fas fa-search text-gray-400 text-3xl mb-3"></i>
                <p class="text-gray-500">Nessuna opportunità attiva al momento</p>
            </div>
        @endif
    </div>
</div>
