# Laravel 10 → 13 + Composer Full Upgrade Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiornare l'intera stack PHP da Laravel 10 / PHP 8.2 a Laravel 13 / PHP 8.3, includendo tutti i package Composer, i package locali wm-package e wm-internal, Nova 4 → 5, e la sostituzione/rimozione dei package abbandonati.

**Architecture:** Upgrade incrementale L10→11→12→13. Ogni step viene consolidato con i test prima di procedere. I package abbandonati vengono sostituiti prima dell'upgrade Nova per ridurre le variabili. I package locali vengono aggiornati come primo passo.

**Tech Stack:** PHP 8.3, Laravel 13, Laravel Nova 5, Composer, Docker (php:8.3-fpm)

---

## Riferimenti essenziali

- Upgrade L10→11: https://laravel.com/docs/11.x/upgrade
- Upgrade L11→12: https://laravel.com/docs/12.x/upgrade
- Upgrade L12→13: https://laravel.com/docs/13.x/upgrade
- Nova 5 release notes: https://nova.laravel.com/releases

---

## Mappa compatibilità package

### Pronti per L13 / Nova 5
| Package | Da | A |
|---|---|---|
| `badinansoft/nova-language-switch` | 2.0 | 3.0 |
| `barryvdh/laravel-dompdf` | 3.0 | 3.1 |
| `datomatic/nova-markdown-tui` | 1.2 | 1.3 |
| `ebess/advanced-nova-media-library` | 4.x | 5.x |
| `interaction-design-foundation/nova-html-card` | 3.1 | 3.4 |
| `kongulov/nova-tab-translatable` | 2.1 | latest |
| `laravel/horizon` | 5.25 | 5.46 |
| `laravel/sanctum` | 3.x | 4.x |
| `laravel/tinker` | 2.x | 3.x |
| `lorisleiva/laravel-actions` | 2.8 | 2.10 |
| `nunomaduro/collision` | 7.x | 8.x |
| `outl1ne/nova-multiselect-field` | 4.x | 5.x |
| `overtrue/laravel-favorite` | 5.x | 6.x |
| `phpunit/phpunit` | 10.x | 11.x |
| `predis/predis` | 2.x | 3.x |
| `spatie/laravel-medialibrary` | 10.x | 11.x |
| `webklex/laravel-imap` | 5.x | 6.x |

### Da sostituire (abbandonati o fermi a Nova 4)
| Package attuale | Sostituto | Note |
|---|---|---|
| `manogi/nova-tiptap` | `marshmallow/nova-tiptap` | Sostituto ufficiale indicato dal package stesso |
| `khalin/nova4-searchable-belongs-to-filter` | `suenerds/nova-searchable-belongs-to-filter` | Già presente nel progetto, rimuovere il duplicato |
| `davidpiesse/nova-toggle` | Campo `Boolean` nativo Nova 5 | Fermo al 2022, nessun fork attivo |
| `emilianotisato/nova-tinymce` | `marshmallow/nova-tiptap` | Già installato come sostituto di manogi |
| `eminiarts/nova-tabs` | Verificare fork Nova 5 | Valutare `outl1ne/nova-panel` se non esiste fork |
| `formfeed-uk/nova-breadcrumbs` | Rimozione | Nova 5 ha breadcrumb nativi migliorati |

### Package locali da aggiornare
| Package | Cambiamenti |
|---|---|
| `wm/wm-package` | PHP `^8.3`, illuminate `^10\|^11\|^12\|^13`, sanctum `^3\|^4`, testbench `^7\|^8\|^9`, pest `^1\|^2\|^3` |
| `wm/wm-internal` | PHP `^8.3`, illuminate `^10\|^11\|^12\|^13`, testbench `^7\|^8\|^9`, pest `^1\|^2\|^3` |

---

## Task 1: Aggiornare i package locali (wm-package, wm-internal)

**Files:**
- Modify: `wm-package/composer.json`
- Modify: `wm-internal/composer.json`

- [ ] **Step 1: Aggiornare wm-package/composer.json**

