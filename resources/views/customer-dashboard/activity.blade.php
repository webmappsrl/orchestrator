<div class="bg-white overflow-hidden shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
            <i class="fas fa-clock text-orange-500 mr-2"></i>
            Attività Recenti
        </h3>
        
        @if($stories->count() > 0)
            <div class="space-y-3">
                @foreach($stories as $story)
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900">{{ $story->name }}</h4>
                                @if($story->fundraisingProject)
                                    <p class="text-sm text-gray-600 mt-1">
                                        <strong>Progetto:</strong> {{ $story->fundraisingProject->title }}
                                    </p>
                                @endif
                                @if($story->description)
                                    <p class="text-sm text-gray-600 mt-1">
                                        {{ Str::limit($story->description, 100) }}
                                    </p>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-500 mb-2">
                                    {{ $story->updated_at->diffForHumans() }}
                                </div>
                                @php
                                    $statusClasses = [
                                        'new' => 'bg-blue-100 text-blue-800',
                                        'assigned' => 'bg-yellow-100 text-yellow-800',
                                        'progress' => 'bg-orange-100 text-orange-800',
                                        'testing' => 'bg-purple-100 text-purple-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800'
                                    ];
                                    $statusLabels = [
                                        'new' => 'Nuovo',
                                        'assigned' => 'Assegnato',
                                        'progress' => 'In Corso',
                                        'testing' => 'In Test',
                                        'completed' => 'Completato',
                                        'rejected' => 'Respinto'
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$story->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $statusLabels[$story->status] ?? ucfirst($story->status) }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            @if($stories->count() >= 5)
                <div class="mt-4 text-center">
                    <a href="/resources/story-showed-by-customers" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        Vedi tutte le attività →
                    </a>
                </div>
            @endif
        @else
            <div class="text-center py-8">
                <i class="fas fa-history text-gray-400 text-3xl mb-3"></i>
                <p class="text-gray-500">Nessuna attività recente</p>
                <p class="text-gray-400 text-sm mt-1">Le tue attività di fundraising appariranno qui</p>
            </div>
        @endif
    </div>
</div>
