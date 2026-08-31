> Ticket: oc:8426

# Nuovo stato "Pending Release" — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introdurre lo stato `pending_release` fra `tested` e `released`, visibile solo nella colonna Kanban dedicata e al cliente in "I miei Ticket", escluso da tutte le liste interne del team e dal calendario dei developer.

**Architecture:** Un nuovo `case` in `App\Enums\StoryStatus` propagato a mano a tutti i punti che elencano stati in modo esplicito. Nessuna migrazione (la colonna `status` è testuale), nessuna modifica al componente `nova-components/kanban-card/` (il drag&drop scrive il valore ricevuto senza whitelist), nessuna modifica al comando calendario (filtra su `status = 'tested'` esatto, quindi l'esclusione è automatica).

**Tech Stack:** Laravel 10, Laravel Nova, PostgreSQL, PHPUnit con `DatabaseTransactions` su DB `orchestrator_test`.

**Spec:** `docs/features/8426-nuovo-stato-pending-release/overview.md`

## Global Constraints

- **Tutti i comandi girano nel container Docker:** prefissare sempre con `docker exec php81_orchestrator`.
- **Test sul DB di supporto `orchestrator_test`** (già configurato in `phpunit.xml`). **Mai** `DB_DATABASE=orchestrator`: punterebbe al DB reale.
- **Mai** `php artisan migrate:fresh` o `db:wipe`. Questo ticket non richiede alcuna migrazione.
- **Nessun commit va eseguito.** Gli step "Commit" di questo piano sono istruzioni testuali per il developer: mostrare il comando, non lanciarlo.
- **Commit convention:** `feat(oc:8426): ...`
- **Valore dello stato:** `pending_release` (esatto, snake_case). **Nome del case:** `PendingRelease`.
- **Colore:** `#14B8A6` (teal). Non usare un altro verde: `tested` è `#34D399`, `released` `#10B981`, `done` `#16A34A`, la colonna virtuale `tested_by_others` `#86EFAC`.
- **Doppia chiave di traduzione obbligatoria** in `lang/it.json` **e** `lang/en.json` — vedi `CLAUDE.md` → `## Convenzioni del codebase`. Servono sia `"Pending Release"` (letta da `label()`) sia `"Pending_release"` (letta da `__(ucfirst($value))`).
- **Locale di default del repo:** `it`, fallback `en`.
- **Nessuna migrazione dati:** i 16 ticket attualmente in `tested` non vanno toccati.

---

## File Structure

| File | Responsabilità | Azione |
|---|---|---|
| `app/Enums/StoryStatus.php` | fonte di verità dello stato: valore, label, colore | Modify |
| `lang/it.json`, `lang/en.json` | traduzioni (doppia chiave) | Modify |
| `app/Traits/fieldTrait.php` | classificazione del badge Nova (`loadingWhen`) | Modify |
| `app/Nova/Dashboards/Kanban.php` | colonna Kanban + ownership della card | Modify |
| `app/Nova/CustomerStory.php` | esclusione dalla vista team "Ticket" | Modify |
| `app/Nova/DeveloperStory.php` | esclusione dalla vista team dev | Modify |
| `app/Nova/AssignedToMeStory.php` | esclusione da "my work" | Modify |
| `app/Models/Tag.php` | `pending_release` conta come chiuso nel SAL | Modify |
| `app/Services/Metrics/StoryMetricsCalculator.php` | `pending_release` è stato avanzato per i reopen | Modify |
| `tests/Feature/PendingReleaseStatusTest.php` | tutti i test della feature in un solo file | **Create** |

**Perché un solo file di test:** le asserzioni sono tutte sullo stesso fatto (un nuovo stato correttamente propagato) e condividono gli stessi helper di setup. Il repo ha già file di test per-tema (`TagSalTest`, `TaskNovaResourceTest`), non per-classe.

---

## Task 1: Enum, traduzioni e badge Nova

**Files:**
- Modify: `app/Enums/StoryStatus.php` (case list riga ~14, `color()` riga ~30, `label()` riga ~57)
- Modify: `lang/it.json`, `lang/en.json`
- Modify: `app/Traits/fieldTrait.php:322-328` (`loadingWhen`)
- Test: `tests/Feature/PendingReleaseStatusTest.php` (create)

**Interfaces:**
- Consumes: niente (primo task)
- Produces: `StoryStatus::PendingRelease` con valore `'pending_release'`, `label()` → `__('Pending Release')`, `color()` → `'#14B8A6'`. Tutti i task successivi usano `StoryStatus::PendingRelease->value`.

- [ ] **Step 1: Scrivi il test che falla**

Crea `tests/Feature/PendingReleaseStatusTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PendingReleaseStatusTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pending_release_case_esiste_con_valore_corretto()
    {
        $this->assertSame('pending_release', StoryStatus::PendingRelease->value);
        $this->assertContains('pending_release', StoryStatus::values());
    }

    public function test_pending_release_si_trova_subito_dopo_tested_nellenum()
    {
        $values = StoryStatus::values();

        $this->assertSame(
            array_search('tested', $values, true) + 1,
            array_search('pending_release', $values, true),
            'pending_release deve seguire immediatamente tested: l\'ordine dei cases determina '
            .'l\'ordine delle opzioni in StoryStatusFilter e fieldTrait::getOptions()'
        );
    }

    /**
     * StoryStatus::label() e' un match ESAUSTIVO SENZA ramo default (a differenza di
     * color() e collapse()): un case aggiunto senza la riga corrispondente in label()
     * solleva \UnhandledMatchError, cioe' un 500 sul dashboard Kanban e su ogni index.
     * Questo test protegge anche gli stati futuri, non solo pending_release.
     */
    public function test_ogni_case_dellenum_ha_label_e_colore()
    {
        foreach (StoryStatus::cases() as $case) {
            $label = $case->label();
            $this->assertIsString($label);
            $this->assertNotSame('', $label, "label() vuota per il case {$case->name}");

            $this->assertMatchesRegularExpression(
                '/^#[0-9A-Fa-f]{6}$/',
                $case->color(),
                "color() non e' un hex valido per il case {$case->name}"
            );
        }
    }

    public function test_pending_release_ha_un_colore_distinto_da_tested_e_released()
    {
        $this->assertSame('#14B8A6', StoryStatus::PendingRelease->color());

        $this->assertNotSame(StoryStatus::Tested->color(), StoryStatus::PendingRelease->color());
        $this->assertNotSame(StoryStatus::Released->color(), StoryStatus::PendingRelease->color());
    }

    /**
     * Doppia chiave obbligatoria: label() cerca "Pending Release", mentre
     * fieldTrait::getOptions() e displayUsing() costruiscono la chiave con
     * __(ucfirst($value)) e cercano quindi "Pending_release".
     * Vedi CLAUDE.md -> ## Convenzioni del codebase.
     */
    public function test_entrambe_le_chiavi_di_traduzione_esistono_in_it_e_en()
    {
        foreach (['it', 'en'] as $locale) {
            $translations = json_decode(file_get_contents(base_path("lang/{$locale}.json")), true);

            $this->assertArrayHasKey('Pending Release', $translations, "manca 'Pending Release' in {$locale}.json");
            $this->assertArrayHasKey('Pending_release', $translations, "manca 'Pending_release' in {$locale}.json");

            $this->assertSame(
                $translations['Pending Release'],
                $translations['Pending_release'],
                "le due chiavi devono avere lo stesso valore tradotto in {$locale}.json"
            );
        }
    }

    public function test_ucfirst_del_valore_produce_la_chiave_con_underscore()
    {
        // Documenta il motivo della doppia chiave: ucfirst() non tocca gli underscore.
        $this->assertSame('Pending_release', ucfirst(StoryStatus::PendingRelease->value));
    }
}
```

- [ ] **Step 2: Lancia il test e verifica che fallisca**

Run:
```bash
docker exec php81_orchestrator php artisan test --filter=PendingReleaseStatusTest
```
Expected: FAIL con `Error: Undefined constant App\Enums\StoryStatus::PendingRelease`

- [ ] **Step 3: Aggiungi il case all'enum**

In `app/Enums/StoryStatus.php`, inserire il nuovo case **subito dopo `Tested`**:

```php
    case Test = 'testing';
    case Tested = 'tested';
    case PendingRelease = 'pending_release';
    case Waiting = 'waiting';
```

- [ ] **Step 4: Aggiungi il colore in `color()`**

Nel `match` di `color()`, subito dopo la riga di `Tested`:

```php
            self::Tested => '#34D399',
            self::PendingRelease => '#14B8A6',
            self::Released => '#10B981',
```

- [ ] **Step 5: Aggiungi la label in `label()`**

Nel `match` di `label()`, subito dopo la riga di `Tested`. **Obbligatorio**: `label()` non ha ramo `default`, ometterlo causa `UnhandledMatchError`.

```php
            self::Tested => __('Tested'),
            self::PendingRelease => __('Pending Release'),
```

- [ ] **Step 6: Aggiungi le due chiavi di traduzione**

In `lang/it.json`, accanto alle chiavi `"Tested"` / `"Released"` già presenti:

```json
    "Pending Release": "In attesa di rilascio",
    "Pending_release": "In attesa di rilascio",
```

In `lang/en.json`:

```json
    "Pending Release": "Pending Release",
    "Pending_release": "Pending Release",
```

La forma con underscore **non è un duplicato**: è la sola che `__(ucfirst($value))` sa risolvere (`fieldTrait.php:335,662`). Stesso pattern già usato da `Closed_Lost`, `Closed_Won`, `Partially_Paid`, `To_Present`, `Waiting_For_Order`.

- [ ] **Step 7: Aggiungi `pending_release` a `loadingWhen()`**

In `app/Traits/fieldTrait.php:322-328`, dentro `->loadingWhen([...])`:

```php
                ->loadingWhen([
                    StoryStatus::Assigned->value,
                    StoryStatus::Todo->value,
                    StoryStatus::Progress->value,
                    StoryStatus::Tested->value,
                    StoryStatus::PendingRelease->value,
                    StoryStatus::Backlog->value,
                    storyStatus::Test->value
                ])
```

Senza questa riga il badge Nova renderizza `pending_release` come *completato* (verde/success), perché non compare né in `loadingWhen` né in `failedWhen`. Lo stato è un'attesa, non una conclusione.

- [ ] **Step 8: Lancia il test e verifica che passi**

Run:
```bash
docker exec php81_orchestrator php artisan test --filter=PendingReleaseStatusTest
```
Expected: PASS (7 test)

- [ ] **Step 9: Verifica che nulla di esistente si sia rotto**

Run:
```bash
docker exec php81_orchestrator php artisan test --filter="StoryEmailTriggersTest|StoryAutoTaggingTest|TagSalTest|StoryLogCommandsTest"
```
Expected: PASS. Se falliscono, il nuovo case ha rotto un `match` o una lista di stati non prevista: fermarsi e indagare prima di proseguire.

- [ ] **Step 10: Commit** *(istruzione per il developer — non eseguire)*

```bash
git add app/Enums/StoryStatus.php lang/it.json lang/en.json app/Traits/fieldTrait.php tests/Feature/PendingReleaseStatusTest.php
git commit -m "feat(oc:8426): aggiungi stato pending_release a StoryStatus con traduzioni e badge"
```

---

## Task 2: Colonna Kanban

**Files:**
- Modify: `app/Nova/Dashboards/Kanban.php:62-108`
- Test: `tests/Feature/PendingReleaseStatusTest.php` (append)

**Interfaces:**
- Consumes: `StoryStatus::PendingRelease` da Task 1
- Produces: colonna Kanban con `value = 'pending_release'`, posizionata dopo `tested_by_others` e prima di `released`; `statusFilterOverrides['pending_release'] = ['user_id', 'creator_id']`

- [ ] **Step 1: Scrivi il test che falla**

Aggiungi in `tests/Feature/PendingReleaseStatusTest.php`. Servono questi `use` in testa al file:

```php
use App\Enums\UserRole;
use App\Models\User;
use App\Nova\Dashboards\Kanban;
```

```php
    private function kanbanCard(): \Webmapp\KanbanCard\KanbanCard
    {
        // Kanban::cards() legge auth()->user() per initialFilterValue.
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $this->actingAs($admin);

        $cards = (new Kanban())->cards();

        return $cards[0];
    }

    public function test_il_kanban_ha_una_colonna_pending_release()
    {
        $values = array_column($this->kanbanCard()->columnsConfig, 'value');

        $this->assertContains('pending_release', $values);
    }

    public function test_la_colonna_pending_release_sta_tra_tested_by_others_e_released()
    {
        $values = array_column($this->kanbanCard()->columnsConfig, 'value');

        $this->assertSame(
            array_search('tested_by_others', $values, true) + 1,
            array_search('pending_release', $values, true)
        );
        $this->assertSame(
            array_search('pending_release', $values, true) + 1,
            array_search('released', $values, true)
        );
    }

    /**
     * statusFilterOverrides definisce DI CHI e' la card in una colonna. Per
     * pending_release il test e' concluso, quindi la card interessa chi ha
     * sviluppato e chi ha aperto il ticket (come released), non chi ha testato
     * (come tested). Senza override espl icito filtrerebbe sul default user_id
     * e le card create da customer non comparirebbero a nessuno.
     */
    public function test_pending_release_filtra_su_user_id_e_creator_id()
    {
        $overrides = $this->kanbanCard()->statusFilterOverrides;

        $this->assertArrayHasKey('pending_release', $overrides);
        $this->assertEqualsCanonicalizing(
            ['user_id', 'creator_id'],
            (array) $overrides['pending_release']
        );
    }
```

- [ ] **Step 2: Lancia il test e verifica che fallisca**

Run:
```bash
docker exec php81_orchestrator php artisan test --filter=PendingReleaseStatusTest
```
Expected: FAIL — `Failed asserting that array does not contain 'pending_release'` sui 3 nuovi test

- [ ] **Step 3: Aggiungi lo `statusFilterOverrides`**

In `app/Nova/Dashboards/Kanban.php:62-66`:

```php
                ->statusFilterOverrides([
                    StoryStatus::Test->value => 'tester_id',
                    StoryStatus::Tested->value => 'tester_id',
                    StoryStatus::PendingRelease->value => ['user_id', 'creator_id'],
                    StoryStatus::Released->value => ['user_id', 'creator_id'],
                ])
```

- [ ] **Step 4: Aggiungi la colonna**

In `app/Nova/Dashboards/Kanban.php`, il terzo `array_map` (riga ~100-107) mappa oggi il solo `[StoryStatus::Released]`. Aggiungere `PendingRelease` **prima** di `Released` nello stesso array, così la colonna eredita label e colore dall'enum senza duplicare la config:

```php
                        array_map(
                            fn(StoryStatus $status) => [
                                'value' => $status->value,
                                'label' => $status->label(),
                                'color' => $status->color() ?: KanbanCard::DEFAULT_COLOR,
                                'collapse' => $status->collapse(),
                            ],
                            [StoryStatus::PendingRelease, StoryStatus::Released]
                        )
```

Questo colloca la colonna dopo `tested_by_others` (che è il blocco `array_merge` precedente) e prima di `released`, come richiesto.

- [ ] **Step 5: Lancia il test e verifica che passi**

Run:
```bash
docker exec php81_orchestrator php artisan test --filter=PendingReleaseStatusTest
```
Expected: PASS (10 test)

- [ ] **Step 6: Verifica che il componente Kanban non richieda modifiche**

Run:
```bash
grep -n "input('status')" nova-components/kanban-card/src/Http/Controllers/KanbanController.php
```
Expected: la riga ~419 mostra che `updateStatus()` scrive il valore ricevuto senza whitelist contro `StoryStatus::values()`. **Nessuna modifica al componente**, quindi nessun bundle `dist/` da rigenerare e nessun `node --check`.

Il `'tested'` hardcodato in `KanbanController.php:108,145,207` riguarda **solo** la colonna virtuale `tested_by_others` e **non va toccato**: l'effetto è corretto — spostando un ticket in `pending_release` esce anche da "Has Been Tested".

- [ ] **Step 7: Commit** *(istruzione per il developer — non eseguire)*

```bash
git add app/Nova/Dashboards/Kanban.php tests/Feature/PendingReleaseStatusTest.php
git commit -m "feat(oc:8426): aggiungi colonna Kanban pending_release con ownership user_id/creator_id"
```

---

## Task 3: Esclusione dalle liste interne, presenza per il cliente, uscita dal calendario

**Files:**
- Modify: `app/Nova/CustomerStory.php:31-35`
- Modify: `app/Nova/DeveloperStory.php:30-37`
- Modify: `app/Nova/AssignedToMeStory.php:17-22`
- Test: `tests/Feature/PendingReleaseStatusTest.php` (append)

**Interfaces:**
- Consumes: `StoryStatus::PendingRelease` da Task 1
- Produces: nessuna interfaccia nuova — solo restrizioni di query

- [ ] **Step 1: Scrivi il test che falla**

`use` aggiuntivi in testa al file:

```php
use App\Models\Story;
use App\Nova\AssignedToMeStory;
use App\Nova\CustomerStory;
use App\Nova\DeveloperStory;
use App\Nova\StoryShowedByCustomer;
use App\Console\Commands\SyncStoriesWithGoogleCalendar;
use Laravel\Nova\Http\Requests\NovaRequest;
```

```php
    private function novaRequestFor(User $user): NovaRequest
    {
        return NovaRequest::create('/')->setUserResolver(fn () => $user);
    }

    private function makeStory(array $attributes): Story
    {
        // status va forzato sulla colonna: StoryFactory assegna uno stato casuale
        // fra tutti i cases (database/factories/StoryFactory.php).
        return Story::factory()->create($attributes);
    }

    public function test_pending_release_escluso_da_customer_stories()
    {
        $customer = User::factory()->create(['roles' => [UserRole::Customer]]);
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);

        $pending = $this->makeStory(['creator_id' => $customer->id, 'status' => StoryStatus::PendingRelease->value]);
        $tested = $this->makeStory(['creator_id' => $customer->id, 'status' => StoryStatus::Tested->value]);

        $ids = CustomerStory::indexQuery($this->novaRequestFor($admin), Story::query())->pluck('id');

        $this->assertNotContains($pending->id, $ids);
        $this->assertContains($tested->id, $ids, 'i ticket in tested devono restare visibili');
    }

    public function test_pending_release_escluso_da_developer_stories()
    {
        $dev = User::factory()->create(['roles' => [UserRole::Developer]]);

        $pending = $this->makeStory(['creator_id' => $dev->id, 'status' => StoryStatus::PendingRelease->value]);
        $tested = $this->makeStory(['creator_id' => $dev->id, 'status' => StoryStatus::Tested->value]);

        $ids = DeveloperStory::indexQuery($this->novaRequestFor($dev), Story::query())->pluck('id');

        $this->assertNotContains($pending->id, $ids);
        $this->assertContains($tested->id, $ids);
    }

    public function test_pending_release_escluso_da_assigned_to_me()
    {
        $dev = User::factory()->create(['roles' => [UserRole::Developer]]);
        $this->actingAs($dev); // AssignedToMeStory::indexQuery() usa auth()->user()->id

        $pending = $this->makeStory(['user_id' => $dev->id, 'status' => StoryStatus::PendingRelease->value]);
        $tested = $this->makeStory(['user_id' => $dev->id, 'status' => StoryStatus::Tested->value]);

        $ids = AssignedToMeStory::indexQuery($this->novaRequestFor($dev), Story::query())->pluck('id');

        $this->assertNotContains($pending->id, $ids);
        $this->assertContains($tested->id, $ids);
    }

    /**
     * StoryShowedByCustomer (/resources/story-showed-by-customers, "I miei Ticket")
     * e' la vista DEL CLIENTE, non del team: il cliente deve continuare a vedere i
     * ticket in attesa del suo ok. Test di NON regressione: questo file non va toccato.
     */
    public function test_il_cliente_continua_a_vedere_pending_release_nei_suoi_ticket()
    {
        $customer = User::factory()->create(['roles' => [UserRole::Customer]]);

        $pending = $this->makeStory(['creator_id' => $customer->id, 'status' => StoryStatus::PendingRelease->value]);

        $ids = StoryShowedByCustomer::indexQuery($this->novaRequestFor($customer), Story::query())->pluck('id');

        $this->assertContains($pending->id, $ids);
    }

    /**
     * getTestedTickets() filtra con where('status', 'tested') ESATTO: spostando il
     * ticket in pending_release esce automaticamente dal calendario, zero righe di
     * codice. Test di regressione a protezione di quell'assunzione.
     */
    public function test_pending_release_non_finisce_nel_calendario_del_developer()
    {
        $dev = User::factory()->create(['roles' => [UserRole::Developer]]);

        $pending = $this->makeStory(['creator_id' => $dev->id, 'status' => StoryStatus::PendingRelease->value]);
        $tested = $this->makeStory(['creator_id' => $dev->id, 'status' => StoryStatus::Tested->value]);

        $ids = (new SyncStoriesWithGoogleCalendar())->getTestedTickets($dev->id)->pluck('id');

        $this->assertNotContains($pending->id, $ids);
        $this->assertContains($tested->id, $ids);
    }
```

- [ ] **Step 2: Lancia il test e verifica che fallisca**

Run:
```bash
docker exec php81_orchestrator php artisan test --filter=PendingReleaseStatusTest
```
Expected: FAIL sui 3 test di esclusione (`assertNotContains` trova l'id). I due test di non-regressione (cliente e calendario) devono **già passare** ora: se fallissero, l'assunzione dell'overview è sbagliata — fermarsi e indagare.

- [ ] **Step 3: Escludi da `CustomerStory`**

`app/Nova/CustomerStory.php:32`:

```php
        $whereNotIn = [
            StoryStatus::Done->value,
            StoryStatus::Backlog->value,
            StoryStatus::Rejected->value,
            StoryStatus::PendingRelease->value,
        ];
```

- [ ] **Step 4: Escludi da `DeveloperStory`**

`app/Nova/DeveloperStory.php:30-37` — sostituire il `where('status', '!=', ...)` con un `whereNotIn`:

```php
    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->whereNotNull('creator_id')
            ->whereDoesntHave('creator', function ($query) {
                $query->whereJsonContains('roles', UserRole::Customer);
            })
            ->whereNotIn('status', [
                StoryStatus::Done->value,
                StoryStatus::PendingRelease->value,
            ]);
    }
```

- [ ] **Step 5: Escludi da `AssignedToMeStory`**

`app/Nova/AssignedToMeStory.php:21` — mantenere lo stile esistente, che passa gli enum e non i `->value`:

```php
            ->whereNotIn('status', [StoryStatus::New, StoryStatus::Done, StoryStatus::PendingRelease]);
```

- [ ] **Step 6: Lancia il test e verifica che passi**

Run:
```bash
docker exec php81_orchestrator php artisan test --filter=PendingReleaseStatusTest
```
Expected: PASS (15 test)

- [ ] **Step 7: Commit** *(istruzione per il developer — non eseguire)*

```bash
git add app/Nova/CustomerStory.php app/Nova/DeveloperStory.php app/Nova/AssignedToMeStory.php tests/Feature/PendingReleaseStatusTest.php
git commit -m "feat(oc:8426): escludi pending_release dalle liste interne del team"
```

---

## Task 4: `pending_release` conta come chiuso nel SAL

**Files:**
- Modify: `app/Models/Tag.php:93-100`
- Test: `tests/Feature/PendingReleaseStatusTest.php` (append)

**Interfaces:**
- Consumes: `StoryStatus::PendingRelease` da Task 1
- Produces: `Tag::salClosedStoryStatusValues()` include `'pending_release'`; consumato da `Tag::salTicketCounts()`, `Tag::isClosed()`, `app/Nova/Tag.php:57`, `app/Nova/TagGroup.php:70,108`

- [ ] **Step 1: Scrivi il test che falla**

`use` aggiuntivo: `use App\Models\Tag;`

```php
    /**
     * Il SAL e' una metrica INTERNA (Tag e TagGroup vivono nella MenuSection('DEV'),
     * visibile solo a Admin/Manager/Developer). Quando il developer dichiara un ticket
     * concluso e in attesa di rilascio, il lavoro sull'RDO e' erogato e il SAL deve
     * rifletterlo. `tested` resta invece FUORI: decisione esplicita, vedi overview.md.
     */
    public function test_pending_release_conta_come_chiuso_nel_sal()
    {
        $this->assertContains('pending_release', Tag::salClosedStoryStatusValues());
        $this->assertNotContains('tested', Tag::salClosedStoryStatusValues());
    }

    public function test_sal_ticket_counts_include_i_pending_release()
    {
        $tag = Tag::create(['name' => 'rdo-test-8426']);

        $pending = $this->makeStory(['status' => StoryStatus::PendingRelease->value]);
        $tested = $this->makeStory(['status' => StoryStatus::Tested->value]);
        $released = $this->makeStory(['status' => StoryStatus::Released->value]);

        $tag->tagged()->attach([$pending->id, $tested->id, $released->id]);

        [$closed, $total] = $tag->salTicketCounts();

        $this->assertSame(3, $total);
        $this->assertSame(2, $closed, 'pending_release e released contano come chiusi, tested no');
    }

    public function test_un_tag_con_soli_pending_release_e_considerato_chiuso()
    {
        $tag = Tag::create(['name' => 'rdo-test-8426-closed']);

        $tag->tagged()->attach($this->makeStory(['status' => StoryStatus::PendingRelease->value])->id);

        $this->assertTrue($tag->isClosed());
    }
```

**Attenzione:** `Tag::tagged()` è la relazione `morphedByMany` sul pivot `taggables` — è quella da usare per legare Story a Tag. **Non** usare `taggable()`, che è il `morphTo` verso il parent del tag (vedi `CLAUDE.md` → decisioni oc:8155). Se `Tag::create(['name' => ...])` fallisce per campi obbligatori, ispezionare la migration dei tag e aggiungere il minimo necessario.

- [ ] **Step 2: Lancia il test e verifica che fallisca**

Run:
```bash
docker exec php81_orchestrator php artisan test --filter=PendingReleaseStatusTest
```
Expected: FAIL — `Failed asserting that array contains 'pending_release'`

- [ ] **Step 3: Aggiungi lo stato al SAL**

`app/Models/Tag.php:93-100`:

```php
    public static function salClosedStoryStatusValues(): array
    {
        return [
            StoryStatus::PendingRelease->value,
            StoryStatus::Released->value,
            StoryStatus::Done->value,
            StoryStatus::Rejected->value,
        ];
    }
```

- [ ] **Step 4: Lancia il test e verifica che passi**

Run:
```bash
docker exec php81_orchestrator php artisan test --filter=PendingReleaseStatusTest
```
Expected: PASS (18 test)

- [ ] **Step 5: Verifica che il SAL esistente non si sia rotto**

Run:
```bash
docker exec php81_orchestrator php artisan test --filter="TagSalTest|TagHoursMetricsTest|QuarterTagFilterTest"
```
Expected: PASS. Questi test coprono il rendering dei campi `SAL t` e `SAL #`: un fallimento qui indica che l'aggiunta ha alterato un conteggio atteso.

- [ ] **Step 6: Commit** *(istruzione per il developer — non eseguire)*

```bash
git add app/Models/Tag.php tests/Feature/PendingReleaseStatusTest.php
git commit -m "feat(oc:8426): pending_release conta come chiuso nel SAL dei tag"
```

---

## Task 5: `pending_release` è stato avanzato per i reopen

**Files:**
- Modify: `app/Services/Metrics/StoryMetricsCalculator.php:18`
- Test: `tests/Feature/PendingReleaseStatusTest.php` (append)

**Interfaces:**
- Consumes: `StoryStatus::PendingRelease` da Task 1
- Produces: `FORWARD_STATUSES` include `'pending_release'`; consumato da `StoryMetricsCalculator::reopenCount()`

- [ ] **Step 1: Scrivi il test che falla**

`use` aggiuntivi:

```php
use App\Models\StoryLog;
use App\Services\Metrics\StoryMetricsCalculator;
```

```php
    /**
     * getStatusLogs() usa una cache statica self::$logCache[$storyId] senza metodo
     * pubblico di reset: ogni test deve usare una Story NUOVA (id diverso), altrimenti
     * legge i log del test precedente. Non riusare la stessa story fra due asserzioni.
     */
    private function logStatusChange(Story $story, string $status, string $viewedAt): void
    {
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $story->user_id,
            'changes' => ['status' => $status],
            'viewed_at' => $viewedAt,
        ]);
    }

    public function test_pending_release_e_uno_stato_avanzato_per_i_reopen()
    {
        $story = $this->makeStory(['status' => StoryStatus::Todo->value]);

        // pending_release -> todo = il cliente ha bocciato: e' una rilavorazione
        $this->logStatusChange($story, StoryStatus::PendingRelease->value, '2026-08-01 10:00:00');
        $this->logStatusChange($story, StoryStatus::Todo->value, '2026-08-02 10:00:00');

        $this->assertSame(1, (new StoryMetricsCalculator())->reopenCount($story->id));
    }

    public function test_avanzare_da_pending_release_a_released_non_e_un_reopen()
    {
        $story = $this->makeStory(['status' => StoryStatus::Released->value]);

        $this->logStatusChange($story, StoryStatus::PendingRelease->value, '2026-08-01 10:00:00');
        $this->logStatusChange($story, StoryStatus::Released->value, '2026-08-02 10:00:00');

        $this->assertSame(0, (new StoryMetricsCalculator())->reopenCount($story->id));
    }
```

- [ ] **Step 2: Lancia il test e verifica che fallisca**

Run:
```bash
docker exec php81_orchestrator php artisan test --filter=PendingReleaseStatusTest
```
Expected: FAIL sul primo dei due (`0` invece di `1`); il secondo passa già.

- [ ] **Step 3: Aggiungi lo stato a `FORWARD_STATUSES`**

`app/Services/Metrics/StoryMetricsCalculator.php:18`:

```php
    private const FORWARD_STATUSES = ['testing', 'tested', 'pending_release', 'released', 'done'];
```

**Non** modificare `$closedInQuarter` (righe ~310-316): continua a contare solo `done` e `released`. `pending_release` non è rilasciato e contarlo lì gonfierebbe la produttività con lavoro non consegnato.

- [ ] **Step 4: Lancia il test e verifica che passi**

Run:
```bash
docker exec php81_orchestrator php artisan test --filter=PendingReleaseStatusTest
```
Expected: PASS (20 test)

- [ ] **Step 5: Lancia la suite completa**

Run:
```bash
docker exec php81_orchestrator php artisan test
```
Expected: PASS. Confrontare il numero di test falliti con quello di *prima* di iniziare il ticket: se esistevano fallimenti preesistenti, non attribuirli a questo lavoro né tentare di risolverli qui.

- [ ] **Step 6: Commit** *(istruzione per il developer — non eseguire)*

```bash
git add app/Services/Metrics/StoryMetricsCalculator.php tests/Feature/PendingReleaseStatusTest.php
git commit -m "feat(oc:8426): conta pending_release come stato avanzato nei reopen"
```

---

## Verifica manuale finale (prima della PR)

Il piano non la sostituisce: alcuni difetti emergono solo usando l'interfaccia.

- [ ] Dashboard Kanban: la colonna "In attesa di rilascio" esiste, è teal, sta fra "È stato testato" e "Rilasciato"
- [ ] Drag&drop di una card da "Testato" alla nuova colonna: lo stato viene salvato e la card resta dov'è dopo un refresh
- [ ] Detail di un ticket in `pending_release`: il badge di stato mostra **"In attesa di rilascio"**, non `Pending_release`
- [ ] Form di edit: il Select "Status" mostra **"In attesa di rilascio"**, non `Pending_release`
- [ ] Il ticket **non** appare in `/resources/developer-stories`, `/resources/assigned-to-me-stories`, `/resources/customer-stories`
- [ ] Il ticket **appare** in `/resources/story-showed-by-customers` accedendo come utente customer che lo ha creato
- [ ] Il filtro "Status" nelle liste offre l'opzione tradotta e filtra correttamente
- [ ] Detail di un Tag che contiene ticket in `pending_release`: `SAL #` e `SAL t` li contano come chiusi

## Note per il rollback

Il revert del codice **non basta**: le righe con `status = 'pending_release'` resterebbero a DB e diventerebbero orfane (`label()` senza `default` solleva `UnhandledMatchError` dove il valore viene risolto in enum). Un rollback completo richiede anche:

```sql
UPDATE stories SET status = 'tested' WHERE status = 'pending_release';
```

Farlo via SQL grezzo lascia lo storico `StoryLog` incoerente; farlo via modello riscatena eventi (calendario, notifiche). I ticket già usciti dal Google Calendar non vi rientrano da soli: dipendono dal prossimo `sync:stories-calendar` schedulato.