```json
"require": {
    "php": "^8.3",
    "illuminate/contracts": "^10.0|^11.0|^12.0|^13.0",
    "laravel/sanctum": "^3.0|^4.0",
    "spatie/laravel-package-tools": "^1.13.0"
},
"require-dev": {
    "guzzlehttp/guzzle": "^7.5",
    "laravel/pint": "^1.0",
    "nunomaduro/collision": "^7.0|^8.0",
    "nunomaduro/larastan": "^2.0.1",
    "orchestra/testbench": "^7.0|^8.0|^9.0",
    "pestphp/pest": "^1.21|^2.0|^3.0",
    "pestphp/pest-plugin-laravel": "^1.1|^2.0|^3.0",
    "phpstan/extension-installer": "^1.1",
    "phpstan/phpstan-deprecation-rules": "^1.0",
    "phpstan/phpstan-phpunit": "^1.0",
    "phpunit/phpunit": "^10|^11"
}
```

- [ ] **Step 2: Aggiornare wm-internal/composer.json**

```json
"require": {
    "php": "^8.3",
    "spatie/laravel-package-tools": "^1.14.0",
    "illuminate/contracts": "^10.0|^11.0|^12.0|^13.0"
},
"require-dev": {
    "laravel/pint": "^1.0",
    "nunomaduro/collision": "^7.0|^8.0",
    "orchestra/testbench": "^7.0|^8.0|^9.0",
    "pestphp/pest": "^1.21|^2.0|^3.0",
    "pestphp/pest-plugin-laravel": "^1.1|^2.0|^3.0",
    "phpunit/phpunit": "^10.0|^11.0"
}
```

- [ ] **Step 3: Commit**

```bash
git add wm-package/composer.json wm-internal/composer.json
git commit -m "chore(packages): widen illuminate/PHP constraints for L11-L13 compatibility"
```

---

## Task 2: Aggiornare il Dockerfile a PHP 8.3

**Files:**
- Modify: `docker/configs/phpfpm/Dockerfile`

- [ ] **Step 1: Aggiornare l'immagine base**

```dockerfile
# Prima
FROM php:8.2.15-fpm
# Dopo
FROM php:8.3-fpm
```

- [ ] **Step 2: Rebuild e verifica**

```bash
docker compose build phpfpm
docker compose run --rm phpfpm php -v
```
Atteso: `PHP 8.3.x`

- [ ] **Step 3: Commit**

```bash
git add docker/configs/phpfpm/Dockerfile
git commit -m "chore(docker): upgrade PHP 8.2 → 8.3"
```

---

## Task 3: Sostituire i package abbandonati

Fare le sostituzioni PRIMA dell'upgrade Laravel/Nova per isolare le variabili.

**Files:**
- Modify: `composer.json`
- Modify: file Nova che usano questi package

- [ ] **Step 1: Rimuovere khalin (duplicato di suenerds)**

```bash
docker exec -it php81_orchestrator composer remove khalin/nova4-searchable-belongs-to-filter
```

Verificare che nessuna Resource usi il namespace `Khalin\`:
```bash
grep -r "Khalin\\\\" app/Nova/
```
Se trovato, sostituire con l'equivalente `Suenerds\NovaSearchableBelongsToFilter\SearchableBelongsTo`.

- [ ] **Step 2: Sostituire manogi/nova-tiptap con marshmallow/nova-tiptap**

```bash
docker exec -it php81_orchestrator bash -c "
  composer remove manogi/nova-tiptap --no-update
  composer require marshmallow/nova-tiptap --no-update
  composer update marshmallow/nova-tiptap manogi/nova-tiptap
