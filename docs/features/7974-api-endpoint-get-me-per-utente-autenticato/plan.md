> Ticket: oc:7974

# API endpoint GET /me — Piano di implementazione

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere `GET /api/me` che restituisce `id`, `name`, `email` dell'utente autenticato via Sanctum.

**Architecture:** Closure inline nel gruppo `auth:sanctum` di `routes/api.php`. Nessun controller aggiuntivo. Test minimo in `tests/Feature/Api/MeEndpointTest.php`.

**Tech Stack:** Laravel 10, Laravel Sanctum, PHPUnit

---

### Task 1: Aggiungere la route GET /me

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 1: Aggiungere la closure nel gruppo `auth:sanctum`**

Apri `routes/api.php` e aggiungi la route all'interno del gruppo esistente:

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', function (Request $request) {
        return response()->json([
            'id'    => $request->user()->id,
            'name'  => $request->user()->name,
            'email' => $request->user()->email,
        ]);
    });
    Route::get('/stories/{story}', [StoryController::class, 'show']);
    Route::post('/stories', [StoryController::class, 'store']);
    Route::patch('/stories/{story}', [StoryController::class, 'update']);
});
```

- [ ] **Step 2: Verificare che le route siano registrate**

Eseguire dentro il container `php81_orchestrator`:

```bash
php artisan route:list --path=api/me
```

Output atteso: una riga con `GET | HEAD` → `api/me`.

---

### Task 2: Test minimo

**Files:**
- Create: `tests/Feature/Api/MeEndpointTest.php`

- [ ] **Step 1: Creare il file di test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function utente_autenticato_ottiene_id_name_email(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200)
            ->assertExactJson([
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ]);
    }

    /** @test */
    public function utente_non_autenticato_ottiene_401(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }
}
```

- [ ] **Step 2: Committare**

```bash
git add routes/api.php tests/Feature/Api/MeEndpointTest.php
git commit -m "feat(oc:7974): add GET /api/me endpoint for authenticated user"
```
