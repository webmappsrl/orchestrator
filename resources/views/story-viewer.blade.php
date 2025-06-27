<div class="story-viewer-card">
    <h3>Visualizzatore Storie</h3>
    
    @if(isset($stories) && count($stories) > 0)
        <div class="stories-list">
            @foreach($stories as $story)
                <div class="story-item">
                    <h4>Story #{{ $story->id }}</h4>
                    <p><strong>Titolo:</strong> {{ $story->name }}</p>
                    <p><strong>Status:</strong> {{ $story->status }}</p>
                    <p><strong>Creata il:</strong> {{ $story->created_at ? $story->created_at->format('d/m/Y H:i') : 'N/A' }}</p>
                    
                    @if($story->description)
                        <p><strong>Descrizione:</strong></p>
                        <div class="description">
                            {!! Str::limit($story->description, 200) !!}
                        </div>
                    @endif
                    
                    @if($story->customer_request)
                        <p><strong>Richiesta Cliente:</strong></p>
                        <div class="customer-request">
                            {!! Str::limit($story->customer_request, 200) !!}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <p>Nessuna storia disponibile</p>
    @endif
    
    @if(isset($statusLabel))
        <div class="status-info">
            <p><strong>Status Label:</strong> {{ $statusLabel }}</p>
        </div>
    @endif
</div>

<style>
.story-viewer-card {
    padding: 20px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    background-color: #f9f9f9;
    font-family: Arial, sans-serif;
}

.story-item {
    margin-bottom: 20px;
    padding: 15px;
    background-color: white;
    border-radius: 5px;
    border-left: 4px solid #007bff;
}

.story-item h4 {
    margin-top: 0;
    color: #007bff;
}

.description, .customer-request {
    background-color: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    margin-top: 5px;
}

.status-info {
    margin-top: 15px;
    padding: 10px;
    background-color: #e9ecef;
    border-radius: 4px;
}
</style> 