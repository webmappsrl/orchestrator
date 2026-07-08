> Ticket: oc:8242

# Sync distribuita del modello App da tutti gli shard — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Orchestrator sincronizza le app da tutti gli shard (geohub, maphub, camminiditalia, osm2cai) con identità composita `(shard, app_id)`, upsert non distruttivo che preserva il CRM locale, e un endpoint export protetto aggiunto a wm-package.

**Architecture:** Registry di shard in config → due driver HTTP (wmpackage: nuovo endpoint `/api/v1/export/apps`; geohub: legacy `/api/v1/app/all` con mapping esplicito) → `AppSyncService` che fa full-fetch + upsert `saveQuietly` (mai eventi Eloquent) + riconciliazione dismesse con guardie. Nova espone colonna/filtro shard e sync on-demand sul detail.

**Tech Stack:** Laravel 10 + Nova 5.7, PostgreSQL, Redis (lock/throttle), Http client Laravel, wm-package (Orchestra Testbench + Pest per i test package).

## Global Constraints

- Commit convention: `feat(oc:8242): ...` — i commit sono istruzioni per l'utente, MAI eseguiti autonomamente (regola Webmapp: nessun `git commit`/`git push`/branch automatico)
- Test orchestrator: `docker exec php81_orchestrator php artisan test --filter=<Nome>` (DB `orchestrator_test` di default da phpunit.xml; MAI `DB_DATABASE=orchestrator`)
- Test wm-package: `docker exec -w /var/www/html/orchestrator/wm-package php81_orchestrator vendor/bin/pest --filter=<nome>` (richiede `composer install` una tantum in wm-package, vedi Task 1 Step 0)
- La sync NON deve mai scatenare eventi Eloquent (observer `BuildConfJson`, tag automatici): solo `saveQuietly`
- Colonne orchestrator-owned (`user_id` valorizzato, `customer_name`, pivot `user_app`, tag): mai sovrascritte dalla sync dopo la creazione
- `removed_from_shard_at` è sync-owned: la sync la timbra E la azzera
- Branch già creati in entrambi i repo: `feature/oc-8242-sync-distribuita-app-shard`
- File wm-package vanno nel repo submodule `wm-package/`; file orchestrator nel repo principale
- Testi UI Nova: chiavi `__()` con traduzioni in `lang/it.json` e `lang/en.json` (entrambe, mai chiavi mancanti)

---

## Task 1: wm-package — Middleware export token + config

**Files:**
- Modify: `wm-package/config/wm-package.php` (aggiunta chiave `export`)
- Create: `wm-package/src/Http/Middleware/EnsureExportToken.php`
- Test: `wm-package/tests/Api/AppExportApiTest.php` (primi 3 test)

**Interfaces:**
- Produces: middleware `Wm\WmPackage\Http\Middleware\EnsureExportToken` (usato dalle route del Task 2); chiave config `wm-package.export.token` (ENV `WM_EXPORT_TOKEN`)

- [ ] **Step 0: Bootstrap ambiente test wm-package (una tantum)**

Run: `docker exec -w /var/www/html/orchestrator/wm-package php81_orchestrator composer install --no-interaction`
Expected: vendor/ creato. Se fallisse per vincoli di piattaforma, annotare in notes.md e validare i task wm-package solo via test di integrazione orchestrator (Http::fake) + review manuale.

- [ ] **Step 1: Aggiungi la chiave config**

In `wm-package/config/wm-package.php`, dopo la riga `'shard_name' => env('SHARD_NAME', env('APP_NAME')),` aggiungi:

```php
    'export' => [
        // Token statico per l'endpoint export M2M (consumato da Orchestrator, oc:8242).
        // ENV assente = endpoint disabilitato (403). Kill switch senza release.
        'token' => env('WM_EXPORT_TOKEN'),
    ],
```

- [ ] **Step 2: Scrivi i test del middleware (falliranno: route inesistenti)**

Create `wm-package/tests/Api/AppExportApiTest.php`:

```php
<?php

use Wm\WmPackage\Models\App;

beforeEach(function () {
    config(['wm-package.export.token' => 'test-token']);
});

it('returns 403 when export token is not configured on the instance', function () {
    config(['wm-package.export.token' => null]);

    $this->getJson('/api/v1/export/apps', ['Authorization' => 'Bearer test-token'])
        ->assertStatus(403);
});

it('returns 401 when the bearer token is missing or wrong', function () {
    $this->getJson('/api/v1/export/apps')->assertStatus(401);

    $this->getJson('/api/v1/export/apps', ['Authorization' => 'Bearer wrong'])
        ->assertStatus(401);
});

it('returns 200 with the right token', function () {
    App::factory()->create();

    $this->getJson('/api/v1/export/apps', ['Authorization' => 'Bearer test-token'])
        ->assertOk();
});
```

- [ ] **Step 3: Verifica che falliscano**

Run: `docker exec -w /var/www/html/orchestrator/wm-package php81_orchestrator vendor/bin/pest --filter=AppExportApiTest`
Expected: FAIL (404 sulle route, non ancora registrate — è atteso: passeranno a fine Task 2)

- [ ] **Step 4: Implementa il middleware**

Create `wm-package/src/Http/Middleware/EnsureExportToken.php`:

```php
<?php

namespace Wm\WmPackage\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExportToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('wm-package.export.token');

        if (empty($expected)) {
            // Export non configurato su questa istanza: disabilitato by design.
            abort(403, 'Export is not configured on this instance.');
        }

        if (! hash_equals((string) $expected, (string) $request->bearerToken())) {
            abort(401, 'Invalid export token.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 5: Commit (istruzione per l'utente)**

```bash
# repo wm-package
git add config/wm-package.php src/Http/Middleware/EnsureExportToken.php tests/Api/AppExportApiTest.php
git commit -m "feat(oc:8242): add export token middleware and config"
```

---

## Task 2: wm-package — Endpoint export apps (resource + controller + route)

**Files:**
- Create: `wm-package/src/Http/Resources/AppExportResource.php`
- Create: `wm-package/src/Http/Controllers/Api/AppExportController.php`
- Modify: `wm-package/routes/api.php` (route in fondo al file)
- Test: `wm-package/tests/Api/AppExportApiTest.php` (test aggiuntivi)

**Interfaces:**
- Consumes: `EnsureExportToken` (Task 1)
- Produces: contratto v1 — `GET /api/v1/export/apps` (paginata 50/pagina, `?updated_after=` ISO8601, 422 se malformato) e `GET /api/v1/export/apps/{app}`; payload `data[]` con campi: `id, sku, name, customer_name, api, ios_store_link, android_store_link, default_language, available_languages, welcome, dashboard_show, author_name, author_email, created_at, updated_at`. Il driver `WmPackageShardDriver` (Task 5) consuma esattamente questi campi.

- [ ] **Step 1: Aggiungi i test dell'endpoint**

Append a `wm-package/tests/Api/AppExportApiTest.php`:

```php
use Wm\WmPackage\Models\User;

it('lists apps with the v1 contract fields', function () {
    $user = User::factory()->create(['email' => 'owner@example.org', 'name' => 'Owner']);
    App::factory()->create(['user_id' => $user->id, 'customer_name' => 'ACME']);

    $this->getJson('/api/v1/export/apps', ['Authorization' => 'Bearer test-token'])
        ->assertOk()
        ->assertJsonStructure([
            'data' => [[
                'id', 'sku', 'name', 'customer_name', 'api',
                'ios_store_link', 'android_store_link',
                'default_language', 'available_languages', 'welcome',
                'dashboard_show', 'author_name', 'author_email',
                'created_at', 'updated_at',
            ]],
            'links', 'meta',
        ])
        ->assertJsonPath('data.0.author_email', 'owner@example.org');
});

it('filters the list with updated_after', function () {
    $old = App::factory()->create();
    $old->timestamps = false;
    $old->forceFill(['updated_at' => now()->subDays(10)])->saveQuietly();

    App::factory()->create(); // updated_at = now

    $this->getJson('/api/v1/export/apps?updated_after=' . now()->subDay()->toIso8601String(), [
        'Authorization' => 'Bearer test-token',
    ])->assertOk()->assertJsonCount(1, 'data');
});

it('rejects a malformed updated_after with 422', function () {
    $this->getJson('/api/v1/export/apps?updated_after=not-a-date', [
        'Authorization' => 'Bearer test-token',
    ])->assertStatus(422);
});

it('shows a single app', function () {
    $app = App::factory()->create();

    $this->getJson('/api/v1/export/apps/' . $app->id, ['Authorization' => 'Bearer test-token'])
        ->assertOk()
        ->assertJsonPath('data.id', $app->id);
});

