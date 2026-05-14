# Fix Failing Tests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Portare a zero i test falliti (19 fallimenti, tutti pre-esistenti alle modifiche TagGroup) senza toccare la logica applicativa.

**Architecture:** Quattro categorie di fix indipendenti: (1) migration mancante, (2) due test da aggiornare per la nuova API di Nova ActionResponse, (3) fix idempotenza `attachTagToStory`. Ogni task è autonomo.

**Tech Stack:** Laravel 10, PHPUnit, Laravel Nova ActionResponse, PostgreSQL

---

## File Structure

- Modify: `app/Services/TagService.php` — fix `attachTagToStory` per garantire idempotenza
- Create: `database/migrations/2026_05_14_120000_add_last_login_at_to_users_table.php` — colonna mancante
- Modify: `tests/Feature/DuplicateStoryTest.php:80` — aggiornare asserzione su `openInNewTab`
- Modify: `tests/Feature/ExportStoriesToExcelActionTest.php:90-103` — aggiornare asserzioni su `download`

---

### Task 1: Fix `attachTagToStory` per idempotenza

**Files:**
- Modify: `app/Services/TagService.php`

Il metodo attuale usa `exists()` + `attach()` ma produce 2 righe nel pivot. Fix: usare `syncWithoutDetaching` che è idempotente per definizione.

- [ ] **Step 1: Verifica che il test fallisce**

```bash
docker exec php81_orchestrator php artisan test --filter="attach_tag_to_story_is_idempotent"
```

Expected: FAIL — `Failed asserting that actual size 2 matches expected size 1`

- [ ] **Step 2: Aggiorna `attachTagToStory` in `app/Services/TagService.php`**

Trova il metodo (circa riga 19):
```php
public function attachTagToStory(Story $story, Tag $tag): void
{
    if (! $story->tags()->where('tags.id', $tag->id)->exists()) {
        $story->tags()->attach($tag->id);
    }
}
```

Sostituiscilo con:
```php
public function attachTagToStory(Story $story, Tag $tag): void
{
    $story->tags()->syncWithoutDetaching([$tag->id]);
}
```

- [ ] **Step 3: Verifica che il test passa**

```bash
docker exec php81_orchestrator php artisan test --filter="attach_tag_to_story_is_idempotent"
```

Expected: PASS

- [ ] **Step 4: Verifica che gli altri test TagService passano ancora**

```bash
docker exec php81_orchestrator php artisan test --filter="TagServiceTest"
```

Expected: tutti PASS

---

### Task 2: Migration per colonna `last_login_at` su `users`

**Files:**
- Create: `database/migrations/2026_05_14_120000_add_last_login_at_to_users_table.php`

Il listener `wm-package/src/Listeners/UpdateLastLoginAt.php` aggiorna `users.last_login_at` ma la colonna non esiste. Tutti i 16 test `StoryEmailTriggersTest` falliscono per questo.

- [ ] **Step 1: Verifica che i test falliscono per `last_login_at`**

```bash
docker exec php81_orchestrator php artisan test --filter="StoryEmailTriggersTest" 2>&1 | grep "last_login_at" | head -3
```

Expected: righe con `column "last_login_at" of relation "users" does not exist`

- [ ] **Step 2: Crea la migration**

File: `database/migrations/2026_05_14_120000_add_last_login_at_to_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
```

- [ ] **Step 3: Esegui la migration**

```bash
docker exec php81_orchestrator php artisan migrate
```

Expected: `Migrating: 2026_05_14_120000_add_last_login_at_to_users_table` → `Migrated`

- [ ] **Step 4: Verifica che i 16 test passano**

```bash
docker exec php81_orchestrator php artisan test --filter="StoryEmailTriggersTest"
```

Expected: tutti PASS (16/16)

---

### Task 3: Fix test `DuplicateStoryTest` per nuova struttura Nova

**Files:**
- Modify: `tests/Feature/DuplicateStoryTest.php`

Nova ha cambiato la struttura di `ActionResponse::openInNewTab()`: prima serializzava direttamente con chiave `openInNewTab`, ora wrappa in un oggetto `Redirect` sotto la chiave `redirect`.

