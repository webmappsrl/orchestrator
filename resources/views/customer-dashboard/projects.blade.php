<div class="bg-white overflow-hidden shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
            <i class="fas fa-project-diagram text-green-500 mr-2"></i>
            I Miei Progetti di Fundraising
        </h3>
        
        @if($projects->count() > 0)
            <div class="space-y-3">
                @foreach($projects as $project)
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900">{{ $project->title }}</h4>
                                <p class="text-sm text-gray-600 mt-1">
                                    <strong>Opportunità:</strong> {{ $project->fundraisingOpportunity->name }}
                                </p>
                                <p class="text-sm text-gray-600">
                                    <strong>Capofila:</strong> {{ $project->leadUser->name }}
                                </p>
                                @if($project->requested_amount)
                                    <p class="text-sm text-blue-600 font-medium">
                                        <strong>Importo Richiesto:</strong> € {{ number_format($project->requested_amount, 2, ',', '.') }}
                                    </p>
                                @endif
                                @if($project->approved_amount)
                                    <p class="text-sm text-green-600 font-medium">
                                        <strong>Importo Approvato:</strong> € {{ number_format($project->approved_amount, 2, ',', '.') }}
                                    </p>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-500 mb-2">
                                    Aggiornato: {{ $project->updated_at->format('d/m/Y') }}
                                </div>
                                @php
                                    $statusClasses = [
                                        'draft' => 'bg-gray-100 text-gray-800',
                                        'submitted' => 'bg-blue-100 text-blue-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                        'completed' => 'bg-purple-100 text-purple-800'
                                    ];
                                    $statusLabels = [
                                        'draft' => 'Bozza',
                                        'submitted' => 'Presentato',
                                        'approved' => 'Approvato',
                                        'rejected' => 'Respinto',
                                        'completed' => 'Completato'
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$project->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $statusLabels[$project->status] ?? ucfirst($project->status) }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            @if($projects->count() >= 5)
                <div class="mt-4 text-center">
                    <a href="/resources/customer-fundraising-projects" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        Vedi tutti i progetti →
                    </a>
                </div>
            @endif
        @else
            <div class="text-center py-8">
                <i class="fas fa-folder-open text-gray-400 text-3xl mb-3"></i>
                <p class="text-gray-500">Nessun progetto di fundraising al momento</p>
                <p class="text-gray-400 text-sm mt-1">I tuoi progetti appariranno qui quando sarai coinvolto</p>
            </div>
        @endif
    </div>
</div>