"
```

Aggiornare tutti i file che importano `Manogi\Tiptap`:
```bash
grep -rn "Manogi\\\\Tiptap" app/Nova/
```
Sostituire con `Marshmallow\NovaTiptap\Tiptap`.

- [ ] **Step 3: Rimuovere emilianotisato/nova-tinymce (sostituito da TipTap)**

```bash
docker exec -it php81_orchestrator composer remove emilianotisato/nova-tinymce
grep -rn "EmilianoTisato\\\\\|NovaExt\\\\" app/Nova/
```
Sostituire ogni `Tinymce::make(...)` con `\Marshmallow\NovaTiptap\Tiptap::make(...)`.

- [ ] **Step 4: Rimuovere davidpiesse/nova-toggle**

```bash
docker exec -it php81_orchestrator composer remove davidpiesse/nova-toggle
grep -rn "Davidpiesse\\\\" app/Nova/
```
Sostituire ogni `Toggle::make(...)` con il campo `Boolean` nativo di Nova:
```php
// Prima
\Davidpiesse\NovaToggle\Toggle::make('Active')
// Dopo
\Laravel\Nova\Fields\Boolean::make('Active')
```

- [ ] **Step 5: Gestire eminiarts/nova-tabs**

```bash
# Verificare se esiste un fork Nova 5
composer show eminiarts/nova-tabs
grep -rn "Eminiarts\\\\NovaTabs\\\\" app/Nova/
```
Se il package non supporta Nova 5, sostituire con i Panel nativi di Nova:
```php
// Prima
use Eminiarts\Tabs\Tabs;
Tabs::make('Details', [...fields...])
// Dopo
\Laravel\Nova\Panel::make('Details', [...fields...])
```

- [ ] **Step 6: Rimuovere formfeed-uk/nova-breadcrumbs**

```bash
docker exec -it php81_orchestrator composer remove formfeed-uk/nova-breadcrumbs
grep -rn "Formfeed\\\\" app/Nova/
```
Nova 5 ha breadcrumb nativi — rimuovere qualsiasi registrazione del package in `NovaServiceProvider`.

- [ ] **Step 7: Test dopo le sostituzioni**

```bash
docker exec -it php81_orchestrator php artisan test
```
Aprire http://localhost:8000/nova e verificare visivamente le Resource modificate.

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock app/Nova/ app/Providers/
git commit -m "refactor(nova): replace/remove abandoned packages before Laravel upgrade"
```

---

## Task 4: Upgrade a Laravel 11

**Files:**
- Modify: `composer.json`
- Create: `bootstrap/app.php`
- Create: `routes/console.php`
- Delete: `app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php`

- [ ] **Step 1: Aggiornare composer.json per L11**

Modificare i vincoli principali:
```json
"php": "^8.3",
"laravel/framework": "^11.0",
"laravel/sanctum": "^4.0",
"laravel/tinker": "^3.0",
"nunomaduro/collision": "^8.0",
"phpunit/phpunit": "^11.0",
"spatie/laravel-medialibrary": "^11.0",
"webklex/laravel-imap": "^6.0",
"overtrue/laravel-favorite": "^6.0",
"predis/predis": "^3.0"
```

- [ ] **Step 2: composer update**

```bash
docker exec -it php81_orchestrator bash -c "composer update --with-all-dependencies 2>&1"
```

- [ ] **Step 3: Creare bootstrap/app.php (struttura slim L11)**

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Copiare i middleware custom dall'ex app/Http/Kernel.php
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Copiare la logica dall'ex app/Exceptions/Handler.php
    })->create();
```

- [ ] **Step 4: Creare routes/console.php**

Prima controllare i task attuali:
```bash
docker exec -it php81_orchestrator cat app/Console/Kernel.php
```

Creare `routes/console.php` con i task schedulati trovati:
```php
<?php

use Illuminate\Support\Facades\Schedule;

// Copiare tutti i task da app/Console/Kernel.php -> protected function schedule()
Schedule::command('db:backup')->dailyAt('02:00');
// ... tutti gli altri task schedulati
```

- [ ] **Step 5: Rimuovere file deprecati**

```bash
rm app/Http/Kernel.php app/Console/Kernel.php app/Exceptions/Handler.php
```

- [ ] **Step 6: Verifica**

```bash
docker exec -it php81_orchestrator php artisan migrate --force
docker exec -it php81_orchestrator php artisan schedule:list
docker exec -it php81_orchestrator php artisan test
```

- [ ] **Step 7: Commit**

```bash
git add -p
git commit -m "feat: upgrade Laravel 10 → 11, migrate to slim skeleton"
```

---

## Task 5: Upgrade a Laravel 12

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Aggiornare composer.json**

```json
"laravel/framework": "^12.0"
```

- [ ] **Step 2: composer update e verifica breaking changes**

```bash
docker exec -it php81_orchestrator bash -c "composer update laravel/framework --with-all-dependencies 2>&1"
```

Leggere https://laravel.com/docs/12.x/upgrade — verificare in particolare Model casting e Queue changes.

- [ ] **Step 3: Test**

```bash
docker exec -it php81_orchestrator php artisan test
```

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "feat: upgrade Laravel 11 → 12"
```

