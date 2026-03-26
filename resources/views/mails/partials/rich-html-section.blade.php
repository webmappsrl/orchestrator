@if (($showTitle ?? true) && trim((string) ($title ?? '')) !== '')
    <div class="section-title">{{ $title }}</div>
@endif
<div class="description">
    @if ($text !== '')
        {!! $html !!}
    @else
        {{ $fallback }}
    @endif
</div>
