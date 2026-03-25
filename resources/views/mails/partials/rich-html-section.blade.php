<div class="section-title">{{ $title }}</div>
<div class="description">
    @if ($text !== '')
        {!! $html !!}
    @else
        {{ $fallback }}
    @endif
</div>