it('returns 404 for a missing app', function () {
    $this->getJson('/api/v1/export/apps/999999', ['Authorization' => 'Bearer test-token'])
        ->assertStatus(404);
});
```

- [ ] **Step 2: Verifica che falliscano**

Run: `docker exec -w /var/www/html/orchestrator/wm-package php81_orchestrator vendor/bin/pest --filter=AppExportApiTest`
Expected: FAIL con 404 (route non registrate)

- [ ] **Step 3: Implementa la JsonResource (whitelist = contratto)**

Create `wm-package/src/Http/Resources/AppExportResource.php`:

```php
<?php

namespace Wm\WmPackage\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contratto v1 dell'export apps verso Orchestrator (oc:8242).
 *
 * ⚠️ Questa whitelist È il contratto: aggiungere campi è consentito,
 * rinominare o rimuovere è breaking e richiede /api/v2/export.
 */
class AppExportResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'customer_name' => $this->customer_name,
            'api' => $this->api,
            'ios_store_link' => $this->ios_store_link,
            'android_store_link' => $this->android_store_link,
            'default_language' => $this->default_language,
            'available_languages' => $this->available_languages,
            'welcome' => $this->getTranslations('welcome'),
            'dashboard_show' => (bool) $this->dashboard_show,
            'author_name' => $this->author?->name,
            'author_email' => $this->author?->email,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 4: Implementa il controller**

