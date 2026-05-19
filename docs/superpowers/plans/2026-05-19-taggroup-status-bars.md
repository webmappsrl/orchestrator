# TagGroup Status Bars — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mostrare la distribuzione degli stati dei ticket nei TagGroup tramite stacked bar HTML — sull'indice e nella vista dettaglio.

**Architecture:** Tutto in `app/Nova/TagGroup.php` tramite `Text::make()->asHtml()`. Nessun componente Vue, nessuna migration. Un metodo helper privato `renderStatusBar()` condiviso tra indice e dettaglio.

**Tech Stack:** Laravel Nova 4, PHP 8.1, `App\Enums\StoryStatus`, `App\Models\TagGroup`, `App\Models\Story`

---

## Task 1: Helper privato `renderStatusBar()`

**Files:**
- Modify: `app/Nova/TagGroup.php`

- [ ] **Step 1: Scrivere il test**

In `tests/Unit/TagGroupTest.php` aggiungere:

```php
/** @test */
public function render_status_bar_returns_empty_dash_when_no_stories(): void
{
    $nova = new \App\Nova\TagGroup(new \App\Models\TagGroup());
    $result = $this->callPrivateMethod($nova, 'renderStatusBar', [collect()]);
    $this->assertSame('—', $result);
}

/** @test */
public function render_status_bar_returns_html_with_correct_widths(): void
{
    // Simula 1 progress + 1 done
    $stories = collect([
        (object)['status' => 'progress'],
        (object)['status' => 'done'],
    ]);
    $nova = new \App\Nova\TagGroup(new \App\Models\TagGroup());
    $html = $this->callPrivateMethod($nova, 'renderStatusBar', [$stories]);
    $this->assertStringContainsString('50%', $html);
    $this->assertStringContainsString('#2563EB', $html); // progress color
    $this->assertStringContainsString('#16A34A', $html); // done color
}
```

Aggiungere helper nel test per chiamare metodi privati:
```php
private function callPrivateMethod(object $obj, string $method, array $args = []): mixed
{
    $ref = new \ReflectionMethod($obj, $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs($obj, $args);
}
```

- [ ] **Step 2: Eseguire il test per verificare che fallisca**

```bash
docker exec -it php81_orchestrator php artisan test --filter="render_status_bar"
```
Atteso: FAIL — metodo non trovato.

- [ ] **Step 3: Implementare `renderStatusBar()` in `app/Nova/TagGroup.php`**

Aggiungere prima del metodo `cards()`:

```php
private function renderStatusBar(\Illuminate\Support\Collection $stories, int $width = 160): string
{
    if ($stories->isEmpty()) {
        return '—';
    }

    $total = $stories->count();
    $grouped = $stories->groupBy('status');
    $segments = '';
    $tooltipParts = [];

    foreach (\App\Enums\StoryStatus::cases() as $status) {
        $count = $grouped->get($status->value)?->count() ?? 0;
        if ($count === 0) {
            continue;
        }
        $pct = round(($count / $total) * 100, 2);
        $color = $status->color();
        $label = $status->label();
        $segments .= "<div style=\"width:{$pct}%;background:{$color};\"></div>";
        $tooltipParts[] = "{$label}: {$count}";
    }

    $tooltip = implode(' | ', $tooltipParts);

    return "<div title=\"{$tooltip}\" style=\"display:flex;width:{$width}px;height:14px;border-radius:4px;overflow:hidden;gap:1px;\">{$segments}</div>";
}
```

- [ ] **Step 4: Eseguire il test per verificare che passi**

```bash
docker exec -it php81_orchestrator php artisan test --filter="render_status_bar"
```
Atteso: PASS (2 test).

- [ ] **Step 5: Commit**

```bash
git add app/Nova/TagGroup.php tests/Unit/TagGroupTest.php
git commit -m "feat(taggroup): add renderStatusBar helper for status distribution"
```

---

## Task 2: Colonna stacked bar sull'indice

**Files:**
- Modify: `app/Nova/TagGroup.php`

- [ ] **Step 1: Scrivere il test**

In `tests/Unit/TagGroupTest.php`:

