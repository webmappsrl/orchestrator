<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Q&A - Story</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial; margin: 0; padding: 24px; background: #f5f6f8; color: #111827; }
        .card { background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; max-width: 980px; margin: 0 auto 16px auto; }
        label { display:block; font-weight: 600; margin: 12px 0 6px; }
        input, select, textarea { width: 100%; border: 1px solid #d1d5db; border-radius: 10px; padding: 10px 12px; font-size: 14px; }
        textarea { min-height: 120px; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .btn { display: inline-block; background: #111827; color: white; border: 0; padding: 10px 14px; border-radius: 10px; cursor: pointer; font-weight: 600; }
        .muted { color: #6b7280; font-size: 13px; }
        pre { white-space: pre-wrap; word-break: break-word; background: #0b1020; color: #e5e7eb; padding: 14px; border-radius: 12px; }
        .error { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 12px; border-radius: 12px; }
        .toplinks a { color: #111827; text-decoration: none; font-weight: 600; }
        .toplinks a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <div class="toplinks">
            <a href="{{ url('/') }}">Torna a Nova</a>
            <span class="muted"> · </span>
            <a href="{{ url('/resources/stories') }}">Apri elenco Stories</a>
        </div>
        <h2 style="margin: 12px 0 4px;">AI Q&A su Story</h2>
        <div class="muted">
            Domanda libera: l’AI cerca tra tutte le Stories (pgvector) e aggancia Documentation correlata (per tag / nome).
        </div>
    </div>

    <div class="card">
        @if(!empty($error))
            <div class="error"><strong>Errore:</strong> {{ $error }}</div>
        @endif

        <form method="POST" action="{{ route('nova-ai.story.ask') }}">
            @csrf

            <label for="question">Domanda</label>
            <textarea id="question" name="question" required>{{ old('question', $question) }}</textarea>

            <div style="margin-top: 12px;">
                <button class="btn" type="submit">Chiedi</button>
            </div>
        </form>
    </div>

    @if(!empty($answer))
        <div class="card">
            <h3 style="margin: 0 0 10px;">Risposta</h3>
            <pre>{{ $answer }}</pre>
        </div>
    @endif
</body>
</html>

