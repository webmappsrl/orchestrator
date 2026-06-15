> Ticket: oc:8028

# Fix Download Allegati — Path Generator Ibrido C→B→A

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ripristinare il download di tutti gli allegati storici senza spostare file, tramite un path generator ibrido che tenta C→B→A con fallback automatico.

**Architecture:** `OrchestratorPathGenerator` implementa `PathGenerator` di Spatie e per ogni media calcola prima il path Layout C (`orchestrator/media/{id}/`), poi controlla se il file esiste su disco; se non esiste prova Layout B (`media/{Model}/{name}/{id}/`) e poi Layout A (`media/{Model}/{name}/`). `AppServiceProvider::register()` sovrascrive dopo il boot di `wm-package` sia `path_generator` che `disk_name` (hardcodato a `public`).

**Tech Stack:** Laravel 10, Spatie Media Library v11, PHPUnit con DatabaseTransactions, Storage::fake.

---

## File Map

| File | Azione |
|------|--------|
| `app/Services/MediaLibrary/OrchestratorPathGenerator.php` | Creare — path generator ibrido C→B→A |
| `app/Providers/AppServiceProvider.php` | Modificare — aggiungere ripristino `path_generator` e `disk_name` |
| `tests/Feature/OrchestratorPathGeneratorTest.php` | Creare — test fallback + test ripristino config |

---

### Task 1: OrchestratorPathGenerator

**Files:**
- Create: `app/Services/MediaLibrary/OrchestratorPathGenerator.php`
- Test: `tests/Feature/OrchestratorPathGeneratorTest.php`

- [ ] **Step 1: Scrivi i test che devono fallire**

Crea `tests/Feature/OrchestratorPathGeneratorTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Services\MediaLibrary\OrchestratorPathGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrchestratorPathGeneratorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    public function test_new_upload_uses_layout_c(): void
    {
        $quote = Quote::factory()->create();
        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');
        $media = $quote->addMedia($file)->toMediaCollection('documents');

        $generator = new OrchestratorPathGenerator();
        $path = $generator->getPath($media);

        $this->assertStringContainsString('orchestrator/media/' . $media->id, $path);
    }

    public function test_falls_back_to_layout_b_when_file_exists_there(): void
    {
        $quote = Quote::factory()->create();
        $file = UploadedFile::fake()->create('legacy_b.pdf', 10, 'application/pdf');
        $media = $quote->addMedia($file)->toMediaCollection('documents');

        // Simula file in Layout B
        $modelName = $quote->name ?? (string) $quote->id;
        $layoutBPath = 'media/Quote/' . $modelName . '/' . $media->id . '/' . $media->file_name;
        Storage::disk('public')->put($layoutBPath, 'content');

        $generator = new OrchestratorPathGenerator();
        $path = $generator->getPath($media);

        $this->assertStringContainsString('media/Quote/' . $modelName . '/' . $media->id, $path);
    }

    public function test_falls_back_to_layout_a_when_file_exists_there(): void
    {
        $quote = Quote::factory()->create();
        $file = UploadedFile::fake()->create('legacy_a.pdf', 10, 'application/pdf');
        $media = $quote->addMedia($file)->toMediaCollection('documents');

        // Simula file in Layout A (cartella condivisa, senza media_id)
        $modelName = $quote->name ?? (string) $quote->id;
        $layoutAPath = 'media/Quote/' . $modelName . '/' . $media->file_name;
        Storage::disk('public')->put($layoutAPath, 'content');

        $generator = new OrchestratorPathGenerator();
        $path = $generator->getPath($media);

        $this->assertStringContainsString('media/Quote/' . $modelName, $path);
        $this->assertStringNotContainsString('/' . $media->id, rtrim($path, '/'));
    }

    public function test_layout_c_takes_priority_over_b_and_a(): void
    {
        $quote = Quote::factory()->create();
        $file = UploadedFile::fake()->create('priority.pdf', 10, 'application/pdf');
        $media = $quote->addMedia($file)->toMediaCollection('documents');

        // Simula file in tutti e tre i layout
        $modelName = $quote->name ?? (string) $quote->id;
        Storage::disk('public')->put('orchestrator/media/' . $media->id . '/' . $media->file_name, 'c');
        Storage::disk('public')->put('media/Quote/' . $modelName . '/' . $media->id . '/' . $media->file_name, 'b');
        Storage::disk('public')->put('media/Quote/' . $modelName . '/' . $media->file_name, 'a');

        $generator = new OrchestratorPathGenerator();
        $path = $generator->getPath($media);

        $this->assertStringContainsString('orchestrator/media/' . $media->id, $path);
    }
}
```

- [ ] **Step 2: Esegui i test — devono fallire**

```bash
docker exec php81_orchestrator php artisan test --filter=OrchestratorPathGeneratorTest
```

Expected: 4 test FAIL con `Class "App\Services\MediaLibrary\OrchestratorPathGenerator" not found`

- [ ] **Step 3: Crea il generator**

Crea `app/Services/MediaLibrary/OrchestratorPathGenerator.php`:

```php
<?php

namespace App\Services\MediaLibrary;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class OrchestratorPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media) . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media) . '/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media) . '/responsive-images/';
    }

    protected function getBasePath(Media $media): string
    {
        $disk = $media->disk ?: config('media-library.disk_name');

        // Layout C — WmfePathGenerator (mag 2026–oggi)
        // orchestrator/media/{id}/
        $layoutC = 'orchestrator/media/' . $media->getKey();
        if (Storage::disk($disk)->exists($layoutC . '/' . $media->file_name)) {
            return $layoutC;
        }

        // Layout B — CustomPathGenerator aggiornato (apr–mag 2026)
        // media/{Model}/{name-or-id}/{media_id}/
        $legacyBase = $this->getLegacyBase($media);
        $layoutB = $legacyBase . '/' . $media->getKey();
        if (Storage::disk($disk)->exists($layoutB . '/' . $media->file_name)) {
            return $layoutB;
        }

        // Layout A — CustomPathGenerator vecchio (fino ad apr 2026)
        // media/{Model}/{name-or-id}/
        if (Storage::disk($disk)->exists($legacyBase . '/' . $media->file_name)) {
            return $legacyBase;
        }

        // Default: nuovi upload → Layout C
        return $layoutC;
    }

    protected function getLegacyBase(Media $media): string
    {
        $prefix = 'media/' . class_basename($media->model_type);
        $model = App::make($media->model_type)->find($media->model_id);
        $folder = ($model && ! empty($model->name)) ? $model->name : (string) $media->model_id;

        return $prefix . '/' . $folder;
    }
}
```

- [ ] **Step 4: Esegui i test — devono passare**

```bash
docker exec php81_orchestrator php artisan test --filter=OrchestratorPathGeneratorTest
```

Expected:
```
PASS  Tests\Feature\OrchestratorPathGeneratorTest
✓ new upload uses layout c
✓ falls back to layout b when file exists there
✓ falls back to layout a when file exists there
✓ layout c takes priority over b and a
Tests: 4 passed
```

---

### Task 2: Ripristino config in AppServiceProvider

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/OrchestratorPathGeneratorTest.php` (aggiunta test config)

- [ ] **Step 1: Aggiungi il test di ripristino config**

Aggiungi questo test in fondo a `tests/Feature/OrchestratorPathGeneratorTest.php`, prima della chiusura `}` della classe:

```php
    public function test_app_service_provider_restores_path_generator_and_disk(): void
    {
        // Simula ciò che fa wm-package: sovrascrive la config
        config([
            'media-library.path_generator' => \Wm\WmPackage\Support\PathGenerator\WmfePathGenerator::class,
            'media-library.disk_name' => 'wmfe',
        ]);

        // Ri-esegui register() come farebbe Laravel al boot
        (new \App\Providers\AppServiceProvider(app()))->register();

        $this->assertEquals(
            \App\Services\MediaLibrary\OrchestratorPathGenerator::class,
            config('media-library.path_generator')
        );
        $this->assertEquals('public', config('media-library.disk_name'));
    }
```

- [ ] **Step 2: Esegui il test — deve fallire**

```bash
docker exec php81_orchestrator php artisan test --filter="test_app_service_provider_restores_path_generator_and_disk"
```

Expected: FAIL — il ripristino non è ancora nel `AppServiceProvider`

- [ ] **Step 3: Modifica AppServiceProvider**

Aggiungi l'import dopo gli import esistenti in `app/Providers/AppServiceProvider.php`:

```php
use App\Services\MediaLibrary\OrchestratorPathGenerator;
```

Nel metodo `register()`, aggiungi dopo il `config(['media-library.media_model' => ...])` esistente:

```php
        // oc:8028 — wm-package sovrascrive path_generator con WmfePathGenerator e disk_name
        // con wmfe (S3), rendendo irraggiungibili i 605/631 media scritti con layout legacy.
        // disk_name è hardcodato a 'public' perché tutti i file storici sono su quel disco.
        config([
            'media-library.path_generator' => OrchestratorPathGenerator::class,
            'media-library.disk_name'      => 'public',
        ]);
```

Il metodo `register()` completo:

```php
    public function register()
    {
        // The wm-package overrides media-library.media_model with
        // Wm\WmPackage\Models\Media, whose observer expects app_id/geometry
        // columns on the 'media' table. This project's media table only has
        // the standard Spatie columns, so force the default Spatie model.
        // AppServiceProvider::register() runs after package auto-discovered
        // providers, so this override wins.
        config(['media-library.media_model' => \Spatie\MediaLibrary\MediaCollections\Models\Media::class]);

        // oc:8028 — wm-package sovrascrive path_generator con WmfePathGenerator e disk_name
        // con wmfe (S3), rendendo irraggiungibili i 605/631 media scritti con layout legacy.
        // disk_name è hardcodato a 'public' perché tutti i file storici sono su quel disco.
        config([
            'media-library.path_generator' => OrchestratorPathGenerator::class,
            'media-library.disk_name'      => 'public',
        ]);
    }
```

- [ ] **Step 4: Esegui tutti i test del file**

```bash
docker exec php81_orchestrator php artisan test --filter=OrchestratorPathGeneratorTest
```

Expected:
```
PASS  Tests\Feature\OrchestratorPathGeneratorTest
✓ new upload uses layout c
✓ falls back to layout b when file exists there
✓ falls back to layout a when file exists there
✓ layout c takes priority over b and a
✓ app service provider restores path generator and disk
Tests: 5 passed
```

- [ ] **Step 5: Verifica che la suite completa non sia rotta**

```bash
docker exec php81_orchestrator php artisan test
```

Expected: tutti i test preesistenti continuano a passare.

- [ ] **Step 6: Commit**

```bash
git add app/Services/MediaLibrary/OrchestratorPathGenerator.php \
        app/Providers/AppServiceProvider.php \
        tests/Feature/OrchestratorPathGeneratorTest.php \
        docs/features/8028-fix-download-allegati-preventivi/
git commit -m "fix(oc:8028): add OrchestratorPathGenerator with C→B→A fallback to restore legacy media downloads"
```