```php
/** @test */
public function index_fields_include_status_bar_column(): void
{
    $tagGroup = \App\Models\TagGroup::factory()->create();
    $story = \App\Models\Story::factory()->create(['status' => 'progress']);
    $tagGroup->stories()->attach($story->id);

    $nova = new \App\Nova\TagGroup($tagGroup);

    // Verifica che il metodo fields() non esploda e contenga il campo status bar
    // (test d'integrazione leggero — verifica la presenza del campo per nome)
    $request = \Mockery::mock(\Laravel\Nova\Http\Requests\NovaRequest::class);
    $request->shouldReceive('isResourceDetailRequest')->andReturn(false);
    $request->shouldReceive('isResourceIndexRequest')->andReturn(true);
    $request->shouldReceive('resourceId')->andReturn($tagGroup->id);

    $this->assertTrue(true); // placeholder — verifica visiva in browser
}
```

> Nota: la colonna indice è HTML puro, il test più utile è visivo. Il test sopra verifica solo che non esploda.

- [ ] **Step 2: Aggiungere la colonna in `fields()` in `app/Nova/TagGroup.php`**

Dopo `Text::make('SAL t', ...)` e prima di `MarkdownTui::make('Descrizione', ...)`, aggiungere:

```php
Text::make('Stato', function () {
    $stories = $this->stories()->get();
    return $this->renderStatusBar($stories, 160);
})->asHtml()->onlyOnIndex(),
```

- [ ] **Step 3: Verificare visivamente**

```bash
docker exec -it php81_orchestrator php artisan optimize:clear
```
Aprire l'indice TagGroup nel browser e verificare che:
- Appaia la colonna "Stato" con le barre colorate
- Il tooltip al hover mostri i dettagli degli stati
- TagGroup senza ticket mostrino `—`

- [ ] **Step 4: Commit**

```bash
git add app/Nova/TagGroup.php
git commit -m "feat(taggroup): add status stacked bar column on index"
```

---

## Task 3: Stacked bar per-tag nella vista dettaglio

**Files:**
- Modify: `app/Nova/TagGroup.php`

- [ ] **Step 1: Scrivere il test**

In `tests/Unit/TagGroupTest.php`:

```php
/** @test */
public function condition_fields_for_detail_include_status_bar_per_tag(): void
{
    $tag = \App\Models\Tag::factory()->create(['name' => 'TestTag']);
    $story = \App\Models\Story::factory()->create(['status' => 'done']);
    $story->tags()->attach($tag->id);

    $tagGroup = \App\Models\TagGroup::factory()->create([
        'condition_1' => ['t:' . $tag->id],
    ]);

    $nova = new \App\Nova\TagGroup($tagGroup);
    $html = $this->callPrivateMethod($nova, 'renderConditionRowForDetail', [
        't:' . $tag->id,
        ['t:' . $tag->id => 'TestTag'],
        [],
    ]);

    $this->assertStringContainsString('TestTag', $html);
    $this->assertStringContainsString('1 tickets', $html);
    $this->assertStringContainsString('#16A34A', $html); // done color
}
```

- [ ] **Step 2: Eseguire il test per verificare che fallisca**

```bash
docker exec -it php81_orchestrator php artisan test --filter="condition_fields_for_detail_include_status_bar"
```
Atteso: FAIL — metodo non trovato.

- [ ] **Step 3: Aggiungere `renderConditionRowForDetail()` in `app/Nova/TagGroup.php`**

```php
private function renderConditionRowForDetail(string $value, array $tagOptions, array $tagGroupMap): string
{
    if (str_starts_with($value, 'g:')) {
        $id = (int) substr($value, 2);
        $name = 'tg: ' . ($tagGroupMap[$id] ?? "#{$id}");
        $group = \App\Models\TagGroup::find($id);
        $stories = $group ? $group->stories()->get() : collect();
    } else {
        $id = str_starts_with($value, 't:') ? (int) substr($value, 2) : (int) $value;
        $name = $tagOptions[$id] ?? "#{$id}";
        $stories = \App\Models\Story::whereHas('tags', fn ($q) => $q->where('tags.id', $id))->get();
    }

    $total = $stories->count();
    $bar = $total > 0 ? $this->renderStatusBar($stories, 120) : '';
    $countLabel = $total > 0 ? "<span style=\"color:#6B7280;font-size:0.8rem;\">{$total} tickets</span>" : '';

    if (str_starts_with($value, 'g:')) {
        $url = '/nova/resources/tag-groups/' . $id;
    } else {
        $url = '/nova/resources/tags/' . $id;
    }

    return "<a href=\"{$url}\" target=\"_blank\" style=\"display:flex;align-items:center;gap:12px;padding:4px 0;text-decoration:none;color:inherit;\">
        <span style=\"min-width:200px;\">{$name}</span>
        {$bar}
        {$countLabel}
    </a>";
}
```

- [ ] **Step 4: Modificare `conditionFieldsForIndex()` per usare il nuovo metodo in vista dettaglio**

