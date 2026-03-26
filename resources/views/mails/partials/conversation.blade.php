@php
    $rawConversation = trim((string) ($story->customer_request ?? ''));
    $dividerPattern = '/<div[^>]*height:\s*2px[^>]*><\/div>/i';
    $conversationItems = preg_split($dividerPattern, $rawConversation) ?: [];
    $conversationItems = array_values(
        array_filter(array_map(static fn($item) => trim((string) $item), $conversationItems)),
    );
    $hasResponseUpdates = (bool) ($highlightLatest ?? false);
@endphp

@if ($showTitle ?? false)
    <div class="section-title">Conversazione</div>
@endif
<div class="conversation">
    @if (count($conversationItems) > 0)
        @foreach ($conversationItems as $idx => $item)
            <div class="conversation-item {{ $idx === 0 && $hasResponseUpdates ? 'latest' : '' }}">
                @if ($idx === 0 && $hasResponseUpdates)
                    <span class="latest-label">Ultimo aggiornamento</span>
                @endif
                {!! $item !!}
            </div>
        @endforeach
    @else
        <p class="muted">Nessun messaggio disponibile.</p>
    @endif
</div>