---

## Task 6: Upgrade a Laravel 13

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Aggiornare composer.json**

```json
"laravel/framework": "^13.0"
```

- [ ] **Step 2: composer update**

```bash
docker exec -it php81_orchestrator bash -c "composer update laravel/framework --with-all-dependencies 2>&1"
```

- [ ] **Step 3: Aggiornare i package rimanenti all'ultima versione**

```bash
docker exec -it php81_orchestrator bash -c "composer update \
  barryvdh/laravel-dompdf \
  lorisleiva/laravel-actions \
  maatwebsite/excel \
  spatie/laravel-translatable \
  spatie/db-dumper \
  spatie/laravel-google-calendar \
  laravel/horizon \
  --with-all-dependencies 2>&1"
```

- [ ] **Step 4: Test**

```bash
docker exec -it php81_orchestrator php artisan test
```

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock
git commit -m "feat: upgrade Laravel 12 → 13, update remaining packages"
```

---

## Task 7: Upgrade Nova 4 → 5

**Files:**
- Modify: `composer.json`
- Modify: eventuali Resource/Action/Lens con API cambiate in Nova 5
- Modify: `nova-components/kanban-card/composer.json`

- [ ] **Step 1: Aggiornare i package Nova in composer.json**

```json
"laravel/nova": "^5.0",
"badinansoft/nova-language-switch": "^3.0",
"outl1ne/nova-multiselect-field": "^5.0",
"interaction-design-foundation/nova-html-card": "^3.4",
"datomatic/nova-markdown-tui": "^1.3",
"ebess/advanced-nova-media-library": "^5.0"
```

- [ ] **Step 2: composer update**

```bash
docker exec -it php81_orchestrator bash -c "composer update laravel/nova --with-all-dependencies 2>&1"
```

- [ ] **Step 3: Pubblicare asset Nova 5 e rebuild frontend**

```bash
docker exec -it php81_orchestrator php artisan nova:publish
npm run build
```

- [ ] **Step 4: Aggiornare nova-components/kanban-card**

```bash
# Aggiornare il vincolo Nova nel package locale
# In nova-components/kanban-card/composer.json:
# "laravel/nova": "^4.0|^5.0"

cd nova-components/kanban-card
npm install && npm run build
cd ../..
```

- [ ] **Step 5: Verifica visiva completa nel browser**

Aprire http://localhost:8000/nova e verificare:
- Tutte le Resource si caricano senza errori
- Actions funzionano
- Kanban board funziona (drag & drop)
- Lenses funzionano
- Nessun errore JS in console (F12)

- [ ] **Step 6: Test + Commit**

```bash
docker exec -it php81_orchestrator php artisan test
git add composer.json composer.lock nova-components/ public/vendor/nova
git commit -m "feat: upgrade Nova 4 → 5 with full ecosystem packages"
```

---

## Task 8: Verifica finale

- [ ] **Step 1: Suite completa dei test**

```bash
docker exec -it php81_orchestrator php artisan test --stop-on-failure
```

- [ ] **Step 2: Verifica Horizon**

```bash
docker exec -it php81_orchestrator bash scripts/launch_horizon.sh
```
Aprire http://localhost:8000/horizon — code operative.

- [ ] **Step 3: Verifica scheduler**

```bash
docker exec -it php81_orchestrator php artisan schedule:list
```
Tutti i task schedulati devono essere presenti.

- [ ] **Step 4: Verifica versioni**

```bash
docker exec -it php81_orchestrator php artisan --version
docker exec -it php81_orchestrator php -v
docker exec -it php81_orchestrator composer show laravel/nova | grep versions
```
Atteso: Laravel 13.x, PHP 8.3.x, Nova 5.x

- [ ] **Step 5: Commit finale**

```bash
git add -p
git commit -m "chore: final cleanup post Laravel 13 + Nova 5 + full composer upgrade"
```