Create `wm-package/src/Http/Controllers/Api/AppExportController.php` (stesso parent degli altri controller Api del package — verifica l'`extends` di `AppController` e usa lo stesso):

```php
<?php

namespace Wm\WmPackage\Http\Controllers\Api;

use Illuminate\Http\Request;
use Wm\WmPackage\Http\Resources\AppExportResource;
use Wm\WmPackage\Models\App;

class AppExportController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'updated_after' => ['sometimes', 'date'],
        ]);

        $query = App::query()->with('author')->orderBy('id');

        if (isset($validated['updated_after'])) {
            $query->where('updated_at', '>', $validated['updated_after']);
        }

        return AppExportResource::collection($query->paginate(50));
    }

    public function show(App $app)
    {
        $app->load('author');

        return new AppExportResource($app);
    }
}
```

- [ ] **Step 5: Registra le route**

In fondo a `wm-package/routes/api.php` aggiungi (gli `use` in testa al file):

```php
use Wm\WmPackage\Http\Controllers\Api\AppExportController;
use Wm\WmPackage\Http\Middleware\EnsureExportToken;
```

```php
/*
 * Export M2M per Orchestrator (oc:8242) — contratto v1.
 * NB: routes/api.php è registrato dal ServiceProvider sia sotto /api
 * sia sotto /api/v2: l'URL canonico del contratto è /api/v1/export/apps.
 */
Route::prefix('v1/export')->name('export.')
    ->middleware([EnsureExportToken::class, 'throttle:60,1'])
    ->group(function () {
        Route::get('/apps', [AppExportController::class, 'index'])->name('apps.index');
        Route::get('/apps/{app}', [AppExportController::class, 'show'])->name('apps.show');
    });
```

- [ ] **Step 6: Verifica che tutti i test passino**

Run: `docker exec -w /var/www/html/orchestrator/wm-package php81_orchestrator vendor/bin/pest --filter=AppExportApiTest`
Expected: PASS (8 test)

- [ ] **Step 7: Commit (istruzione per l'utente)**

```bash
# repo wm-package
git add src/Http/Resources/AppExportResource.php src/Http/Controllers/Api/AppExportController.php routes/api.php tests/Api/AppExportApiTest.php
git commit -m "feat(oc:8242): add versioned apps export endpoint (v1 contract)"
```

---

## Task 3: orchestrator — Migration identità (shard, app_id) + pulizia modello App

**Files:**
- Create: `database/migrations/2026_07_08_000001_add_shard_identity_to_apps_table.php`
- Create: `database/migrations/2026_07_08_000002_add_sku_to_apps_table.php`
- Modify: `app/Models/App.php`
- Modify: `database/factories/AppFactory.php`
- Test: `tests/Feature/AppShardModelTest.php`

**Interfaces:**
- Produces: colonne `apps.shard` (string NOT NULL), `apps.removed_from_shard_at` (timestamp nullable), `apps.sku` (string nullable); unique composito `(shard, app_id)`; scope `App::active()`; factory con default `shard`/`app_id`. Tutti i task successivi vi si appoggiano.

- [ ] **Step 1: Scrivi il test**

Create `tests/Feature/AppShardModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\App;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AppShardModelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_same_remote_app_id_on_different_shards_is_allowed(): void
    {
        $a = App::factory()->create(['shard' => 'geohub', 'app_id' => '1']);
        $b = App::factory()->create(['shard' => 'maphub', 'app_id' => '1']);

        $this->assertNotSame($a->id, $b->id);
        $this->assertSame(2, App::where('app_id', '1')->count());
    }

    public function test_same_remote_app_id_on_same_shard_is_rejected(): void
    {
        App::factory()->create(['shard' => 'geohub', 'app_id' => '7']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        App::factory()->create(['shard' => 'geohub', 'app_id' => '7']);
    }

    public function test_active_scope_excludes_removed_apps(): void
    {
        App::factory()->create(['shard' => 'geohub', 'app_id' => '10']);
        App::factory()->create(['shard' => 'geohub', 'app_id' => '11', 'removed_from_shard_at' => now()]);

        $this->assertSame(1, App::active()->count());
    }

    public function test_dead_code_is_gone_from_app_model(): void
    {
        foreach (['ugc_medias', 'ugc_pois', 'ugc_tracks', 'getGeojson', 'getMostViewedPoiGeojson', 'getUGCPoiGeojson', 'getUGCMediaGeojson', 'getiUGCTrackGeojson', 'getAppfillables'] as $method) {
            $this->assertFalse(method_exists(App::class, $method), "App::{$method}() dovrebbe essere rimosso");
        }
    }
}
```

- [ ] **Step 2: Verifica che fallisca**

Run: `docker exec php81_orchestrator php artisan test --filter=AppShardModelTest`
Expected: FAIL (colonna shard inesistente, metodi morti presenti)

- [ ] **Step 3: Migration A — identità composita**

Create `database/migrations/2026_07_08_000001_add_shard_identity_to_apps_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * ⚠️ Il down() è per il solo rollback pre-produzione: dopo il primo sync
 * multi-shard possono esistere app_id duplicati tra shard e il ripristino
 * dell'unique semplice fallirebbe. Il rollback operativo è disattivare
 * gli shard in config/shards.php (nessuna perdita dati).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->string('shard')->nullable();
            $table->timestamp('removed_from_shard_at')->nullable();
        });

        // Tutte le app esistenti provengono dall'import geohub.
        DB::table('apps')->update(['shard' => 'geohub']);
        DB::statement('ALTER TABLE apps ALTER COLUMN shard SET NOT NULL');

        Schema::table('apps', function (Blueprint $table) {
            $table->dropUnique('apps_app_id_unique');
            $table->unique(['shard', 'app_id']);
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->dropUnique(['shard', 'app_id']);
            $table->unique('app_id', 'apps_app_id_unique');
            $table->dropColumn(['shard', 'removed_from_shard_at']);
        });
    }
};
```

- [ ] **Step 4: Migration B — colonna sku dal contratto v1**

Create `database/migrations/2026_07_08_000002_add_sku_to_apps_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Allineamento allo schema wm-package: sku è l'identificatore
// applicativo (bundle) sugli shard wm-package. Orchestrator lo
// riceve dal contratto v1 dell'export.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->string('sku')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->dropColumn('sku');
        });
    }
};
```

- [ ] **Step 5: Esegui le migration (DB dev + DB test)**

Run: `docker exec php81_orchestrator php artisan migrate`
Run: `docker exec php81_orchestrator bash -c "DB_DATABASE=orchestrator_test php artisan migrate"`
Expected: entrambe OK, 2 migration applicate ciascuno

- [ ] **Step 6: Pulisci il modello App**

In `app/Models/App.php`:

1. Rimuovi integralmente i metodi: `ugc_medias()`, `ugc_pois()`, `ugc_tracks()`, `getGeojson()`, `getMostViewedPoiGeojson()`, `getUGCPoiGeojson()`, `getUGCMediaGeojson()`, `getiUGCTrackGeojson()`, `getAppfillables()` (righe 59–72, 80–181, 265–274 del file attuale).
2. In `$fillable` aggiungi in coda: `"shard", "removed_from_shard_at", "sku"`.
3. Dopo `$guarded = []` aggiungi cast e scope:

```php
    protected $casts = [
        'removed_from_shard_at' => 'datetime',
    ];

    /**
     * Scope: app presenti sul proprio shard (non dismesse).
     */
    public function scopeActive($query)
    {
        return $query->whereNull('removed_from_shard_at');
    }
```

Non toccare: `boot()` (tag automatici + observer restano per le modifiche manuali da Nova), `BuildConfJson()`, `users()`, `tags()`, `layers()`, `ConfTrait`.

- [ ] **Step 7: Aggiorna la factory**

In `database/factories/AppFactory.php`, sostituisci `definition()`:

```php
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'shard' => 'geohub',
            'app_id' => (string) $this->faker->unique()->numberBetween(1, 100000),
        ];
    }
```

- [ ] **Step 8: Verifica che i test passino**

Run: `docker exec php81_orchestrator php artisan test --filter=AppShardModelTest`
Expected: PASS (4 test)

- [ ] **Step 9: Verifica di non aver rotto la suite esistente**

Run: `docker exec php81_orchestrator php artisan test`
Expected: PASS (eventuali failure preesistenti annotate in notes.md, nessun failure NUOVO)

- [ ] **Step 10: Commit (istruzione per l'utente)**

```bash
git add database/migrations/2026_07_08_000001_add_shard_identity_to_apps_table.php database/migrations/2026_07_08_000002_add_sku_to_apps_table.php app/Models/App.php database/factories/AppFactory.php tests/Feature/AppShardModelTest.php
git commit -m "feat(oc:8242): composite (shard, app_id) identity and App model cleanup"
```

---

## Task 4: orchestrator — Registry shard (config + classi)

**Files:**
- Create: `config/shards.php`
- Create: `app/Services/Shards/Shard.php`
- Create: `app/Services/Shards/ShardRegistry.php`
- Test: `tests/Feature/ShardRegistryTest.php`

**Interfaces:**
- Produces: `Shard` DTO readonly (`slug`, `url`, `driver`, `enabled`, `token`); `ShardRegistry::all(): Collection<Shard>`, `::enabled(): Collection<Shard>`, `::get(string $slug): ?Shard`. Consumati da Task 5–8.

- [ ] **Step 1: Scrivi il test**

Create `tests/Feature/ShardRegistryTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\Shards\Shard;
use App\Services\Shards\ShardRegistry;
use Tests\TestCase;

class ShardRegistryTest extends TestCase
{
    public function test_registry_reads_shards_from_config(): void
    {
        config(['shards' => [
            'alpha' => ['url' => 'https://alpha.test/', 'driver' => 'wmpackage', 'enabled' => true, 'token' => 'tok-a'],
            'beta' => ['url' => 'https://beta.test', 'driver' => 'geohub', 'enabled' => false, 'token' => null],
        ]]);

        $registry = new ShardRegistry();

        $this->assertCount(2, $registry->all());
        $this->assertCount(1, $registry->enabled());

        $alpha = $registry->get('alpha');
        $this->assertInstanceOf(Shard::class, $alpha);
        $this->assertSame('https://alpha.test', $alpha->url); // trailing slash rimosso
        $this->assertSame('tok-a', $alpha->token);
        $this->assertNull($registry->get('missing'));
    }

    public function test_default_config_contains_the_four_seed_shards(): void
    {
        $slugs = array_keys(require base_path('config/shards.php'));

        $this->assertSame(['geohub', 'maphub', 'camminiditalia', 'osm2cai'], $slugs);
    }
}
```

- [ ] **Step 2: Verifica che fallisca**

Run: `docker exec php81_orchestrator php artisan test --filter=ShardRegistryTest`
Expected: FAIL (classi inesistenti)

- [ ] **Step 3: Crea config/shards.php**

```php
<?php

/*
 * Registry degli shard sincronizzati da Orchestrator (oc:8242).
 *
 * ⚠️ Lo slug (chiave dell'array) è IMMUTABILE: entra nell'identità
 * composita (shard, app_id) delle app. Rinominarlo orfanizza tutte le
 * app dello shard (verrebbero dismesse e re-importate da zero, perdendo
 * il CRM locale). Aggiungere nuovi shard è sempre sicuro.
 *
 * 'enabled' => false è il kill switch operativo: ferma la sync dello
 * shard senza perdita dati (rollback senza toccare migration).
 */
return [
    'geohub' => [
        'url' => env('SHARD_URL_GEOHUB', 'https://geohub.webmapp.it'),
        'driver' => 'geohub',
        'enabled' => (bool) env('SHARD_ENABLED_GEOHUB', true),
        'token' => null, // endpoint legacy pubblico
    ],
    'maphub' => [
        'url' => env('SHARD_URL_MAPHUB', 'https://maphub.it'),
        'driver' => 'wmpackage',
        'enabled' => (bool) env('SHARD_ENABLED_MAPHUB', true),
        'token' => env('SHARD_TOKEN_MAPHUB'),
    ],
    'camminiditalia' => [
        'url' => env('SHARD_URL_CAMMINIDITALIA', 'https://camminiditalia.maphub.it'),
        'driver' => 'wmpackage',
        'enabled' => (bool) env('SHARD_ENABLED_CAMMINIDITALIA', true),
        'token' => env('SHARD_TOKEN_CAMMINIDITALIA'),
    ],
    'osm2cai' => [
        'url' => env('SHARD_URL_OSM2CAI', 'https://osm2cai.cai.it'),
        'driver' => 'wmpackage',
        'enabled' => (bool) env('SHARD_ENABLED_OSM2CAI', true),
        'token' => env('SHARD_TOKEN_OSM2CAI'),
    ],
];
```

- [ ] **Step 4: Crea il DTO Shard**

Create `app/Services/Shards/Shard.php`:

```php
<?php

namespace App\Services\Shards;

class Shard
{
    public function __construct(
        public readonly string $slug,
        public readonly string $url,
        public readonly string $driver,
        public readonly bool $enabled,
        public readonly ?string $token = null,
    ) {
    }
}
```

- [ ] **Step 5: Crea ShardRegistry**

Create `app/Services/Shards/ShardRegistry.php`:

```php
<?php

namespace App\Services\Shards;

use Illuminate\Support\Collection;

class ShardRegistry
{
    /** @return Collection<int, Shard> */
    public function all(): Collection
    {
        return collect(config('shards', []))
            ->map(fn (array $cfg, string $slug) => new Shard(
                slug: $slug,
                url: rtrim($cfg['url'], '/'),
                driver: $cfg['driver'],
                enabled: (bool) ($cfg['enabled'] ?? true),
                token: $cfg['token'] ?? null,
            ))
            ->values();
    }

    /** @return Collection<int, Shard> */
    public function enabled(): Collection
    {
        return $this->all()->filter(fn (Shard $shard) => $shard->enabled)->values();
    }

    public function get(string $slug): ?Shard
    {
        return $this->all()->firstWhere('slug', $slug);
    }
}
```

- [ ] **Step 6: Verifica che i test passino**

Run: `docker exec php81_orchestrator php artisan test --filter=ShardRegistryTest`
Expected: PASS (2 test)

- [ ] **Step 7: Commit (istruzione per l'utente)**

```bash
git add config/shards.php app/Services/Shards/Shard.php app/Services/Shards/ShardRegistry.php tests/Feature/ShardRegistryTest.php
git commit -m "feat(oc:8242): shard registry with immutable slugs and kill switch"
```

---

## Task 5: orchestrator — Driver shard (wmpackage + geohub legacy)

**Files:**
- Create: `app/Services/Shards/ShardDriver.php` (interfaccia)
- Create: `app/Services/Shards/WmPackageShardDriver.php`
- Create: `app/Services/Shards/GeohubShardDriver.php`
- Create: `app/Services/Shards/ShardDriverFactory.php`
- Test: `tests/Feature/ShardDriversTest.php`

**Interfaces:**
- Consumes: `Shard` (Task 4); contratto v1 export (Task 2)
- Produces: `ShardDriver::fetchApps(Shard): ?array` (lista di array `[colonna apps => valore]`, `app_id` sempre presente come stringa; `null` = risposta invalida) e `::fetchApp(Shard, string $remoteId): ?array`; `ShardDriverFactory::for(Shard): ShardDriver`. Consumati da `AppSyncService` (Task 6).

- [ ] **Step 1: Scrivi i test**

Create `tests/Feature/ShardDriversTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\Shards\GeohubShardDriver;
use App\Services\Shards\Shard;
use App\Services\Shards\WmPackageShardDriver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShardDriversTest extends TestCase
{
    private function wmShard(): Shard
    {
        return new Shard(slug: 'maphub', url: 'https://maphub.test', driver: 'wmpackage', enabled: true, token: 'tok');
    }

    private function geohubShard(): Shard
    {
        return new Shard(slug: 'geohub', url: 'https://geohub.test', driver: 'geohub', enabled: true);
    }

    public function test_wmpackage_driver_normalizes_the_v1_contract(): void
    {
        Http::fake([
            'https://maphub.test/api/v1/export/apps' => Http::response([
                'data' => [[
                    'id' => 12, 'sku' => 'it.webmapp.demo', 'name' => 'Demo',
                    'customer_name' => 'ACME', 'api' => 'elbrus',
                    'ios_store_link' => null, 'android_store_link' => null,
                    'default_language' => 'it', 'available_languages' => ['it', 'en'],
                    'welcome' => ['it' => 'Ciao'], 'dashboard_show' => true,
                    'author_name' => 'Owner', 'author_email' => 'owner@example.org',
                    'created_at' => '2026-01-01T00:00:00+00:00', 'updated_at' => '2026-06-01T00:00:00+00:00',
                ]],
                'links' => ['next' => null],
                'meta' => [],
            ]),
        ]);

        $apps = (new WmPackageShardDriver())->fetchApps($this->wmShard());

        $this->assertCount(1, $apps);
        $this->assertSame('12', $apps[0]['app_id']);
        $this->assertSame('it.webmapp.demo', $apps[0]['sku']);
        $this->assertSame('owner@example.org', $apps[0]['user_email']);
        $this->assertJson($apps[0]['available_languages']);
    }

    public function test_wmpackage_driver_follows_pagination(): void
    {
        Http::fake([
            'https://maphub.test/api/v1/export/apps?page=2' => Http::response([
                'data' => [['id' => 2, 'name' => 'B']],
                'links' => ['next' => null],
            ]),
            'https://maphub.test/api/v1/export/apps' => Http::response([
                'data' => [['id' => 1, 'name' => 'A']],
                'links' => ['next' => 'https://maphub.test/api/v1/export/apps?page=2'],
            ]),
        ]);

        $apps = (new WmPackageShardDriver())->fetchApps($this->wmShard());

        $this->assertSame(['1', '2'], array_column($apps, 'app_id'));
    }

    public function test_wmpackage_driver_returns_null_on_error_or_missing_token(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);

        $this->assertNull((new WmPackageShardDriver())->fetchApps($this->wmShard()));

        $noToken = new Shard(slug: 'maphub', url: 'https://maphub.test', driver: 'wmpackage', enabled: true, token: null);
        $this->assertNull((new WmPackageShardDriver())->fetchApps($noToken));
    }

    public function test_geohub_driver_maps_the_legacy_payload(): void
    {
        Http::fake([
            'https://geohub.test/api/v1/app/all' => Http::response([
                ['id' => 50, 'app_id' => null, 'name' => 'Parco', 'user_id' => 21697,
                 'user_email' => 'parco@webmapp.it', 'customer_name' => 'Parco',
                 'available_languages' => ['it'], 'campo_ignoto_futuro' => 'x'],
            ]),
        ]);

        $apps = (new GeohubShardDriver())->fetchApps($this->geohubShard());

        $this->assertCount(1, $apps);
        $this->assertSame('50', $apps[0]['app_id']); // fallback su id remoto
        $this->assertSame('parco@webmapp.it', $apps[0]['user_email']);
        $this->assertArrayNotHasKey('user_id', $apps[0]); // mai la FK remota
        $this->assertArrayNotHasKey('campo_ignoto_futuro', $apps[0]); // whitelist, non pass-through
    }

    public function test_geohub_driver_returns_null_on_invalid_payload(): void
    {
        Http::fake(['https://geohub.test/api/v1/app/all' => Http::response('not json array', 200)]);

        $this->assertNull((new GeohubShardDriver())->fetchApps($this->geohubShard()));
    }
}
```

- [ ] **Step 2: Verifica che falliscano**

Run: `docker exec php81_orchestrator php artisan test --filter=ShardDriversTest`
Expected: FAIL (classi inesistenti)

- [ ] **Step 3: Interfaccia + factory**

Create `app/Services/Shards/ShardDriver.php`:

```php
<?php

namespace App\Services\Shards;

interface ShardDriver
{
    /**
     * Lista completa delle app dello shard, normalizzate come array
     * [colonna apps => valore] con 'app_id' sempre presente (stringa).
     * Ritorna null se la risposta è invalida (≠ lista vuota legittima:
     * anche quella viene trattata come no-op dal chiamante).
     */
    public function fetchApps(Shard $shard): ?array;

    /**
     * Singola app per id remoto (timeout corto: usata dal detail Nova).
     * Null se non trovata, non configurata o errore.
     */
    public function fetchApp(Shard $shard, string $remoteId): ?array;
}
```

Create `app/Services/Shards/ShardDriverFactory.php`:

```php
<?php

namespace App\Services\Shards;

use InvalidArgumentException;

class ShardDriverFactory
{
    public function for(Shard $shard): ShardDriver
    {
        return match ($shard->driver) {
            'geohub' => app(GeohubShardDriver::class),
            'wmpackage' => app(WmPackageShardDriver::class),
            default => throw new InvalidArgumentException("Driver shard sconosciuto: {$shard->driver}"),
        };
    }
}
```

- [ ] **Step 4: Driver wmpackage**

Create `app/Services/Shards/WmPackageShardDriver.php`:

```php
<?php

namespace App\Services\Shards;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WmPackageShardDriver implements ShardDriver
{
    public function fetchApps(Shard $shard): ?array
    {
        if (empty($shard->token)) {
            Log::warning("Shard [{$shard->slug}]: SHARD_TOKEN mancante, sync saltata");

            return null;
        }

        $url = $shard->url . '/api/v1/export/apps';
        $apps = [];

        do {
            $response = Http::timeout(30)->acceptJson()->withToken($shard->token)->get($url);

            if (! $response->ok()) {
                return null;
            }

            $json = $response->json();
            if (! is_array($json) || ! array_key_exists('data', $json)) {
                return null;
            }

            foreach ($json['data'] as $element) {
                $normalized = $this->normalize($element);
                if ($normalized !== null) {
                    $apps[] = $normalized;
                }
            }

            $url = $json['links']['next'] ?? null;
        } while ($url);

        return $apps;
    }

    public function fetchApp(Shard $shard, string $remoteId): ?array
    {
        if (empty($shard->token)) {
            return null;
        }

        $response = Http::timeout(3)->acceptJson()->withToken($shard->token)
            ->get($shard->url . '/api/v1/export/apps/' . $remoteId);

        if (! $response->ok()) {
            return null;
        }

        $data = $response->json('data');

        return is_array($data) ? $this->normalize($data) : null;
    }

    /** Mapping contratto v1 → colonne apps di Orchestrator. */
    private function normalize(mixed $element): ?array
    {
        if (! is_array($element) || empty($element['id'])) {
            return null;
        }

        return [
            'app_id' => (string) $element['id'],
            'sku' => $element['sku'] ?? null,
            'name' => $element['name'] ?? null,
            'customer_name' => $element['customer_name'] ?? null,
            'user_email' => $element['author_email'] ?? null,
            'api' => $element['api'] ?? null,
            'ios_store_link' => $element['ios_store_link'] ?? null,
            'android_store_link' => $element['android_store_link'] ?? null,
            'default_language' => $element['default_language'] ?? null,
            'available_languages' => $this->jsonOrNull($element['available_languages'] ?? null),
            'welcome' => $this->jsonOrNull($element['welcome'] ?? null),
            'dashboard_show' => $element['dashboard_show'] ?? null,
        ];
    }

    private function jsonOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_array($value) ? json_encode($value) : (string) $value;
    }
}
```

- [ ] **Step 5: Driver geohub legacy**

Create `app/Services/Shards/GeohubShardDriver.php`:

```php
<?php

namespace App\Services\Shards;

use Illuminate\Support\Facades\Http;

class GeohubShardDriver implements ShardDriver
{
    /**
     * Whitelist esplicita payload geohub → colonne apps (mai pass-through:
     * campi remoti sconosciuti vengono ignorati, campi assenti restano NULL).
     * user_id remoto ESCLUSO by design: è FK locale orchestrator-owned.
     */
    private const FIELDS = [
        'name', 'customer_name', 'user_email', 'api', 'ios_store_link', 'android_store_link',
        'default_language', 'available_languages', 'welcome', 'page_project',
        'map_max_zoom', 'map_min_zoom', 'map_def_zoom', 'map_bbox',
        'primary_color', 'default_feature_color', 'font_family_header', 'font_family_content',
        'icon', 'splash', 'icon_small', 'feature_image', 'logo_homepage', 'icon_notify',
        'start_url', 'show_edit_link', 'poi_min_zoom', 'show_track_ref_label', 'enable_routing',
        'auth_show_at_startup', 'offline_enable', 'offline_force_auth', 'geolocation_record_enable',
        'tracks_on_payment', 'config_home', 'app_pois_api_layer', 'tiles',
        'start_end_icons_show', 'start_end_icons_min_zoom', 'ref_on_track_show', 'ref_on_track_min_zoom',
        'alert_poi_show', 'alert_poi_radius', 'social_track_text', 'draw_track_show',
        'iconmoon_selection', 'editing_inline_show', 'flow_line_quote_show', 'flow_line_quote_orange',
        'flow_line_quote_red', 'map_max_stroke_width', 'map_min_stroke_width',
        'download_track_enable', 'dashboard_show', 'print_track_enable', 'poi_interaction',
        'external_overlays',
    ];

    public function fetchApps(Shard $shard): ?array
    {
        return $this->fetch($shard, 30);
    }

    public function fetchApp(Shard $shard, string $remoteId): ?array
    {
        // Il geohub non espone lettura singola: full fetch (timeout corto) e filtro.
        $apps = $this->fetch($shard, 3);

        return collect($apps ?? [])->firstWhere('app_id', $remoteId);
    }

    private function fetch(Shard $shard, int $timeout): ?array
    {
        $response = Http::timeout($timeout)->acceptJson()->get($shard->url . '/api/v1/app/all');

        if (! $response->ok() || ! is_array($response->json())) {
            return null;
        }

        $apps = [];
        foreach ($response->json() as $element) {
            $normalized = $this->normalize($element);
            if ($normalized !== null) {
                $apps[] = $normalized;
            }
        }

        return $apps;
    }

    private function normalize(mixed $element): ?array
    {
        if (! is_array($element)) {
            return null;
        }

        $remoteId = $element['app_id'] ?? $element['id'] ?? null;
        if ($remoteId === null || $remoteId === '') {
            return null;
        }

        $attributes = ['app_id' => (string) $remoteId];

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $element)) {
                $attributes[$field] = is_array($element[$field])
                    ? json_encode($element[$field])
                    : $element[$field];
            }
        }

        return $attributes;
    }
}
```

- [ ] **Step 6: Verifica che i test passino**

Run: `docker exec php81_orchestrator php artisan test --filter=ShardDriversTest`
Expected: PASS (5 test)

- [ ] **Step 7: Commit (istruzione per l'utente)**

```bash
git add app/Services/Shards/ShardDriver.php app/Services/Shards/ShardDriverFactory.php app/Services/Shards/WmPackageShardDriver.php app/Services/Shards/GeohubShardDriver.php tests/Feature/ShardDriversTest.php
git commit -m "feat(oc:8242): wmpackage and legacy geohub shard drivers"
```

---

## Task 6: orchestrator — AppSyncService (upsert, auto-link, riconciliazione con guardie)

**Files:**
- Create: `app/Services/Shards/AppSyncService.php`
- Test: `tests/Feature/AppShardSyncTest.php`

**Interfaces:**
- Consumes: `ShardDriverFactory` (Task 5), `Shard` (Task 4), scope `App::active()` (Task 3)
- Produces: `AppSyncService::syncShard(Shard): ?array` (`['synced' => int, 'created' => int, 'removed' => int]`, `null` = no-op per payload invalido/vuoto) e `::syncOne(Shard, string $remoteId): bool`. Consumati da Task 7 e 8.

- [ ] **Step 1: Scrivi i test (il cuore della feature)**

Create `tests/Feature/AppShardSyncTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\Tag;
use App\Models\User;
use App\Services\Shards\AppSyncService;
use App\Services\Shards\Shard;
use App\Services\Shards\ShardDriverFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppShardSyncTest extends TestCase
{
    use DatabaseTransactions;

    private function shard(string $slug = 'maphub'): Shard
    {
        return new Shard(slug: $slug, url: "https://{$slug}.test", driver: 'wmpackage', enabled: true, token: 'tok');
    }

    private function sync(): AppSyncService
    {
        return new AppSyncService(new ShardDriverFactory());
    }

    private function fakeShardApps(string $slug, array $apps): void
    {
        Http::fake([
            "https://{$slug}.test/api/v1/export/apps" => Http::response([
                'data' => $apps,
                'links' => ['next' => null],
            ]),
        ]);
    }

    public function test_sync_creates_apps_and_same_remote_id_coexists_across_shards(): void
    {
        App::factory()->create(['shard' => 'geohub', 'app_id' => '1', 'name' => 'Geohub app']);

        $this->fakeShardApps('maphub', [['id' => 1, 'name' => 'Maphub app']]);

        $result = $this->sync()->syncShard($this->shard());

        $this->assertSame(['synced' => 1, 'created' => 1, 'removed' => 0], $result);
        $this->assertSame(2, App::where('app_id', '1')->count());
    }

    public function test_second_sync_updates_shard_fields_but_preserves_local_crm(): void
    {
        $this->fakeShardApps('maphub', [['id' => 5, 'name' => 'Prima', 'customer_name' => 'Remoto', 'author_email' => 'x@y.z']]);
        $this->sync()->syncShard($this->shard());

        // CRM curato a mano su Orchestrator
        $app = App::where('shard', 'maphub')->where('app_id', '5')->first();
        $localUser = User::factory()->create();
        $app->forceFill(['user_id' => $localUser->id, 'customer_name' => 'Cliente CRM'])->saveQuietly();

        Http::clearResolvedInstances();
        $this->fakeShardApps('maphub', [['id' => 5, 'name' => 'Dopo', 'customer_name' => 'Remoto cambiato', 'author_email' => 'nuovo@y.z']]);
        $this->sync()->syncShard($this->shard());

        $app->refresh();
        $this->assertSame('Dopo', $app->name);              // shard-owned: aggiornato
        $this->assertSame('nuovo@y.z', $app->user_email);   // shard-owned: aggiornato
        $this->assertSame('Cliente CRM', $app->customer_name); // orchestrator-owned: intatto
        $this->assertSame($localUser->id, $app->user_id);      // orchestrator-owned: intatto
    }

    public function test_auto_link_populates_user_id_by_email_only_when_null(): void
    {
        $user = User::factory()->create(['email' => 'Owner@Example.ORG']);

        $this->fakeShardApps('maphub', [['id' => 9, 'name' => 'X', 'author_email' => 'owner@example.org']]);
        $this->sync()->syncShard($this->shard());

        $this->assertSame($user->id, App::where('shard', 'maphub')->where('app_id', '9')->first()->user_id);
    }

    public function test_invalid_or_empty_payload_is_a_noop(): void
    {
        App::factory()->create(['shard' => 'maphub', 'app_id' => '1']);

        $this->fakeShardApps('maphub', []);
        $this->assertNull($this->sync()->syncShard($this->shard()));

        $this->assertNull(App::where('shard', 'maphub')->first()->removed_from_shard_at);
    }

    public function test_reconciliation_guard_aborts_mass_removals(): void
    {
        foreach (range(1, 10) as $i) {
            App::factory()->create(['shard' => 'maphub', 'app_id' => (string) $i]);
        }

        // Solo 5 app su 10 nel payload → 5 rimozioni = 50% > 30%: abort
        $this->fakeShardApps('maphub', array_map(fn ($i) => ['id' => $i, 'name' => "App $i"], range(1, 5)));
        $result = $this->sync()->syncShard($this->shard());

        $this->assertSame(0, $result['removed']);
        $this->assertSame(0, App::whereNotNull('removed_from_shard_at')->count());
    }

    public function test_missing_app_is_stamped_and_reappearing_app_is_reactivated(): void
    {
        foreach (range(1, 10) as $i) {
            App::factory()->create(['shard' => 'maphub', 'app_id' => (string) $i]);
        }

        // 9 app su 10: la mancante (10%) viene dismessa
        $this->fakeShardApps('maphub', array_map(fn ($i) => ['id' => $i, 'name' => "App $i"], range(1, 9)));
        $result = $this->sync()->syncShard($this->shard());

        $this->assertSame(1, $result['removed']);
        $removed = App::where('shard', 'maphub')->where('app_id', '10')->first();
        $this->assertNotNull($removed->removed_from_shard_at);

        // L'app ricompare → riattivata
        Http::clearResolvedInstances();
        $this->fakeShardApps('maphub', array_map(fn ($i) => ['id' => $i, 'name' => "App $i"], range(1, 10)));
        $this->sync()->syncShard($this->shard());

        $this->assertNull($removed->refresh()->removed_from_shard_at);
    }

    public function test_sync_never_fires_eloquent_events(): void
    {
        $this->fakeShardApps('maphub', [['id' => 77, 'name' => 'Quiet']]);
        $this->sync()->syncShard($this->shard());

        $app = App::where('shard', 'maphub')->where('app_id', '77')->first();

        // Il hook created() del modello creerebbe un Tag: non deve esistere.
        $this->assertSame(0, Tag::where('taggable_type', App::class)->where('taggable_id', $app->id)->count());
    }
}
```

- [ ] **Step 2: Verifica che falliscano**

Run: `docker exec php81_orchestrator php artisan test --filter=AppShardSyncTest`
Expected: FAIL (`AppSyncService` inesistente)

- [ ] **Step 3: Implementa AppSyncService**

Create `app/Services/Shards/AppSyncService.php`:

```php
<?php

namespace App\Services\Shards;

use App\Models\App;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AppSyncService
{
    /**
     * Colonne CRM orchestrator-owned: seminate alla creazione,
     * MAI aggiornate dalla sync sui record esistenti.
     */
    private const SEED_ONLY = ['customer_name'];

    /** Guardia riconciliazione: max frazione di app attive dismissibili in un giro. */
    private const MAX_REMOVAL_RATIO = 0.30;

    public function __construct(private readonly ShardDriverFactory $drivers)
    {
    }

    /**
     * Full sync di uno shard: upsert + riconciliazione dismesse.
     *
     * @return array{synced: int, created: int, removed: int}|null
     *         null = no-op (payload invalido o vuoto: non si tocca nulla)
     */
    public function syncShard(Shard $shard): ?array
    {
        $apps = $this->drivers->for($shard)->fetchApps($shard);

        if ($apps === null || $apps === []) {
            Log::warning("apps:sync [{$shard->slug}] payload invalido o vuoto — nessuna azione");

            return null;
        }

        $created = 0;
        $seenRemoteIds = [];

        foreach ($apps as $attributes) {
            $seenRemoteIds[] = $attributes['app_id'];
            if ($this->upsert($shard, $attributes)) {
                $created++;
            }
        }

        $removed = $this->reconcile($shard, $seenRemoteIds);

        return ['synced' => count($apps), 'created' => $created, 'removed' => $removed];
    }

    /** Sync on-demand di una singola app (detail Nova). */
    public function syncOne(Shard $shard, string $remoteId): bool
    {
        $attributes = $this->drivers->for($shard)->fetchApp($shard, $remoteId);

        if ($attributes === null) {
            return false;
        }

        $this->upsert($shard, $attributes);

        return true;
    }

    /** @return bool true se l'app è stata creata */
    private function upsert(Shard $shard, array $attributes): bool
    {
        $app = App::where('shard', $shard->slug)
            ->where('app_id', $attributes['app_id'])
            ->first();

        $wasCreated = $app === null;

        if ($wasCreated) {
            $app = new App();
            $app->shard = $shard->slug;
        } else {
            foreach (self::SEED_ONLY as $column) {
                unset($attributes[$column]);
            }
        }

        $app->fill($attributes);

        // Sync-owned: presente nel payload dello shard → attiva (riattivazione inclusa).
        $app->removed_from_shard_at = null;

        $this->autoLinkUser($app);

        // Mai eventi Eloquent dalla sync: niente observer, tag automatici o BuildConfJson.
        $app->saveQuietly();

        return $wasCreated;
    }

    private function autoLinkUser(App $app): void
    {
        if ($app->user_id !== null || empty($app->user_email)) {
            return;
        }

        $user = User::whereRaw('lower(email) = ?', [mb_strtolower($app->user_email)])->first();

        if ($user !== null) {
            $app->user_id = $user->id;
        }
    }

    /** @return int numero di app dismesse */
    private function reconcile(Shard $shard, array $seenRemoteIds): int
    {
        $missing = App::where('shard', $shard->slug)
            ->active()
            ->whereNotIn('app_id', $seenRemoteIds)
            ->get();

        if ($missing->isEmpty()) {
            return 0;
        }

        $activeCount = App::where('shard', $shard->slug)->active()->count();

        if ($missing->count() / max($activeCount, 1) > self::MAX_REMOVAL_RATIO) {
            Log::error("apps:sync [{$shard->slug}] guardia riconciliazione: {$missing->count()}/{$activeCount} rimozioni in un giro — abort");

            return 0;
        }

        foreach ($missing as $app) {
            $app->removed_from_shard_at = now();
            $app->saveQuietly();
        }

        return $missing->count();
    }
}
```

Nota sull'ordine delle operazioni in `reconcile()`: viene chiamato DOPO gli upsert, quindi le app appena viste sono già attive — `activeCount` include anche le `missing` (non ancora timbrate), il rapporto è calcolato sul totale attivo pre-dismissione.

- [ ] **Step 4: Verifica che i test passino**

Run: `docker exec php81_orchestrator php artisan test --filter=AppShardSyncTest`
Expected: PASS (7 test)

- [ ] **Step 5: Commit (istruzione per l'utente)**

```bash
git add app/Services/Shards/AppSyncService.php tests/Feature/AppShardSyncTest.php
git commit -m "feat(oc:8242): AppSyncService with quiet upsert, CRM ownership and reconciliation guards"
```

---

## Task 7: orchestrator — Comando apps:sync + scheduling + rimozione vecchio import

**Files:**
- Create: `app/Console/Commands/SyncShardApps.php`
- Modify: `app/Console/Kernel.php`
- Delete: `app/Console/Commands/OrchestratorImport.php`
- Test: `tests/Feature/SyncShardAppsCommandTest.php`

**Interfaces:**
- Consumes: `ShardRegistry`, `AppSyncService`
- Produces: comando `apps:sync {--shard=}`; scheduling ogni 30 minuti. Nessun altro task dipende da questo.

- [ ] **Step 1: Scrivi il test**

Create `tests/Feature/SyncShardAppsCommandTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\App;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncShardAppsCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shards' => [
            'alpha' => ['url' => 'https://alpha.test', 'driver' => 'wmpackage', 'enabled' => true, 'token' => 'tok'],
            'beta' => ['url' => 'https://beta.test', 'driver' => 'wmpackage', 'enabled' => true, 'token' => 'tok'],
            'spento' => ['url' => 'https://spento.test', 'driver' => 'wmpackage', 'enabled' => false, 'token' => 'tok'],
        ]]);
    }

    public function test_command_syncs_all_enabled_shards_and_isolates_failures(): void
    {
        Http::fake([
            'https://alpha.test/api/v1/export/apps' => Http::response([
                'data' => [['id' => 1, 'name' => 'Alpha One']],
                'links' => ['next' => null],
            ]),
            'https://beta.test/api/v1/export/apps' => Http::response(null, 500), // beta giù
        ]);

        $this->artisan('apps:sync')->assertSuccessful();

        // alpha sincronizzata nonostante beta sia giù; spento mai chiamato
        $this->assertSame(1, App::where('shard', 'alpha')->count());
        $this->assertSame(0, App::where('shard', 'beta')->count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'spento.test'));
    }

    public function test_command_accepts_a_single_shard_option(): void
    {
        Http::fake([
            'https://alpha.test/api/v1/export/apps' => Http::response([
                'data' => [['id' => 1, 'name' => 'Alpha One']],
                'links' => ['next' => null],
            ]),
        ]);

        $this->artisan('apps:sync', ['--shard' => 'alpha'])->assertSuccessful();

        $this->assertSame(1, App::where('shard', 'alpha')->count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'beta.test'));
    }

    public function test_the_old_destructive_import_is_gone(): void
    {
        $this->assertFalse(class_exists(\App\Console\Commands\OrchestratorImport::class), 'OrchestratorImport (App::truncate) deve essere rimosso');
    }
}
```

- [ ] **Step 2: Verifica che fallisca**

Run: `docker exec php81_orchestrator php artisan test --filter=SyncShardAppsCommandTest`
Expected: FAIL (comando inesistente, OrchestratorImport ancora presente)

- [ ] **Step 3: Implementa il comando**

Create `app/Console/Commands/SyncShardApps.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\Shards\AppSyncService;
use App\Services\Shards\ShardRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncShardApps extends Command
{
    protected $signature = 'apps:sync {--shard= : Slug del singolo shard da sincronizzare}';

    protected $description = 'Sincronizza le app da tutti gli shard abilitati (config/shards.php)';

    public function handle(ShardRegistry $registry, AppSyncService $sync): int
    {
        $shards = $this->option('shard')
            ? collect([$registry->get($this->option('shard'))])->filter()
            : $registry->enabled();

        if ($shards->isEmpty()) {
            $this->error('Nessuno shard da sincronizzare (slug inesistente o tutti disabilitati).');

            return self::FAILURE;
        }

        $failures = 0;

        foreach ($shards as $shard) {
            // Lock per shard: evita sovrapposizioni tra giro schedulato e run manuali.
            $lock = Cache::lock("apps_sync_shard_{$shard->slug}", 600);

            if (! $lock->get()) {
                $this->warn("[{$shard->slug}] sync già in corso, salto.");

                continue;
            }

            try {
                $result = $sync->syncShard($shard);

                if ($result === null) {
                    $this->warn("[{$shard->slug}] payload invalido o vuoto — nessuna azione.");
                    $failures++;
                } else {
                    $this->info("[{$shard->slug}] {$result['synced']} sincronizzate, {$result['created']} nuove, {$result['removed']} dismesse.");
                }
            } catch (\Throwable $e) {
                // Errori isolati per shard: gli altri proseguono.
                Log::error("apps:sync [{$shard->slug}] fallita", ['exception' => $e]);
                $this->error("[{$shard->slug}] errore: {$e->getMessage()}");
                $failures++;
            } finally {
                $lock->release();
            }
        }

        return $failures === $shards->count() ? self::FAILURE : self::SUCCESS;
    }
}
```

- [ ] **Step 4: Rimuovi il vecchio import e registra lo scheduling**

1. Elimina il file `app/Console/Commands/OrchestratorImport.php`.
2. Verifica che non sia referenziato altrove: `grep -rn "orchestrator:import\|OrchestratorImport" app/ routes/ scripts/ config/ --include="*.php" --include="*.sh"` → deve restituire zero risultati (se compare in scripts/ o docs, rimuovi/aggiorna il riferimento).
3. In `app/Console/Kernel.php`, dentro `schedule()`, aggiungi accanto agli altri comandi:

```php
        // oc:8242 — sync app da tutti gli shard (full fetch + upsert, errori isolati)
        $schedule->command('apps:sync')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onOneServer();
```

- [ ] **Step 5: Verifica che i test passino**

Run: `docker exec php81_orchestrator php artisan test --filter=SyncShardAppsCommandTest`
Expected: PASS (3 test)

- [ ] **Step 6: Commit (istruzione per l'utente)**

```bash
git add app/Console/Commands/SyncShardApps.php app/Console/Kernel.php tests/Feature/SyncShardAppsCommandTest.php
git rm app/Console/Commands/OrchestratorImport.php
git commit -m "feat(oc:8242): apps:sync command with per-shard isolation, drop destructive OrchestratorImport"
```

---

## Task 8: orchestrator — Sync on-demand dal detail Nova (job + hook)

**Files:**
- Create: `app/Jobs/SyncShardAppJob.php`
- Modify: `app/Nova/App.php` (override `detailQuery`)
- Test: `tests/Feature/SyncShardAppJobTest.php`

**Interfaces:**
- Consumes: `ShardRegistry::get()`, `AppSyncService::syncOne()`
- Produces: `SyncShardAppJob` (costruttore `(int $appId)`, throttle Redis `shard_app_refresh_{id}` di 180s); eseguito inline (`dispatchSync`) dall'apertura del detail Nova.

- [ ] **Step 1: Scrivi il test**

Create `tests/Feature/SyncShardAppJobTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\SyncShardAppJob;
use App\Models\App;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncShardAppJobTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shards' => [
            'maphub' => ['url' => 'https://maphub.test', 'driver' => 'wmpackage', 'enabled' => true, 'token' => 'tok'],
        ]]);
        Cache::flush();
    }

    public function test_job_refreshes_the_app_from_its_shard(): void
    {
        $app = App::factory()->create(['shard' => 'maphub', 'app_id' => '5', 'name' => 'Stantia']);

        Http::fake([
            'https://maphub.test/api/v1/export/apps/5' => Http::response([
                'data' => ['id' => 5, 'name' => 'Fresca'],
            ]),
        ]);

        SyncShardAppJob::dispatchSync($app->id);

        $this->assertSame('Fresca', $app->refresh()->name);
    }

    public function test_job_is_throttled_per_app(): void
    {
        $app = App::factory()->create(['shard' => 'maphub', 'app_id' => '5', 'name' => 'Stantia']);

        Http::fake([
            'https://maphub.test/api/v1/export/apps/5' => Http::response(['data' => ['id' => 5, 'name' => 'Fresca']]),
        ]);

        SyncShardAppJob::dispatchSync($app->id);
        SyncShardAppJob::dispatchSync($app->id); // seconda apertura nel giro di 180s

        Http::assertSentCount(1);
    }

    public function test_job_falls_back_silently_when_the_shard_is_down(): void
    {
        $app = App::factory()->create(['shard' => 'maphub', 'app_id' => '5', 'name' => 'Locale']);

        Http::fake(['*' => Http::response(null, 500)]);

        SyncShardAppJob::dispatchSync($app->id); // nessuna eccezione

        $this->assertSame('Locale', $app->refresh()->name);
    }

    public function test_job_ignores_apps_of_unknown_or_disabled_shards(): void
    {
        $app = App::factory()->create(['shard' => 'sconosciuto', 'app_id' => '5']);

        Http::fake();
        SyncShardAppJob::dispatchSync($app->id);

        Http::assertNothingSent();
    }
}
```

- [ ] **Step 2: Verifica che fallisca**

Run: `docker exec php81_orchestrator php artisan test --filter=SyncShardAppJobTest`
Expected: FAIL (job inesistente)

- [ ] **Step 3: Implementa il job**

Create `app/Jobs/SyncShardAppJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\App;
use App\Services\Shards\AppSyncService;
use App\Services\Shards\ShardRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sync on-demand di una singola app dal suo shard (oc:8242).
 * Usato con dispatchSync() dall'apertura del detail Nova: il driver ha
 * timeout 3s e ogni errore è silenzioso (fallback alla copia locale).
 */
class SyncShardAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Finestra di throttle per app (secondi). */
    public const THROTTLE_SECONDS = 180;

    public function __construct(public readonly int $appId)
    {
    }

    public function handle(ShardRegistry $registry, AppSyncService $sync): void
    {
        $app = App::find($this->appId);

        if ($app === null || empty($app->shard) || empty($app->app_id)) {
            return;
        }

        $shard = $registry->get($app->shard);

        if ($shard === null || ! $shard->enabled) {
            return;
        }

        // Throttle per app: al massimo una fetch ogni THROTTLE_SECONDS.
        if (! Cache::add("shard_app_refresh_{$app->id}", 1, self::THROTTLE_SECONDS)) {
            return;
        }

        try {
            $sync->syncOne($shard, $app->app_id);
        } catch (\Throwable $e) {
            // Fallback silenzioso alla copia locale.
            Log::warning("Sync on-demand app {$app->id} [{$app->shard}] fallita: {$e->getMessage()}");
        }
    }
}
```

- [ ] **Step 4: Aggancia il detail Nova**

In `app/Nova/App.php` aggiungi il metodo statico (import in testa: `use App\Jobs\SyncShardAppJob;` e `use Illuminate\Support\Facades\Log;`):

```php
    /**
     * All'apertura del detail rinfresca l'app dal suo shard (oc:8242).
     * Inline con timeout 3s + throttle: mai bloccante oltre, mai errori in pagina.
     */
    public static function detailQuery(NovaRequest $request, $query)
    {
        if ($request->resourceId) {
            try {
                SyncShardAppJob::dispatchSync((int) $request->resourceId);
            } catch (\Throwable $e) {
                Log::warning('Sync on-demand dal detail Nova fallita: ' . $e->getMessage());
            }
        }

        return parent::detailQuery($request, $query);
    }
```

- [ ] **Step 5: Verifica che i test passino**

Run: `docker exec php81_orchestrator php artisan test --filter=SyncShardAppJobTest`
Expected: PASS (4 test)

- [ ] **Step 6: Commit (istruzione per l'utente)**

```bash
git add app/Jobs/SyncShardAppJob.php app/Nova/App.php tests/Feature/SyncShardAppJobTest.php
git commit -m "feat(oc:8242): on-demand single-app sync on Nova detail with per-app throttle"
```

---

## Task 9: orchestrator — Nova: colonna/filtro shard + filtro attive di default

**Files:**
- Create: `app/Nova/Filters/ShardFilter.php`
- Create: `app/Nova/Filters/ShardStatusFilter.php`
- Modify: `app/Nova/App.php` (campi + registrazione filtri)
- Modify: `lang/it.json`, `lang/en.json`
- Test: `tests/Feature/NovaShardFiltersTest.php`

**Interfaces:**
- Consumes: `config('shards')`, scope `active()`, colonna `shard`
- Produces: UI Nova. Nessun task dipende da questo.

- [ ] **Step 1: Scrivi il test**

Create `tests/Feature/NovaShardFiltersTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\App;
use App\Nova\Filters\ShardFilter;
use App\Nova\Filters\ShardStatusFilter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\Http\Requests\NovaRequest;
use Tests\TestCase;

class NovaShardFiltersTest extends TestCase
{
    use DatabaseTransactions;

    public function test_shard_filter_filters_by_slug(): void
    {
        App::factory()->create(['shard' => 'geohub', 'app_id' => '1']);
        App::factory()->create(['shard' => 'maphub', 'app_id' => '1']);

        $query = (new ShardFilter())->apply(app(NovaRequest::class), App::query(), 'maphub');

        $this->assertSame(1, $query->count());
        $this->assertSame('maphub', $query->first()->shard);
    }

    public function test_shard_filter_options_come_from_config(): void
    {
        config(['shards' => ['alpha' => ['url' => 'x', 'driver' => 'geohub'], 'beta' => ['url' => 'y', 'driver' => 'wmpackage']]]);

        $this->assertSame(['alpha' => 'alpha', 'beta' => 'beta'], (new ShardFilter())->options(app(NovaRequest::class)));
    }

    public function test_status_filter_defaults_to_active_and_filters(): void
    {
        App::factory()->create(['shard' => 'geohub', 'app_id' => '1']);
        App::factory()->create(['shard' => 'geohub', 'app_id' => '2', 'removed_from_shard_at' => now()]);

        $filter = new ShardStatusFilter();

        $this->assertSame('active', $filter->default());
        $this->assertSame(1, $filter->apply(app(NovaRequest::class), App::query(), 'active')->count());
        $this->assertSame(1, $filter->apply(app(NovaRequest::class), App::query(), 'removed')->count());
        $this->assertSame(2, $filter->apply(app(NovaRequest::class), App::query(), 'all')->count());
    }
}
```

- [ ] **Step 2: Verifica che fallisca**

Run: `docker exec php81_orchestrator php artisan test --filter=NovaShardFiltersTest`
Expected: FAIL (filtri inesistenti)

- [ ] **Step 3: Implementa i due filtri**

Create `app/Nova/Filters/ShardFilter.php`:

```php
<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class ShardFilter extends Filter
{
    public $component = 'select-filter';

    public function apply(NovaRequest $request, $query, $value)
    {
        return $query->where('shard', $value);
    }

    public function options(NovaRequest $request)
    {
        return collect(array_keys(config('shards', [])))
            ->mapWithKeys(fn (string $slug) => [$slug => $slug])
            ->toArray();
    }

    public function name()
    {
        return __('Shard');
    }
}
```

Create `app/Nova/Filters/ShardStatusFilter.php`:

```php
<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class ShardStatusFilter extends Filter
{
    public $component = 'select-filter';

    public function apply(NovaRequest $request, $query, $value)
    {
        return match ($value) {
            'active' => $query->active(),
            'removed' => $query->whereNotNull('removed_from_shard_at'),
            default => $query,
        };
    }

    public function options(NovaRequest $request)
    {
        return [
            __('Active') => 'active',
            __('Removed from shard') => 'removed',
            __('All') => 'all',
        ];
    }

    public function default()
    {
        return 'active';
    }

    public function name()
    {
        return __('Shard status');
    }
}
```

- [ ] **Step 4: Campi e registrazione in Nova/App.php**

1. In `fieldsForIndex()` aggiungi dopo il campo name (adatta la posizione all'array esistente):

```php
            Text::make(__('Shard'), 'shard')->sortable(),
```

2. In `fieldsForDetail()` aggiungi:

```php
            Text::make(__('Shard'), 'shard'),
            DateTime::make(__('Removed from shard at'), 'removed_from_shard_at')
                ->onlyOnDetail()
                ->help(__('Set when the app is no longer present on its shard')),
```

(import: `use Laravel\Nova\Fields\DateTime;` se non già presente)

3. In `filters()` aggiungi ai filtri esistenti:

```php
            new Filters\ShardFilter(),
            new Filters\ShardStatusFilter(),
```

(verifica lo stile di import/namespace dei filtri già registrati nel metodo e adeguati)

- [ ] **Step 5: Traduzioni**

In `lang/it.json` aggiungi le chiavi (in ordine alfabetico rispetto alle esistenti):

```json
    "Active": "Attive",
    "All": "Tutte",
    "Removed from shard": "Dismesse dallo shard",
    "Removed from shard at": "Dismessa dallo shard il",
    "Set when the app is no longer present on its shard": "Valorizzato quando l'app non è più presente sul suo shard",
    "Shard": "Shard",
    "Shard status": "Stato shard"
```

In `lang/en.json` aggiungi le stesse chiavi con valore = chiave (convenzione file en).

- [ ] **Step 6: Verifica che i test passino**

Run: `docker exec php81_orchestrator php artisan test --filter=NovaShardFiltersTest`
Expected: PASS (3 test)

- [ ] **Step 7: Commit (istruzione per l'utente)**

```bash
git add app/Nova/Filters/ShardFilter.php app/Nova/Filters/ShardStatusFilter.php app/Nova/App.php lang/it.json lang/en.json tests/Feature/NovaShardFiltersTest.php
git commit -m "feat(oc:8242): shard column and filters on Nova App resource"
```

---

## Task 10: orchestrator — Nome file PDF shard-qualificato + verifica finale

**Files:**
- Modify: `app/Http/Controllers/AppReportController.php:34-45`
- Test: `tests/Feature/AppReportPathTest.php`

**Interfaces:**
- Consumes: colonna `shard`
- Produces: path PDF `webmapp_report_app_{shard}_{safeName}_{month}.pdf`

- [ ] **Step 1: Scrivi il test**

Create `tests/Feature/AppReportPathTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Http\Controllers\AppReportController;
use App\Models\App;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

class AppReportPathTest extends TestCase
{
    use DatabaseTransactions;

    private function pdfPath(App $app): string
    {
        $method = new ReflectionMethod(AppReportController::class, 'pdfPath');
        $method->setAccessible(true);

        return $method->invoke(new AppReportController(), $app);
    }

    public function test_pdf_path_is_shard_qualified(): void
    {
        $geohub = App::factory()->create(['shard' => 'geohub', 'app_id' => '1', 'name' => 'Cammini']);
        $maphub = App::factory()->create(['shard' => 'maphub', 'app_id' => '1', 'name' => 'Cammini']);

        $month = now()->format('Y-m');

        $this->assertStringEndsWith("webmapp_report_app_geohub_Cammini_{$month}.pdf", $this->pdfPath($geohub));
        $this->assertStringEndsWith("webmapp_report_app_maphub_Cammini_{$month}.pdf", $this->pdfPath($maphub));
        $this->assertNotSame($this->pdfPath($geohub), $this->pdfPath($maphub));
    }
}
```

- [ ] **Step 2: Verifica che fallisca**

Run: `docker exec php81_orchestrator php artisan test --filter=AppReportPathTest`
Expected: FAIL (path senza shard)

- [ ] **Step 3: Modifica pdfPath**

In `app/Http/Controllers/AppReportController.php`, metodo `pdfPath()`, sostituisci la riga del return:

```php
    private function pdfPath(App $app): string
    {
        $safeName = preg_replace('/[^\w\-]/u', '_', $app->name);
        $month    = now()->format('Y-m');
        $dir      = storage_path('app/reports');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Shard nel nome file: due app omonime su shard diversi non devono
        // condividere lo stesso PDF (oc:8242).
        return "{$dir}/webmapp_report_app_{$app->shard}_{$safeName}_{$month}.pdf";
    }
```

- [ ] **Step 4: Verifica che i test passino**

Run: `docker exec php81_orchestrator php artisan test --filter=AppReportPathTest`
Expected: PASS (1 test)

- [ ] **Step 5: Suite completa finale (entrambi i repo)**

Run: `docker exec php81_orchestrator php artisan test`
Expected: PASS — nessun failure nuovo rispetto alla baseline pre-feature

Run: `docker exec -w /var/www/html/orchestrator/wm-package php81_orchestrator vendor/bin/pest --filter=AppExportApiTest`
Expected: PASS

- [ ] **Step 6: Commit (istruzione per l'utente)**

```bash
git add app/Http/Controllers/AppReportController.php tests/Feature/AppReportPathTest.php
git commit -m "feat(oc:8242): shard-qualified PDF report filename"
```

---

## Verifica manuale post-implementazione (fuori suite)

1. `docker exec php81_orchestrator php artisan apps:sync --shard=geohub` → le 68 app esistenti restano, nessuna dismissione, log puliti.
2. Aprire in Nova la lista App: colonna Shard popolata (`geohub`), filtro "Stato shard" default Attive.
3. Aprire un detail: tempo di apertura accettabile; secondo giro entro 3 minuti → nessuna chiamata (throttle).
4. Bottone REPORT su un'app geohub → PDF generato con nuovo nome file.
5. Quando l'endpoint wm-package sarà deployato su maphub: impostare `SHARD_TOKEN_MAPHUB` in ENV e lanciare `apps:sync --shard=maphub`.