Struttura attuale di `$result->jsonSerialize()`:
```php
[
    'redirect' => Redirect { url: '...', openInNewTab: true }
]
```

Il test accede a `$result->jsonSerialize()['openInNewTab']` — chiave inesistente. Va cambiato in `$result->jsonSerialize()['redirect']->jsonSerialize()['openInNewTab']` (o `->url`).

- [ ] **Step 1: Verifica che il test fallisce**

```bash
docker exec php81_orchestrator php artisan test --filter="duplicate_story_with_all_relations"
```

Expected: FAIL — `Undefined array key "openInNewTab"`

- [ ] **Step 2: Aggiorna il test in `tests/Feature/DuplicateStoryTest.php` circa riga 78**

Trova:
```php
$this->assertStringContainsString(
    "/resources/developer-stories/{$newStory->id}/edit",
    $result->jsonSerialize()['openInNewTab']
);
```

Sostituisci con:
```php
$redirectPayload = $result->jsonSerialize()['redirect']->jsonSerialize();
$this->assertTrue($redirectPayload['openInNewTab']);
$this->assertStringContainsString(
    "/resources/developer-stories/{$newStory->id}/edit",
    $redirectPayload['url']
);
```

- [ ] **Step 3: Verifica che il test passa**

```bash
docker exec php81_orchestrator php artisan test --filter="DuplicateStoryTest"
```

Expected: entrambi i test PASS

---

### Task 4: Fix test `ExportStoriesToExcelActionTest` per nuova struttura Nova

**Files:**
- Modify: `tests/Feature/ExportStoriesToExcelActionTest.php`

Nova ha cambiato la struttura di `ActionResponse::download()`: `name` e `url` non sono più direttamente in `$payload` ma dentro un oggetto `DownloadFile` sotto la chiave `download`.

Struttura attuale di `$response->jsonSerialize()`:
```php
[
    'download' => DownloadFile { url: '...', name: '...' }
]
```

Il test accede a `$payload['name']` e `$payload['download']` come stringa — entrambi errati.

- [ ] **Step 1: Verifica che il test fallisce**

```bash
docker exec php81_orchestrator php artisan test --filter="it_stores_export_with_tag_report_filename"
```

Expected: FAIL — `Failed asserting that an array has the key 'name'`

- [ ] **Step 2: Aggiorna il test in `tests/Feature/ExportStoriesToExcelActionTest.php` circa riga 89**

Trova:
```php
$payload = $response->jsonSerialize();
$this->assertArrayHasKey('name', $payload);
$this->assertArrayHasKey('download', $payload);

$expectedFileName = $this->makeReportFileName($tag->name, $date);
$this->assertSame($expectedFileName, $payload['name']);

Excel::assertStored($payload['name'], 'public', function ($export) use ($stories) {
    return $export instanceof SelectedStoriesToExcel
        && $export->collection()->count() === $stories->count();
});
$this->assertStringContainsString($expectedFileName, $payload['download']);
```

Sostituisci con:
```php
$payload = $response->jsonSerialize();
$this->assertArrayHasKey('download', $payload);

$downloadPayload = $payload['download']->jsonSerialize();
$this->assertArrayHasKey('name', $downloadPayload);
$this->assertArrayHasKey('url', $downloadPayload);

$expectedFileName = $this->makeReportFileName($tag->name, $date);
$this->assertSame($expectedFileName, $downloadPayload['name']);

Excel::assertStored($downloadPayload['name'], 'public', function ($export) use ($stories) {
    return $export instanceof SelectedStoriesToExcel
        && $export->collection()->count() === $stories->count();
});
$this->assertStringContainsString($expectedFileName, $downloadPayload['url']);
```

- [ ] **Step 3: Verifica che il test passa**

```bash
docker exec php81_orchestrator php artisan test --filter="ExportStoriesToExcelActionTest"
```

Expected: tutti PASS (7/7)

- [ ] **Step 4: Verifica finale — tutti i test**

```bash
docker exec php81_orchestrator php artisan test 2>&1 | tail -5
```

Expected: `Tests: 0 failed, X passed`