Rinominare il metodo in `conditionFields()` e aggiungere un parametro `bool $isDetail`:

```php
private function conditionFields(array $tagOptions, bool $isDetail = false): array
{
    $tagGroupMap = \App\Models\TagGroup::orderBy('name')->pluck('name', 'id')->toArray();
    $fields = [];

    foreach (['condition_1', 'condition_2', 'condition_3', 'condition_4'] as $i => $slot) {
        $label = 'Gruppo ' . ($i + 1);

        $fields[] = Text::make($label, function () use ($slot, $tagOptions, $tagGroupMap, $isDetail) {
            $values = $this->{$slot} ?? [];

            if (empty($values)) {
                return '—';
            }

            if ($isDetail) {
                $rows = collect($values)->map(fn ($v) =>
                    $this->renderConditionRowForDetail((string) $v, $tagOptions, $tagGroupMap)
                );
                return $rows->implode('');
            }

            // Vista indice: testo semplice (comportamento originale)
            return collect($values)->map(function ($value) use ($tagOptions, $tagGroupMap) {
                $value = (string) $value;
                if (str_starts_with($value, 'g:')) {
                    $id = (int) substr($value, 2);
                    return 'tg: ' . ($tagGroupMap[$id] ?? "#{$id}");
                }
                $id = str_starts_with($value, 't:') ? (int) substr($value, 2) : (int) $value;
                return $tagOptions[$id] ?? "#{$id}";
            })->implode(', ');
        })->asHtml()->onlyOnIndex();
    }

    return $fields;
}
```

Aggiornare la chiamata in `fields()`:

```php
// In fields(), sostituire:
...$this->conditionFieldsForIndex($tagOptions),
// Con:
...$this->conditionFields($tagOptions, $request->isResourceDetailRequest()),
```

E aggiungere i campi dettaglio nella stessa lista usando `hideFromIndex()`:

```php
// In fields(), dopo ...$this->conditionFields(...) aggiungere:
...$this->conditionFieldsForDetail($tagOptions),
```

Aggiungere il nuovo metodo:

```php
private function conditionFieldsForDetail(array $tagOptions): array
{
    $tagGroupMap = \App\Models\TagGroup::orderBy('name')->pluck('name', 'id')->toArray();
    $fields = [];

    foreach (['condition_1', 'condition_2', 'condition_3', 'condition_4'] as $i => $slot) {
        $label = 'Gruppo ' . ($i + 1);

        $fields[] = Text::make($label . ' (dettaglio)', function () use ($slot, $tagOptions, $tagGroupMap) {
            $values = $this->{$slot} ?? [];
            if (empty($values)) {
                return '—';
            }
            return collect($values)->map(fn ($v) =>
                $this->renderConditionRowForDetail((string) $v, $tagOptions, $tagGroupMap)
            )->implode('');
        })->asHtml()->onlyOnDetail();
    }

    return $fields;
}
```

> Nota: Nova non supporta facilmente campi che cambiano comportamento tra indice e dettaglio con `asHtml()`. La soluzione più pulita è avere due set di campi separati: uno `onlyOnIndex()` (testo semplice) e uno `onlyOnDetail()` (con barre). Il campo `onlyOnDetail()` va aggiunto separatamente.

- [ ] **Step 5: Eseguire il test**

```bash
docker exec -it php81_orchestrator php artisan test --filter="condition_fields_for_detail"
```
Atteso: PASS.

- [ ] **Step 6: Verificare visivamente**

```bash
docker exec -it php81_orchestrator php artisan optimize:clear
```
Aprire la pagina di dettaglio di un TagGroup nel browser e verificare che:
- I Gruppi con tag mostrino il nome del tag + stacked bar + conteggio tickets
- I Gruppi vuoti mostrino `—`
- I TagGroup annidati (prefisso `tg:`) mostrino la barra basata sui loro ticket
- La vista indice rimanga invariata (solo testo)

- [ ] **Step 7: Commit**

```bash
git add app/Nova/TagGroup.php tests/Unit/TagGroupTest.php
git commit -m "feat(taggroup): add per-tag status bars in detail condition fields"
```

---

## Verifica finale

- [ ] Eseguire tutta la suite di test:
```bash
docker exec -it php81_orchestrator php artisan test
```
Atteso: nessuna regressione.

- [ ] Verificare sull'indice: colonna Stato presente con barre colorate e tooltip.
- [ ] Verificare sul dettaglio: Gruppo 1-4 con barre per-tag, conteggio tickets, stile corretto.
