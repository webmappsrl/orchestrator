> Ticket: oc:8631

# Preventivi via API: campi di testo formattato — Piano di implementazione

> **Per chi esegue:** SUB-SKILL RICHIESTA: `superpowers:subagent-driven-development` (consigliata) oppure `superpowers:executing-plans`. Gli step usano le checkbox (`- [ ]`).
>
> **⚠️ Nessun commit automatico.** Le righe "Commit" sono istruzioni testuali per il dev: non eseguire `git add`/`git commit`/`git push`, non creare branch. I commit li gestisce il review-gate di `wm-plan` dopo l'approvazione del dev.

**Obiettivo:** leggere e scrivere via API i quattro campi rich-text del preventivo (`additional_info`, `delivery_time`, `payment_plan`, `billing_plan`), rifiutando con 422 comprensibili l'HTML pericoloso e i prezzi illeggibili, e stampare il Piano di fatturazione nel PDF.

**Architettura:** un servizio `RichTextHtmlInspector` analizza l'HTML con `masterminds/html5` (lo stesso parser di DomPDF) e restituisce le violazioni raggruppate; la Rule `SafeRichTextHtml` e il comando `quotes:check-rich-text` lo riusano. La Rule `AdditionalServicesMap` valida forma e prezzi di `additional_services` con la stessa grammatica che template e totale sanno leggere. Il controller estende il meccanismo esistente `TRANSLATABLE_FIELDS` (sola lingua di default).

**Tech stack:** Laravel 12, Nova 4 + `kongulov/nova-tab-translatable` + `marshmallow/nova-tiptap` ^6, `spatie/laravel-translatable`, `masterminds/html5` (già in `vendor/`, dipendenza di DomPDF), PHPUnit 11 (stile `/** @test */` + nomi in italiano snake_case), Scramble.

**Spec:** [overview.md](overview.md) — le note di esecuzione vanno in [notes.md](notes.md).

## Vincoli globali

- Tutti i comandi PHP girano nel container: `docker exec php81_orchestrator …`; i test sul DB `orchestrator_test` (default di `phpunit.xml`), mai su `orchestrator`.
- Lingua di scrittura/lettura via API: solo `config('app.locale')` (`it`).
- Lunghezza massima per campo rich-text: **50.000** caratteri.
- Messaggi 422: formato standard Laravel `{message, errors}`; ogni messaggio dice cosa è sbagliato e cosa è corretto; violazioni raggruppate per tipo con conteggio, **max 10 voci per campo** + «…e altri N».
- Prezzi: numero JSON oppure stringa `^-?\d+([.,]\d{1,2})?$`; nessun separatore delle migliaia.
- Testi traducibili: chiave base in inglese, traduzione in `lang/it.json` **e** `lang/en.json`. Attenzione alle chiavi duplicate nei JSON (l'ultima vince): cercare sempre con `grep` prima di aggiungere.
- Documentazione, commenti, messaggi di commit in italiano; scope commit `feat(oc:8631): …`.
- Contratto API consumato dalla skill `wm-preventivi` (fuori repo): nessuna chiave esistente rinominata o rimossa.

## Focus della review

1. **Round-trip di contenuto Nova reale**: un campo letto via GET e rimandato identico via PATCH deve passare — incluso `<img tt-mode="file" src="/storage/…">`, `<p style="text-align: center;">`, `<td colspan="1" rowspan="1" colwidth="200">`, `<span class="…">`. Coperto in Task 3 e Task 5.
2. **URL di immagine ingannevoli** (`//evil.com/x.png`, `https://<app-host>.evil.com/…`, `https://user@evil.com/…`, `data:image/png;base64,…`): devono essere rifiutati, perché DomPDF ha `enable_remote => true`. Coperto in Task 3.
3. **Prezzi all'italiana con migliaia** (`"1.234,56"`) e **liste** (`[150, 200]`) in `additional_services`: devono dare 422, non un PDF rotto o un totale sbagliato. Coperto in Task 4.
4. **HTML incollato da Word con centinaia di violazioni**: il 422 resta corto (max 10 voci + «…e altri N»). Coperto in Task 3.
5. **PATCH che non manda i quattro campi**: i valori esistenti (anche sotto `de`) restano intatti. Coperto in Task 5.

---

### Task 1: Verificare (ed eventualmente correggere) la lingua con cui Nova salva i Tiptap

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-1--verifica-lingua-di-salvataggio-nova)

Contesto: i preventivi 4 e 6 (ultimi salvati da Nova, aprile 2026, DB locale) hanno `payment_plan`/`delivery_time` solo sotto `de`, l'ultima lingua di `config/tab-translatable.php` (`it, en, fr, es, de`). I Tiptap sono dentro `NovaTabTranslatable::make([...])` in `app/Nova/Quote.php:250-266`. Il pacchetto crea un clone per lingua con attribute `translations_<campo>_<locale>` e un `fillUsing` che chiama `setTranslation($campo, $locale, …)` (`vendor/kongulov/nova-tab-translatable/src/NovaTabTranslatable.php:128,160-173`). Il sospetto è che il clone del Tiptap non mantenga la propria lingua (lato PHP) oppure che il componente Vue invii il valore sotto la chiave dell'ultima tab (lato JS).

**File:**
- Test: `tests/Feature/QuoteNovaTiptapLocaleTest.php` (nuovo)
- Eventuale fix: `app/Nova/Quote.php` (solo se la causa è lato PHP)

**Interfacce:**
- Produce: nessuna interfaccia per i task successivi; solo l'esito in `notes.md`.

- [ ] **Step 1: Scrivere il test lato backend**

```php
<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Nova\Quote as QuoteResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\Http\Requests\NovaRequest;
use Tests\TestCase;

class QuoteNovaTiptapLocaleTest extends TestCase
{
    use DatabaseTransactions;

    private function flatten(array $fields): array
    {
        $flat = [];
        foreach ($fields as $field) {
            if ($field instanceof \Laravel\Nova\Fields\FieldMergeValue || $field instanceof \Illuminate\Http\Resources\MergeValue) {
                $flat = array_merge($flat, $this->flatten($field->data));
            } else {
                $flat[] = $field;
            }
        }
        return $flat;
    }

    /** @test */
    public function il_tiptap_della_tab_it_salva_sotto_la_chiave_it(): void
    {
        $quote = Quote::create(['title' => 'T', 'customer_id' => Customer::factory()->create()->id]);

        foreach (['additional_info', 'delivery_time', 'payment_plan', 'billing_plan'] as $campo) {
            $payload = [];
            foreach (config('tab-translatable.locales') as $locale) {
                $payload["translations_{$campo}_{$locale}"] = $locale === 'it' ? "<p>{$campo} it</p>" : '';
            }
            $request = NovaRequest::create('/nova-api/quotes/' . $quote->id, 'PUT', $payload);

            $fields = $this->flatten((new QuoteResource($quote))->fields($request));
            foreach ($fields as $field) {
                if (str_starts_with($field->attribute ?? '', "translations_{$campo}_")) {
                    $field->fill($request, $quote);
                }
            }

            $this->assertSame("<p>{$campo} it</p>", $quote->getTranslation($campo, 'it', false), "{$campo}: testo della tab it non salvato sotto it");
        }
    }
}
```

Se `flatten()` non trova i campi `translations_*` (perché `NovaTabTranslatable` o `Tab::group` usano un altro contenitore), ispezionare con `dd(array_map(fn($f) => get_class($f), …))` e aggiungere il `instanceof` giusto.

- [ ] **Step 2: Eseguire il test**

Run: `docker exec php81_orchestrator php artisan test --filter=QuoteNovaTiptapLocaleTest`

- **FAIL** → la causa è lato PHP: passare allo Step 3.
- **PASS** → il backend salva correttamente; passare allo Step 4 (verifica lato browser).

- [ ] **Step 3 (solo se FAIL): individuare e correggere la causa lato PHP**

Confrontare `$field->meta['locale']` e l'attribute di ogni clone Tiptap con quelli di un campo `Text` (es. `title`) nello stesso test. Correggere in `app/Nova/Quote.php` con il minimo intervento (es. registrare esplicitamente `fillUsing` per il clone, o costruire i quattro Tiptap fuori dal clone). Rieseguire il test fino a PASS. **Se la correzione richiede di modificare file in `vendor/`, fermarsi e riportare al dev le opzioni** (patch con `cweagans/composer-patches`, sostituzione del campo, segnalazione upstream): non è una decisione da prendere in autonomia.

- [ ] **Step 4 (solo se PASS): verifica lato browser**

Avviare Nova in locale, aprire un preventivo, scrivere un testo nella tab **IT** di *Piano di pagamento*, salvare, poi:

Run: `docker exec php81_orchestrator php artisan tinker --execute="echo json_encode(App\Models\Quote::find(<ID>)->getTranslations('payment_plan'));"`

- chiave `it` → nessun bug attuale: annotare in `notes.md` (sezione Decisioni) «bug `de` non riproducibile, dati storici» e chiudere il task.
- chiave `de` (o altra) → la causa è lato JS (payload): ispezionare con gli strumenti del browser la richiesta `PUT /nova-api/quotes/<ID>` e verificare con quale chiave arriva il valore. **Fermarsi e riportare al dev** l'evidenza e le opzioni: la correzione tocca il componente Vue di un pacchetto terzo.

- [ ] **Step 5: annotare l'esito in `notes.md`**

Registrare causa trovata, file toccati o motivo per cui non serve intervento.

- [ ] **Step 6: Commit (istruzione per il dev)**

```bash
git add tests/Feature/QuoteNovaTiptapLocaleTest.php app/Nova/Quote.php docs/features/8631-preventivi-via-api-campi-testo-formattato/notes.md
git commit -m "fix(oc:8631): i Tiptap del preventivo salvano sotto la lingua della propria tab"
```

(Se non è servita alcuna correzione, il commit contiene solo il test di non regressione: `test(oc:8631): …`.)

---

### Task 2: `RichTextHtmlInspector` — analisi dell'HTML pericoloso

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#review-finale--correzioni-di-sicurezza)

**File:**
- Crea: `app/Services/Quotes/RichTextHtmlInspector.php`
- Test: `tests/Unit/Services/RichTextHtmlInspectorTest.php`

**Interfacce:**
- Produce:
  - `RichTextHtmlInspector::__construct(?string $appHost = null)` — default `parse_url(config('app.url'), PHP_URL_HOST)`.
  - `public function inspect(string $html): array` — lista di violazioni raggruppate, ognuna `['kind' => 'tag'|'attribute'|'style'|'url', 'name' => string, 'count' => int]`, ordinata per ordine di prima apparizione. Lista vuota = HTML accettabile.
  - Costanti pubbliche `ALLOWED_TAGS` (array di stringhe) e `DANGEROUS_STYLE_TOKENS`.

Regole (dall'overview):
- Tag ammessi: `p br div span strong b em i u s strike mark code pre blockquote h1 h2 h3 h4 h5 h6 ul ol li hr a img table thead tbody tfoot tr th td colgroup col caption font sup sub small`. Ogni altro elemento → violazione `tag`. (`html`, `head`, `body` creati dal parser del frammento non vanno contati.)
- Attributi: vietati solo quelli che iniziano per `on` (case-insensitive) → violazione `attribute` con nome dell'attributo.
- `style`: violazione `style` (name = token trovato) se il valore, in minuscolo e senza spazi, contiene `url(`, `expression(`, `@import` o `javascript:`.
- `href` (su qualsiasi tag): ammesso se vuoto, se inizia con `#`, o se lo schema (dopo `trim` e minuscolo) è `http`, `https`, `mailto`, oppure se è relativo senza schema e non inizia con `//`. Altrimenti violazione `url` (name = `href`).
- `src` (su qualsiasi tag): ammesso se relativo (nessuno schema, non inizia con `//`) oppure se `parse_url` dà schema `http`/`https`, **nessun** `user`/`pass`, e `host` **identico** (case-insensitive) a `$appHost`. Altrimenti violazione `url` (name = `src`).
- Commenti e testo: ignorati.

- [ ] **Step 1: Scrivere i test che falliscono**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\Quotes\RichTextHtmlInspector;
use PHPUnit\Framework\TestCase;

class RichTextHtmlInspectorTest extends TestCase
{
    private function inspector(): RichTextHtmlInspector
    {
        return new RichTextHtmlInspector('orchestrator.example.it');
    }

    /** @test */
    public function html_prodotto_da_nova_non_ha_violazioni(): void
    {
        $html = '<p style="text-align: center;" dir="ltr">Testo <strong>forte</strong> <a href="https://webmapp.it" target="_blank" tt-mode="url">link</a></p>'
            . '<ul><li>20% alla firma</li></ul><div><span class="x">a</span></div>'
            . '<table><tbody><tr><td colspan="1" rowspan="1" colwidth="200">c</td></tr></tbody></table>'
            . '<img src="/storage/tiptap/logo.png" tt-mode="file" alt="logo" title="Logo">'
            . '<img src="https://orchestrator.example.it/storage/a.png"><font color="red">f</font><sup>1</sup>';

        $this->assertSame([], $this->inspector()->inspect($html));
    }

    /** @test */
    public function tag_pericolosi_sono_raggruppati_con_conteggio(): void
    {
        $violations = $this->inspector()->inspect('<script>a</script><p>x</p><script>b</script><iframe src="/x"></iframe>');

        $this->assertContains(['kind' => 'tag', 'name' => 'script', 'count' => 2], $violations);
        $this->assertContains(['kind' => 'tag', 'name' => 'iframe', 'count' => 1], $violations);
    }

    /** @test */
    public function handler_on_sono_rifiutati(): void
    {
        $this->assertSame(
            [['kind' => 'attribute', 'name' => 'onclick', 'count' => 2]],
            $this->inspector()->inspect('<p onclick="x()">a</p><span OnClick="y()">b</span>')
        );
    }

    /** @test */
    public function style_con_url_o_expression_e_rifiutato(): void
    {
        $violations = $this->inspector()->inspect('<p style="background: URL(http://evil.com/x.png)">a</p><p style="width: expression(alert(1))">b</p>');

        $this->assertContains(['kind' => 'style', 'name' => 'url(', 'count' => 1], $violations);
        $this->assertContains(['kind' => 'style', 'name' => 'expression(', 'count' => 1], $violations);
    }

    /** @test */
    public function href_javascript_e_rifiutato(): void
    {
        $this->assertSame(
            [['kind' => 'url', 'name' => 'href', 'count' => 1]],
            $this->inspector()->inspect('<a href=" JavaScript:alert(1)">x</a><a href="mailto:a@b.it">m</a><a href="#top">t</a>')
        );
    }

    /**
     * @test
     * @dataProvider srcIngannevoli
     */
    public function src_fuori_dall_host_dell_app_e_rifiutato(string $src): void
    {
        $this->assertSame(
            [['kind' => 'url', 'name' => 'src', 'count' => 1]],
            $this->inspector()->inspect('<img src="' . $src . '">')
        );
    }

    public static function srcIngannevoli(): array
    {
        return [
            'protocol-relative' => ['//evil.com/x.png'],
            'host con suffisso' => ['https://orchestrator.example.it.evil.com/x.png'],
            'credenziali'       => ['https://orchestrator.example.it@evil.com/x.png'],
            'data uri'          => ['data:image/png;base64,AAAA'],
            'host esterno'      => ['https://evil.com/x.png'],
        ];
    }
}
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `docker exec php81_orchestrator php artisan test --filter=RichTextHtmlInspectorTest`
Atteso: FAIL con `Class "App\Services\Quotes\RichTextHtmlInspector" not found`.

- [ ] **Step 3: Implementare**

```php
<?php

namespace App\Services\Quotes;

use DOMElement;
use DOMNode;
use Masterminds\HTML5;

/**
 * Analizza l'HTML dei campi rich-text del preventivo e riporta solo ciò che
 * può eseguire codice o caricare risorse esterne (oc:8631). Usa lo stesso
 * parser di DomPDF, così validatore e rendering leggono l'HTML allo stesso modo.
 */
class RichTextHtmlInspector
{
    public const ALLOWED_TAGS = [
        'p', 'br', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'mark', 'code',
        'pre', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'hr', 'a',
        'img', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'colgroup', 'col',
        'caption', 'font', 'sup', 'sub', 'small',
    ];

    public const DANGEROUS_STYLE_TOKENS = ['url(', 'expression(', '@import', 'javascript:'];

    private const SAFE_HREF_SCHEMES = ['http', 'https', 'mailto'];

    private string $appHost;

    /** @var array<string, array{kind: string, name: string, count: int}> */
    private array $violations = [];

    public function __construct(?string $appHost = null)
    {
        $this->appHost = strtolower($appHost ?? (string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    public function inspect(string $html): array
    {
        $this->violations = [];
        $fragment = (new HTML5(['disable_html_ns' => true]))->loadHTMLFragment($html);

        foreach ($fragment->childNodes as $node) {
            $this->walk($node);
        }

        return array_values($this->violations);
    }

    private function walk(DOMNode $node): void
    {
        if ($node instanceof DOMElement) {
            $this->checkElement($node);
        }
        foreach ($node->childNodes ?? [] as $child) {
            $this->walk($child);
        }
    }

    private function checkElement(DOMElement $element): void
    {
        $tag = strtolower($element->tagName);
        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            $this->add('tag', $tag);
        }

        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->name);
            $value = (string) $attribute->value;

            if (str_starts_with($name, 'on')) {
                $this->add('attribute', $name);
            } elseif ($name === 'style') {
                $this->checkStyle($value);
            } elseif ($name === 'href' && ! $this->isSafeHref($value)) {
                $this->add('url', 'href');
            } elseif ($name === 'src' && ! $this->isSafeSrc($value)) {
                $this->add('url', 'src');
            }
        }
    }

    private function checkStyle(string $style): void
    {
        $normalized = strtolower(preg_replace('/\s+/', '', $style));
        foreach (self::DANGEROUS_STYLE_TOKENS as $token) {
            if (str_contains($normalized, $token)) {
                $this->add('style', $token);
            }
        }
    }

    private function isSafeHref(string $href): bool
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#')) {
            return true;
        }
        $scheme = parse_url($href, PHP_URL_SCHEME);
        if ($scheme === null || $scheme === false) {
            return ! str_starts_with($href, '//') && ! preg_match('/^[a-z][a-z0-9+.-]*:/i', $href);
        }
        return in_array(strtolower($scheme), self::SAFE_HREF_SCHEMES, true);
    }

    private function isSafeSrc(string $src): bool
    {
        $src = trim($src);
        if (str_starts_with($src, '//')) {
            return false;
        }
        $parts = parse_url($src);
        if ($parts === false) {
            return false;
        }
        if (! isset($parts['scheme'])) {
            return ! preg_match('/^[a-z][a-z0-9+.-]*:/i', $src);
        }
        return in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && strtolower($parts['host'] ?? '') === $this->appHost;
    }

    private function add(string $kind, string $name): void
    {
        $key = $kind . ':' . $name;
        $this->violations[$key] ??= ['kind' => $kind, 'name' => $name, 'count' => 0];
        $this->violations[$key]['count']++;
    }
}
```

Nota: `html`/`head`/`body` non compaiono con `loadHTMLFragment`; se compaiono, escluderli esplicitamente in `checkElement`.

- [ ] **Step 4: Eseguire i test e verificare che passino**

Run: `docker exec php81_orchestrator php artisan test --filter=RichTextHtmlInspectorTest`
Atteso: PASS (7 test, 5 casi nel data provider).

- [ ] **Step 5: Commit (istruzione per il dev)**

```bash
git add app/Services/Quotes/RichTextHtmlInspector.php tests/Unit/Services/RichTextHtmlInspectorTest.php
git commit -m "feat(oc:8631): analisi dell'HTML pericoloso nei campi rich-text del preventivo"
```

---

### Task 3: Rule `SafeRichTextHtml` con messaggi 422 comprensibili

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#review-finale--correzioni-di-sicurezza)

**File:**
- Crea: `app/Rules/SafeRichTextHtml.php` (prima Rule custom del progetto: creare la cartella `app/Rules/`)
- Modifica: `lang/en.json`, `lang/it.json`
- Test: `tests/Unit/Rules/SafeRichTextHtmlTest.php`

**Interfacce:**
- Consuma: `RichTextHtmlInspector::inspect(string): array` (Task 2).
- Produce: `new SafeRichTextHtml()` — `Illuminate\Contracts\Validation\ValidationRule`; usata in Task 5 come `['sometimes', 'nullable', 'string', 'max:50000', new SafeRichTextHtml()]`. Costante `SafeRichTextHtml::MAX_LISTED = 10`.

Formato del messaggio (un solo messaggio per campo, righe separate da `; `):
- `tag` → «tag <script> non ammesso (2 occorrenze): rimuovilo, può eseguire codice o incorporare contenuti esterni»
- `attribute` → «attributo onclick non ammesso (5 occorrenze): rimuovi gli attributi che iniziano con "on", eseguono codice»
- `style` → «style con url( non ammesso (1 occorrenza): nello style non usare url(, expression(, @import o javascript:»
- `url` href → «link non ammesso (1 occorrenza): href deve iniziare con https://, http://, mailto: o #»
- `url` src → «immagine non ammessa (1 occorrenza): src deve essere un percorso relativo (es. /storage/…) o un URL su <host>»
- oltre 10 voci → «…e altri N tipi di elementi non ammessi»

Il messaggio completo: «Il campo :attribute contiene HTML non ammesso: <righe>.»

- [ ] **Step 1: Aggiungere le chiavi di traduzione**

Prima: `grep -n 'contains HTML that is not allowed\|occurrence' lang/en.json lang/it.json` (nessun risultato atteso). Aggiungere in `lang/en.json`:

```json
  "The :attribute field contains HTML that is not allowed: :details.": "The :attribute field contains HTML that is not allowed: :details.",
  "tag <:name> not allowed (:count :occurrences): remove it, it can run code or embed external content": "tag <:name> not allowed (:count :occurrences): remove it, it can run code or embed external content",
  "attribute :name not allowed (:count :occurrences): remove attributes starting with \"on\", they run code": "attribute :name not allowed (:count :occurrences): remove attributes starting with \"on\", they run code",
  "style with :name not allowed (:count :occurrences): do not use url(, expression(, @import or javascript: in style": "style with :name not allowed (:count :occurrences): do not use url(, expression(, @import or javascript: in style",
  "link not allowed (:count :occurrences): href must start with https://, http://, mailto: or #": "link not allowed (:count :occurrences): href must start with https://, http://, mailto: or #",
  "image not allowed (:count :occurrences): src must be a relative path (e.g. /storage/...) or a URL on :host": "image not allowed (:count :occurrences): src must be a relative path (e.g. /storage/...) or a URL on :host",
  "...and :count more kinds of elements not allowed": "...and :count more kinds of elements not allowed",
  "occurrence": "occurrence",
  "occurrences": "occurrences",
```

e in `lang/it.json` le stesse chiavi con:

```json
  "The :attribute field contains HTML that is not allowed: :details.": "Il campo :attribute contiene HTML non ammesso: :details.",
  "tag <:name> not allowed (:count :occurrences): remove it, it can run code or embed external content": "tag <:name> non ammesso (:count :occurrences): rimuovilo, può eseguire codice o incorporare contenuti esterni",
  "attribute :name not allowed (:count :occurrences): remove attributes starting with \"on\", they run code": "attributo :name non ammesso (:count :occurrences): rimuovi gli attributi che iniziano con \"on\", eseguono codice",
  "style with :name not allowed (:count :occurrences): do not use url(, expression(, @import or javascript: in style": "style con :name non ammesso (:count :occurrences): nello style non usare url(, expression(, @import o javascript:",
  "link not allowed (:count :occurrences): href must start with https://, http://, mailto: or #": "link non ammesso (:count :occurrences): href deve iniziare con https://, http://, mailto: o #",
  "image not allowed (:count :occurrences): src must be a relative path (e.g. /storage/...) or a URL on :host": "immagine non ammessa (:count :occurrences): src deve essere un percorso relativo (es. /storage/...) o un URL su :host",
  "...and :count more kinds of elements not allowed": "...e altri :count tipi di elementi non ammessi",
  "occurrence": "occorrenza",
  "occurrences": "occorrenze",
```

Verificare il JSON: `docker exec php81_orchestrator php -r 'foreach (["lang/en.json","lang/it.json"] as $f) { json_decode(file_get_contents($f)); echo $f, ": ", json_last_error_msg(), PHP_EOL; }'` → `No error` per entrambi.

- [ ] **Step 2: Scrivere i test che falliscono**

```php
<?php

namespace Tests\Unit\Rules;

use App\Rules\SafeRichTextHtml;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SafeRichTextHtmlTest extends TestCase
{
    private function errore(?string $html): ?string
    {
        App::setLocale('it');
        $validator = Validator::make(['payment_plan' => $html], ['payment_plan' => ['nullable', 'string', new SafeRichTextHtml()]]);
        return $validator->errors()->first('payment_plan') ?: null;
    }

    /** @test */
    public function html_ammesso_e_null_passano(): void
    {
        $this->assertNull($this->errore('<p><strong>20%</strong> alla firma</p>'));
        $this->assertNull($this->errore(null));
    }

    /** @test */
    public function il_messaggio_dice_cosa_e_sbagliato_e_cosa_fare(): void
    {
        $messaggio = $this->errore('<p onclick="x()">a</p><script>1</script><script>2</script>');

        $this->assertStringContainsString('payment plan', strtolower(str_replace('_', ' ', $messaggio)));
        $this->assertStringContainsString('tag <script> non ammesso (2 occorrenze)', $messaggio);
        $this->assertStringContainsString('rimuovilo', $messaggio);
        $this->assertStringContainsString('attributo onclick non ammesso (1 occorrenza)', $messaggio);
    }

    /** @test */
    public function oltre_dieci_tipi_il_messaggio_resta_corto(): void
    {
        $html = '';
        foreach (['script', 'iframe', 'object', 'embed', 'style', 'form', 'input', 'svg', 'math', 'base', 'meta', 'link'] as $tag) {
            $html .= str_repeat("<{$tag}></{$tag}>", 50);
        }

        $messaggio = $this->errore($html);

        $this->assertStringContainsString('e altri 2 tipi di elementi non ammessi', $messaggio);
        $this->assertLessThan(1500, strlen($messaggio));
    }
}
```

- [ ] **Step 3: Eseguire i test e verificare che falliscano**

Run: `docker exec php81_orchestrator php artisan test --filter=SafeRichTextHtmlTest`
Atteso: FAIL con `Class "App\Rules\SafeRichTextHtml" not found`.

- [ ] **Step 4: Implementare**

```php
<?php

namespace App\Rules;

use App\Services\Quotes\RichTextHtmlInspector;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rifiuta solo l'HTML che può eseguire codice o caricare risorse esterne
 * (oc:8631). Il messaggio dice cosa togliere, raggruppato per tipo.
 */
class SafeRichTextHtml implements ValidationRule
{
    public const MAX_LISTED = 10;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $violations = app(RichTextHtmlInspector::class)->inspect($value);
        if ($violations === []) {
            return;
        }

        $details = array_map(fn (array $v) => $this->describe($v), array_slice($violations, 0, self::MAX_LISTED));
        $rest = count($violations) - self::MAX_LISTED;
        if ($rest > 0) {
            $details[] = __('...and :count more kinds of elements not allowed', ['count' => $rest]);
        }

        $fail(__('The :attribute field contains HTML that is not allowed: :details.', [
            'attribute' => $attribute,
            'details'   => implode('; ', $details),
        ]));
    }

    private function describe(array $violation): string
    {
        $replace = [
            'name'        => $violation['name'],
            'count'       => $violation['count'],
            'occurrences' => __($violation['count'] === 1 ? 'occurrence' : 'occurrences'),
            'host'        => (string) parse_url((string) config('app.url'), PHP_URL_HOST),
        ];

        return match (true) {
            $violation['kind'] === 'tag'       => __('tag <:name> not allowed (:count :occurrences): remove it, it can run code or embed external content', $replace),
            $violation['kind'] === 'attribute' => __('attribute :name not allowed (:count :occurrences): remove attributes starting with "on", they run code', $replace),
            $violation['kind'] === 'style'     => __('style with :name not allowed (:count :occurrences): do not use url(, expression(, @import or javascript: in style', $replace),
            $violation['name'] === 'href'      => __('link not allowed (:count :occurrences): href must start with https://, http://, mailto: or #', $replace),
            default                            => __('image not allowed (:count :occurrences): src must be a relative path (e.g. /storage/...) or a URL on :host', $replace),
        };
    }
}
```

Nota: `$fail()` riceve un messaggio già tradotto; il placeholder `:attribute` è sostituito da noi con il nome del campo, perché passare un messaggio già tradotto al validator non ri-applica le sostituzioni standard.

- [ ] **Step 5: Eseguire i test e verificare che passino**

Run: `docker exec php81_orchestrator php artisan test --filter="SafeRichTextHtmlTest|RichTextHtmlInspectorTest"`
Atteso: PASS.

- [ ] **Step 6: Commit (istruzione per il dev)**

```bash
git add app/Rules/SafeRichTextHtml.php tests/Unit/Rules/SafeRichTextHtmlTest.php lang/en.json lang/it.json
git commit -m "feat(oc:8631): regola di validazione dell'HTML rich-text con messaggi comprensibili"
```

---

### Task 4: Rule `AdditionalServicesMap` — forma e prezzi dei servizi aggiuntivi

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#cleanup-della-review-wm-review-ticket)

**File:**
- Crea: `app/Rules/AdditionalServicesMap.php`
- Modifica: `lang/en.json`, `lang/it.json`
- Test: `tests/Unit/Rules/AdditionalServicesMapTest.php`

**Interfacce:**
- Produce: `new AdditionalServicesMap()` (`ValidationRule`), usata in Task 5 su `additional_services`; `public static function isValidPrice(mixed $price): bool`, usata in Task 7.

Regole:
- valore `null` → nessun controllo (resta `nullable`);
- deve essere un array **associativo** (`! array_is_list($value)` quando non vuoto; `[]` è ammesso perché significa «rimuovi la traduzione»);
- ogni chiave è una stringa non vuota dopo `trim`;
- ogni prezzo: `is_int`/`is_float` (non NAN/INF) oppure stringa che soddisfa `^-?\d+([.,]\d{1,2})?$`.

Messaggi (uno per problema, max 10 + «…e altri N», uniti con `; `):
- lista → «additional_services deve essere un oggetto {"descrizione": prezzo}, non una lista (es. {"Setup iniziale": 1500})»
- descrizione vuota → «ogni servizio deve avere una descrizione non vuota»
- prezzo → «il prezzo del servizio "Setup" non è valido (ricevuto: "1.234,56"): usa un numero senza separatore delle migliaia, al massimo due decimali. Esempi validi: 1234.56, 1234,56, 1234»

- [ ] **Step 1: Aggiungere le chiavi di traduzione**

Prima: `grep -n 'must be an object\|non-empty description\|is not valid (received' lang/en.json lang/it.json` (nessun risultato atteso). `lang/en.json`:

```json
  "The :attribute field is not valid: :details.": "The :attribute field is not valid: :details.",
  ":attribute must be an object {\"description\": price}, not a list (e.g. {\"Initial setup\": 1500})": ":attribute must be an object {\"description\": price}, not a list (e.g. {\"Initial setup\": 1500})",
  "every service must have a non-empty description": "every service must have a non-empty description",
  "the price of service \":service\" is not valid (received: :value): use a number without thousands separator and at most two decimals. Valid examples: 1234.56, 1234,56, 1234": "the price of service \":service\" is not valid (received: :value): use a number without thousands separator and at most two decimals. Valid examples: 1234.56, 1234,56, 1234",
  "...and :count more problems": "...and :count more problems",
```

`lang/it.json`:

```json
  "The :attribute field is not valid: :details.": "Il campo :attribute non è valido: :details.",
  ":attribute must be an object {\"description\": price}, not a list (e.g. {\"Initial setup\": 1500})": ":attribute deve essere un oggetto {\"descrizione\": prezzo}, non una lista (es. {\"Setup iniziale\": 1500})",
  "every service must have a non-empty description": "ogni servizio deve avere una descrizione non vuota",
  "the price of service \":service\" is not valid (received: :value): use a number without thousands separator and at most two decimals. Valid examples: 1234.56, 1234,56, 1234": "il prezzo del servizio \":service\" non è valido (ricevuto: :value): usa un numero senza separatore delle migliaia e al massimo due decimali. Esempi validi: 1234.56, 1234,56, 1234",
  "...and :count more problems": "...e altri :count problemi",
```

Verificare il JSON come in Task 3 Step 1.

- [ ] **Step 2: Scrivere i test che falliscono**

```php
<?php

namespace Tests\Unit\Rules;

use App\Rules\AdditionalServicesMap;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AdditionalServicesMapTest extends TestCase
{
    private function errore(mixed $value): ?string
    {
        App::setLocale('it');
        $validator = Validator::make(['additional_services' => $value], ['additional_services' => ['nullable', 'array', new AdditionalServicesMap()]]);
        return $validator->errors()->first('additional_services') ?: null;
    }

    /** @test */
    public function prezzi_validi_passano(): void
    {
        $this->assertNull($this->errore(['Setup' => 1500, 'Hosting' => 99.5, 'Formazione' => '1234,56', 'Extra' => '-10.00', 'Base' => '1234']));
        $this->assertNull($this->errore([]));
        $this->assertNull($this->errore(null));
    }

    /** @test */
    public function separatore_delle_migliaia_e_rifiutato_con_esempi(): void
    {
        $messaggio = $this->errore(['Setup iniziale' => '1.234,56']);

        $this->assertStringContainsString('"Setup iniziale"', $messaggio);
        $this->assertStringContainsString('1.234,56', $messaggio);
        $this->assertStringContainsString('Esempi validi', $messaggio);
    }

    /** @test */
    public function testo_descrittivo_al_posto_del_prezzo_e_rifiutato(): void
    {
        $this->assertStringContainsString('"Consulenza"', $this->errore(['Consulenza' => 'da definire']));
    }

    /** @test */
    public function una_lista_e_rifiutata(): void
    {
        $this->assertStringContainsString('non una lista', $this->errore([150, 200]));
    }

    /** @test */
    public function is_valid_price_ricalca_template_e_totale(): void
    {
        foreach ([0, 10, 10.5, '10', '10.5', '10,50', '-3'] as $ok) {
            $this->assertTrue(AdditionalServicesMap::isValidPrice($ok), var_export($ok, true));
        }
        foreach (['1.234,56', '1,234.56', '10.555', 'abc', '', ' 10', true, null, [1]] as $ko) {
            $this->assertFalse(AdditionalServicesMap::isValidPrice($ko), var_export($ko, true));
        }
    }
}
```

- [ ] **Step 3: Eseguire i test e verificare che falliscano**

Run: `docker exec php81_orchestrator php artisan test --filter=AdditionalServicesMapTest`
Atteso: FAIL con `Class "App\Rules\AdditionalServicesMap" not found`.

- [ ] **Step 4: Implementare**

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * `additional_services` è una mappa {descrizione: prezzo}. Il prezzo deve
 * essere leggibile sia da `number_format(str_replace(',', '.', $p))` nel PDF
 * sia da `Quote::getTotalAdditionalServicesPrice()` (oc:8631): niente
 * separatore delle migliaia, al massimo un separatore decimale.
 */
class AdditionalServicesMap implements ValidationRule
{
    private const MAX_LISTED = 10;
    private const PRICE_PATTERN = '/^-?\d+([.,]\d{1,2})?$/';

    public static function isValidPrice(mixed $price): bool
    {
        if (is_int($price)) {
            return true;
        }
        if (is_float($price)) {
            return is_finite($price);
        }
        return is_string($price) && preg_match(self::PRICE_PATTERN, $price) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === []) {
            return;
        }

        if (array_is_list($value)) {
            $fail(__('The :attribute field is not valid: :details.', [
                'attribute' => $attribute,
                'details'   => __(':attribute must be an object {"description": price}, not a list (e.g. {"Initial setup": 1500})', ['attribute' => $attribute]),
            ]));
            return;
        }

        $problems = [];
        foreach ($value as $service => $price) {
            if (trim((string) $service) === '') {
                $problems[] = __('every service must have a non-empty description');
                continue;
            }
            if (! self::isValidPrice($price)) {
                $problems[] = __('the price of service ":service" is not valid (received: :value): use a number without thousands separator and at most two decimals. Valid examples: 1234.56, 1234,56, 1234', [
                    'service' => $service,
                    'value'   => json_encode($price, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        if ($problems === []) {
            return;
        }

        $problems = array_values(array_unique($problems));
        $rest = count($problems) - self::MAX_LISTED;
        $details = array_slice($problems, 0, self::MAX_LISTED);
        if ($rest > 0) {
            $details[] = __('...and :count more problems', ['count' => $rest]);
        }

        $fail(__('The :attribute field is not valid: :details.', [
            'attribute' => $attribute,
            'details'   => implode('; ', $details),
        ]));
    }
}
```

- [ ] **Step 5: Eseguire i test e verificare che passino**

Run: `docker exec php81_orchestrator php artisan test --filter=AdditionalServicesMapTest`
Atteso: PASS.

- [ ] **Step 6: Commit (istruzione per il dev)**

```bash
git add app/Rules/AdditionalServicesMap.php tests/Unit/Rules/AdditionalServicesMapTest.php lang/en.json lang/it.json
git commit -m "feat(oc:8631): validazione di forma e prezzi dei servizi aggiuntivi"
```

---

### Task 5: API — lettura e scrittura dei quattro campi

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#cleanup-della-review-wm-review-ticket)

**File:**
- Modifica: `app/Http/Requests/Api/QuoteApiRequest.php`
- Modifica: `app/Http/Controllers/Api/QuoteController.php` (`TRANSLATABLE_FIELDS` riga 22, `applyTranslatable()` ~riga 320, `formatQuote()` ~riga 327, 8 docblock `@response` alle righe 37, 101, 187, 208, 247, 263, 279, 295)
- Test: `tests/Feature/Api/QuoteRichTextApiTest.php` (nuovo, per non appesantire `QuoteApiTest.php`)

**Interfacce:**
- Consuma: `SafeRichTextHtml` (Task 3), `AdditionalServicesMap` (Task 4).
- Produce: `QuoteController::RICH_TEXT_FIELDS = ['additional_info', 'delivery_time', 'payment_plan', 'billing_plan']` (costante pubblica, usata in Task 7); chiavi di risposta `additional_info|delivery_time|payment_plan|billing_plan: string|null`.

- [ ] **Step 1: Scrivere i test feature che falliscono**

```php
<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteRichTextApiTest extends TestCase
{
    use DatabaseTransactions;

    private const CAMPI = ['additional_info', 'delivery_time', 'payment_plan', 'billing_plan'];

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => [UserRole::Admin]]));
    }

    private function quote(array $attrs = []): Quote
    {
        return Quote::factory()->create(array_merge(['additional_services' => [], 'discount' => 0], $attrs));
    }

    /** @test */
    public function show_espone_i_quattro_campi_in_italiano_o_null(): void
    {
        $quote = $this->quote();
        $quote->setTranslation('payment_plan', 'it', '<ul><li>20% alla firma</li></ul>');
        $quote->setTranslation('delivery_time', 'de', '<p>solo tedesco</p>');
        $quote->save();

        $json = $this->getJson("/api/quotes/{$quote->id}")->assertOk()->json();

        $this->assertSame('<ul><li>20% alla firma</li></ul>', $json['payment_plan']);
        $this->assertNull($json['delivery_time']);
        $this->assertNull($json['additional_info']);
        $this->assertNull($json['billing_plan']);
    }

    /** @test */
    public function store_salva_i_quattro_campi_esattamente_come_inviati(): void
    {
        $payload = ['title' => 'Nuovo', 'customer_id' => Customer::factory()->create()->id];
        foreach (self::CAMPI as $campo) {
            $payload[$campo] = "<p style=\"text-align: center;\"><strong>{$campo}</strong></p>";
        }

        $json = $this->postJson('/api/quotes', $payload)->assertStatus(201)->json();

        $quote = Quote::find($json['id']);
        foreach (self::CAMPI as $campo) {
            $this->assertSame($payload[$campo], $json[$campo]);
            $this->assertSame($payload[$campo], $quote->getTranslation($campo, 'it', false));
        }
    }

    /** @test */
    public function patch_parziale_non_tocca_gli_altri_campi(): void
    {
        $quote = $this->quote();
        $quote->setTranslation('delivery_time', 'it', '<p>30 giorni</p>');
        $quote->setTranslation('payment_plan', 'de', '<p>legacy de</p>');
        $quote->save();

        $this->patchJson("/api/quotes/{$quote->id}", ['billing_plan' => '<p>50% + 50%</p>'])->assertOk();

        $quote->refresh();
        $this->assertSame('<p>30 giorni</p>', $quote->getTranslation('delivery_time', 'it', false));
        $this->assertSame('<p>legacy de</p>', $quote->getTranslation('payment_plan', 'de', false));
        $this->assertSame('<p>50% + 50%</p>', $quote->getTranslation('billing_plan', 'it', false));
    }

    /** @test */
    public function null_e_stringa_vuota_rimuovono_la_traduzione_it(): void
    {
        $quote = $this->quote();
        $quote->setTranslation('payment_plan', 'it', '<p>x</p>');
        $quote->setTranslation('delivery_time', 'it', '<p>y</p>');
        $quote->save();

        $json = $this->patchJson("/api/quotes/{$quote->id}", ['payment_plan' => null, 'delivery_time' => ''])->assertOk()->json();

        $this->assertNull($json['payment_plan']);
        $this->assertNull($json['delivery_time']);
        $quote->refresh();
        $this->assertArrayNotHasKey('it', $quote->getTranslations('payment_plan'));
        $this->assertArrayNotHasKey('it', $quote->getTranslations('delivery_time'));
    }

    /** @test */
    public function html_pericoloso_da_422_con_messaggio_comprensibile_e_non_salva(): void
    {
        $quote = $this->quote();

        $response = $this->patchJson("/api/quotes/{$quote->id}", ['payment_plan' => '<p onclick="x()">a</p><script>alert(1)</script>'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_plan']);

        $messaggio = $response->json('errors.payment_plan.0');
        $this->assertStringContainsString('<script>', $messaggio);
        $this->assertStringContainsString('onclick', $messaggio);
        $this->assertArrayNotHasKey('it', $quote->fresh()->getTranslations('payment_plan'));
    }

    /** @test */
    public function oltre_50000_caratteri_da_422(): void
    {
        $quote = $this->quote();

        $this->patchJson("/api/quotes/{$quote->id}", ['additional_info' => '<p>' . str_repeat('a', 50001) . '</p>'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['additional_info']);
    }

    /** @test */
    public function round_trip_di_contenuto_nova_reale_passa(): void
    {
        $html = '<p dir="ltr" style="text-align: justify;">Testo</p><div><span class="x">a</span></div>'
            . '<table><tbody><tr><td colspan="1" rowspan="1" colwidth="200"><p>c</p></td></tr></tbody></table>'
            . '<p><a href="https://webmapp.it" target="_blank" tt-mode="url">link</a></p>'
            . '<img src="/storage/tiptap/logo.png" tt-mode="file" alt="logo" title="Logo">';
        $quote = $this->quote();
        $quote->setTranslation('additional_info', 'it', $html);
        $quote->save();

        $letto = $this->getJson("/api/quotes/{$quote->id}")->json('additional_info');
        $this->patchJson("/api/quotes/{$quote->id}", ['additional_info' => $letto])->assertOk();

        $this->assertSame($html, $quote->fresh()->getTranslation('additional_info', 'it', false));
    }

    /** @test */
    public function prezzo_con_migliaia_in_additional_services_da_422(): void
    {
        $quote = $this->quote();

        $messaggio = $this->patchJson("/api/quotes/{$quote->id}", ['additional_services' => ['Setup' => '1.234,56']])
            ->assertStatus(422)
            ->json('errors.additional_services.0');

        $this->assertStringContainsString('"Setup"', $messaggio);
    }
}
```

- [ ] **Step 2: Eseguire e verificare che falliscano**

Run: `docker exec php81_orchestrator php artisan test --filter=QuoteRichTextApiTest`
Atteso: FAIL (chiavi assenti nella risposta, campi ignorati, nessun 422).

- [ ] **Step 3: Regole in `QuoteApiRequest`**

Aggiungere gli `use` e modificare `rules()`:

```php
use App\Rules\AdditionalServicesMap;
use App\Rules\SafeRichTextHtml;
```

```php
            'additional_services'  => ['sometimes', 'nullable', 'array', new AdditionalServicesMap()],
            // ...righe esistenti invariate...
            'template'             => ['sometimes', 'boolean'],
            'additional_info'      => ['sometimes', 'nullable', 'string', 'max:50000', new SafeRichTextHtml()],
            'delivery_time'        => ['sometimes', 'nullable', 'string', 'max:50000', new SafeRichTextHtml()],
            'payment_plan'         => ['sometimes', 'nullable', 'string', 'max:50000', new SafeRichTextHtml()],
            'billing_plan'         => ['sometimes', 'nullable', 'string', 'max:50000', new SafeRichTextHtml()],
```

- [ ] **Step 4: Controller — scrittura**

```php
    public const RICH_TEXT_FIELDS = ['additional_info', 'delivery_time', 'payment_plan', 'billing_plan'];
    private const TRANSLATABLE_FIELDS = ['additional_services', 'notes', ...self::RICH_TEXT_FIELDS];
```

In `applyTranslatable()`:

```php
    private function applyTranslatable(Quote $quote, array $translatable): void
    {
        foreach ($translatable as $field => $value) {
            // oc:8631: per i rich-text un valore vuoto rimuove la traduzione,
            // così il PDF (`@if ($quote->campo)`) nasconde la sezione con certezza.
            if (in_array($field, self::RICH_TEXT_FIELDS, true) && ($value === null || $value === '')) {
                $quote->forgetTranslation($field, config('app.locale'));
                continue;
            }
            $quote->setTranslation($field, config('app.locale'), $value);
        }
    }
```

- [ ] **Step 5: Controller — lettura in `formatQuote()`**

Dopo `'additional_services' => …`:

```php
            'additional_info'      => $this->richText($quote, 'additional_info'),
            'delivery_time'        => $this->richText($quote, 'delivery_time'),
            'payment_plan'         => $this->richText($quote, 'payment_plan'),
            'billing_plan'         => $this->richText($quote, 'billing_plan'),
```

e il metodo privato:

```php
    private function richText(Quote $quote, string $field): ?string
    {
        $value = $quote->getTranslation($field, config('app.locale'), false);

        return $value === '' || $value === null ? null : $value;
    }
```

- [ ] **Step 6: Docblock `@response` (8 metodi)**

In ognuna delle 8 righe `@response` con la forma completa (`index`, `show`, `store`, `update`, `attachProduct`, `detachProduct`, `attachRecurringProduct`, `detachRecurringProduct`) inserire dopo `notes: string|null,`:

```
additional_info: string|null, delivery_time: string|null, payment_plan: string|null, billing_plan: string|null,
```

Verifica: `grep -c 'billing_plan: string|null' app/Http/Controllers/Api/QuoteController.php` → il numero di occorrenze della forma completa (in `index` la forma compare anche dentro la variante paginata: contare e controllare che ogni occorrenza di `notes: string|null, additional_services` sia stata aggiornata con `grep -c 'notes: string|null, additional_services'` → `0`).

- [ ] **Step 7: Eseguire i test**

Run: `docker exec php81_orchestrator php artisan test --filter="QuoteRichTextApiTest|QuoteApiTest"`
Atteso: PASS. Se falliscono test esistenti di `QuoteApiTest` per via della factory che genera prezzi `randomFloat` (validi) non dovrebbe succedere; se succede, leggere l'errore prima di toccare qualsiasi cosa.

- [ ] **Step 8: Commit (istruzione per il dev)**

```bash
git add app/Http/Requests/Api/QuoteApiRequest.php app/Http/Controllers/Api/QuoteController.php tests/Feature/Api/QuoteRichTextApiTest.php
git commit -m "feat(oc:8631): lettura e scrittura via API dei campi rich-text del preventivo"
```

---

### Task 6: Documentazione OpenAPI — i quattro campi in tutti gli 8 endpoint

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-6--documentazione-openapi)

**File:**
- Modifica: `tests/Feature/Api/QuoteApiDocsTest.php`
- Eventuale modifica: `app/Http/Controllers/Api/QuoteController.php` (attributi `#[BodyParameter]` se Scramble non documenta i campi nel body)

**Interfacce:**
- Consuma: docblock aggiornati in Task 5.

- [ ] **Step 1: Aggiungere i test**

```php
    private function allFullQuoteSchemas(): array
    {
        $spec = $this->get('/docs/api.json')->json();
        $ops = [
            ['/quotes', 'get', '200'], ['/quotes/{quote}', 'get', '200'], ['/quotes', 'post', '201'],
            ['/quotes/{quote}', 'patch', '200'],
            ['/quotes/{quote}/products/{product}', 'post', '200'], ['/quotes/{quote}/products/{product}', 'delete', '200'],
            ['/quotes/{quote}/recurring-products/{recurringProduct}', 'post', '200'],
            ['/quotes/{quote}/recurring-products/{recurringProduct}', 'delete', '200'],
        ];

        $schemas = [];
        foreach ($ops as [$path, $method, $status]) {
            $schema = $spec['paths'][$path][$method]['responses'][$status]['content']['application/json']['schema'] ?? null;
            $this->assertNotNull($schema, "Expected a {$status} response schema for {$method} {$path}.");
            $schemas["{$method} {$path}"] = $schema;
        }
        return $schemas;
    }

    private function quoteProperties(array $schema): array
    {
        if (isset($schema['anyOf'])) {
            $schema = $schema['anyOf'][0];
        }
        if (($schema['type'] ?? null) === 'array') {
            $schema = $schema['items'];
        }
        return $schema['properties'] ?? [];
    }

    public function test_all_full_quote_responses_document_the_rich_text_fields(): void
    {
        foreach ($this->allFullQuoteSchemas() as $operation => $schema) {
            $properties = $this->quoteProperties($schema);
            foreach (['additional_info', 'delivery_time', 'payment_plan', 'billing_plan'] as $field) {
                $this->assertArrayHasKey($field, $properties, "Expected {$field} in the response schema of {$operation}.");
            }
        }
    }

    public function test_quotes_store_and_update_document_the_rich_text_fields_in_the_body(): void
    {
        $spec = $this->get('/docs/api.json')->json();
        foreach ([['/quotes', 'post'], ['/quotes/{quote}', 'patch']] as [$path, $method]) {
            $body = $spec['paths'][$path][$method]['requestBody']['content']['application/json']['schema'] ?? [];
            $properties = collect($body['allOf'] ?? [$body])->pluck('properties')->filter()->collapse()->all();
            foreach (['additional_info', 'delivery_time', 'payment_plan', 'billing_plan'] as $field) {
                $this->assertArrayHasKey($field, $properties, "Expected {$field} in the request body of {$method} {$path}.");
            }
        }
    }
```

Se il codice di stato di `store` nello schema non è `201` o i percorsi differiscono, correggere l'array `$ops` leggendo `/docs/api.json`, non il test di asserzione.

- [ ] **Step 2: Eseguire**

Run: `docker exec php81_orchestrator php artisan test --filter=QuoteApiDocsTest`
Atteso: PASS. Se il body non documenta i campi, aggiungere su `store()` e `update()`:

```php
    #[BodyParameter('payment_plan', description: 'HTML (lingua di default). null o "" rimuovono il testo. Rifiutati script, iframe, attributi on*, javascript:/data:, url( nello style, immagini fuori dal dominio dell\'app.', type: 'string', required: false)]
```

(una riga per ciascuno dei quattro campi), poi rieseguire.

- [ ] **Step 3: Commit (istruzione per il dev)**

```bash
git add tests/Feature/Api/QuoteApiDocsTest.php app/Http/Controllers/Api/QuoteController.php
git commit -m "test(oc:8631): la documentazione OpenAPI espone i campi rich-text su tutti gli endpoint"
```

---

### Task 7: Comando `quotes:check-rich-text`

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#cleanup-della-review-wm-review-ticket)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-7--comando-quotescheck-rich-text)

**File:**
- Crea: `app/Console/Commands/CheckQuoteRichText.php`
- Test: `tests/Feature/CheckQuoteRichTextCommandTest.php`

**Interfacce:**
- Consuma: `RichTextHtmlInspector::inspect()` (Task 2), `AdditionalServicesMap::isValidPrice()` (Task 4), `QuoteController::RICH_TEXT_FIELDS` (Task 5).
- Produce: `php artisan quotes:check-rich-text` — sola lettura; exit code `0` se nessun preventivo verrebbe rifiutato, `1` altrimenti.

- [ ] **Step 1: Scrivere il test che fallisce**

```php
<?php

namespace Tests\Feature;

use App\Models\Quote;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CheckQuoteRichTextCommandTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function riporta_i_preventivi_che_verrebbero_rifiutati_senza_modificarli(): void
    {
        $ok = Quote::factory()->create(['additional_services' => []]);
        $ok->setTranslation('payment_plan', 'it', '<p>ok</p>')->save();

        $ko = Quote::factory()->create(['additional_services' => []]);
        $ko->setTranslation('delivery_time', 'de', '<p onclick="x()">a</p>');
        $ko->setTranslation('additional_services', 'it', ['Setup' => 'da definire']);
        $ko->save();
        $prima = $ko->fresh()->getAttributes();

        $this->artisan('quotes:check-rich-text')
            ->expectsOutputToContain((string) $ko->id)
            ->expectsOutputToContain('delivery_time [de]')
            ->expectsOutputToContain('onclick')
            ->expectsOutputToContain('additional_services [it]')
            ->assertExitCode(1);

        $this->assertEquals($prima, $ko->fresh()->getAttributes());
    }
}
```

(Il DB di test può contenere altri preventivi validi: il test controlla solo che `$ko` sia riportato.)

- [ ] **Step 2: Eseguire e verificare che fallisca**

Run: `docker exec php81_orchestrator php artisan test --filter=CheckQuoteRichTextCommandTest`
Atteso: FAIL (`The command "quotes:check-rich-text" does not exist`).

- [ ] **Step 3: Implementare**

```php
<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\QuoteController;
use App\Models\Quote;
use App\Rules\AdditionalServicesMap;
use App\Services\Quotes\RichTextHtmlInspector;
use Illuminate\Console\Command;

class CheckQuoteRichText extends Command
{
    protected $signature = 'quotes:check-rich-text';

    protected $description = 'Sola lettura: elenca i preventivi i cui campi rich-text o servizi aggiuntivi
                              verrebbero rifiutati dalla validazione API (oc:8631). Da lanciare in produzione
                              prima del rilascio: exit code 1 se trova qualcosa.';

    public function handle(RichTextHtmlInspector $inspector): int
    {
        $rows = [];

        Quote::query()->orderBy('id')->each(function (Quote $quote) use ($inspector, &$rows) {
            foreach (QuoteController::RICH_TEXT_FIELDS as $field) {
                foreach ($quote->getTranslations($field) as $locale => $html) {
                    if (! is_string($html) || $html === '') {
                        continue;
                    }
                    $problems = [];
                    if (mb_strlen($html) > 50000) {
                        $problems[] = 'lunghezza ' . mb_strlen($html);
                    }
                    foreach ($inspector->inspect($html) as $v) {
                        $problems[] = "{$v['kind']} {$v['name']} ×{$v['count']}";
                    }
                    if ($problems !== []) {
                        $rows[] = [$quote->id, "{$field} [{$locale}]", implode(', ', $problems)];
                    }
                }
            }

            foreach ($quote->getTranslations('additional_services') as $locale => $services) {
                if (! is_array($services) || $services === []) {
                    continue;
                }
                if (array_is_list($services)) {
                    $rows[] = [$quote->id, "additional_services [{$locale}]", 'lista invece di oggetto'];
                    continue;
                }
                $bad = collect($services)->reject(fn ($price) => AdditionalServicesMap::isValidPrice($price))
                    ->map(fn ($price, $service) => $service . ' = ' . json_encode($price, JSON_UNESCAPED_UNICODE));
                if ($bad->isNotEmpty()) {
                    $rows[] = [$quote->id, "additional_services [{$locale}]", $bad->implode(', ')];
                }
            }
        });

        if ($rows === []) {
            $this->info('Nessun preventivo verrebbe rifiutato.');
            return self::SUCCESS;
        }

        $this->table(['Preventivo', 'Campo [lingua]', 'Motivo'], $rows);
        $this->warn(count($rows) . ' campi verrebbero rifiutati: allargare la regola prima del rilascio o correggere i dati.');

        return self::FAILURE;
    }
}
```

- [ ] **Step 4: Eseguire i test**

Run: `docker exec php81_orchestrator php artisan test --filter=CheckQuoteRichTextCommandTest`
Atteso: PASS.

- [ ] **Step 5: Eseguire il comando sul DB locale**

Run: `docker exec php81_orchestrator php artisan quotes:check-rich-text`
Annotare l'esito in `notes.md`. Se riporta campi rich-text rifiutati su contenuto innocuo, **fermarsi e riportarlo al dev** prima di allargare la regola.

- [ ] **Step 6: Commit (istruzione per il dev)**

```bash
git add app/Console/Commands/CheckQuoteRichText.php tests/Feature/CheckQuoteRichTextCommandTest.php
git commit -m "feat(oc:8631): comando di verifica dei contenuti esistenti contro la nuova validazione"
```

---

### Task 8: PDF — Piano di fatturazione

**File:**
- Modifica: `resources/views/quote-pdf.blade.php` (dopo il blocco `payment_plan`, righe ~342-347)
- Modifica: `lang/en.json`, `lang/it.json`
- Test: `tests/Feature/QuotePdfBillingPlanTest.php`

- [ ] **Step 1: Chiave di traduzione**

Prima: `grep -n '"Billing plan"' lang/en.json lang/it.json` (nessun risultato atteso; esiste solo `"Billing Plan"` maiuscolo in `it.json`, da non toccare). Aggiungere:
- `lang/en.json`: `"Billing plan": "Billing plan",`
- `lang/it.json`: `"Billing plan": "Piano di fatturazione",`

Verificare il JSON come in Task 3 Step 1.

- [ ] **Step 2: Scrivere il test che fallisce**

```php
<?php

namespace Tests\Feature;

use App\Models\Quote;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class QuotePdfBillingPlanTest extends TestCase
{
    use DatabaseTransactions;

    private function html(Quote $quote): string
    {
        App::setLocale('it');
        return view('quote-pdf', ['quote' => $quote, 'config' => config('quote-pdf')])->render();
    }

    /** @test */
    public function il_piano_di_fatturazione_compare_dopo_il_piano_di_pagamento(): void
    {
        $quote = Quote::factory()->create(['additional_services' => [], 'discount' => 0]);
        $quote->setTranslation('payment_plan', 'it', '<p>PIANO-PAGAMENTO</p>');
        $quote->setTranslation('billing_plan', 'it', '<ul><li>FATTURA-UNO</li></ul>');
        $quote->save();

        $html = $this->html($quote->fresh());

        $this->assertStringContainsString('Piano di fatturazione', $html);
        $this->assertStringContainsString('<ul><li>FATTURA-UNO</li></ul>', $html);
        $this->assertGreaterThan(strpos($html, 'PIANO-PAGAMENTO'), strpos($html, 'FATTURA-UNO'));
    }

    /** @test */
    public function senza_piano_di_fatturazione_la_sezione_non_compare(): void
    {
        $quote = Quote::factory()->create(['additional_services' => [], 'discount' => 0]);

        $this->assertStringNotContainsString('Piano di fatturazione', $this->html($quote));
    }
}
```

- [ ] **Step 3: Eseguire e verificare che fallisca**

Run: `docker exec php81_orchestrator php artisan test --filter=QuotePdfBillingPlanTest`
Atteso: il primo test FAIL (sezione assente), il secondo PASS.

- [ ] **Step 4: Aggiungere il blocco al template**

Subito dopo il blocco esistente:

```blade
    @if ($quote->payment_plan)
    <div class="payment-plan">
        <h2 class="description">{{ __('Payment plan') }}</h2>
        <p>{!! $quote->getTranslation('payment_plan', App::getLocale()) !!}</p>
    </div>
    @endif
```

inserire:

```blade
    @if ($quote->billing_plan)
    <div class="payment-plan">
        <h2 class="description">{{ __('Billing plan') }}</h2>
        <p>{!! $quote->getTranslation('billing_plan', App::getLocale()) !!}</p>
    </div>
    @endif
```

(stessa classe `payment-plan` per avere la stessa grafica senza nuovo CSS.)

- [ ] **Step 5: Eseguire i test del PDF**

Run: `docker exec php81_orchestrator php artisan test --filter="QuotePdfBillingPlanTest|QuotePdfServiceTest"`
Atteso: PASS.

- [ ] **Step 6: Verifica visiva**

Generare il PDF di un preventivo locale con Piano di pagamento e Piano di fatturazione compilati (via Nova o `GET /api/quotes/{id}/pdf`) e controllare che i due blocchi siano uno dopo l'altro con la stessa grafica, e che elenchi e grassetti siano resi.

- [ ] **Step 7: Commit (istruzione per il dev)**

```bash
git add resources/views/quote-pdf.blade.php lang/en.json lang/it.json tests/Feature/QuotePdfBillingPlanTest.php
git commit -m "feat(oc:8631): il PDF del preventivo stampa il Piano di fatturazione"
```

---

### Task 9: Suite completa e chiusura

- [ ] **Step 1: Eseguire tutta la suite**

Run: `docker exec php81_orchestrator php artisan test`
Atteso: nessuna nuova failure rispetto a `develop`. Se ci sono failure preesistenti, annotarle in `notes.md` con il nome del test, senza correggerle.

- [ ] **Step 2: Promemoria operativi in `notes.md`**

- Prima del merge: il dev lancia `php artisan quotes:check-rich-text` **in produzione** e riporta l'esito.
- Stima non ancora scritta su Orchestrator (10,9h): riprenderla a fine lavoro.
- Aggiornamento di `docs/knowledge/quote-api-e-pdf.md` e dell'indice del `CLAUDE.md`: gestiti nella fase `update-context` di `wm-plan`, non in questo piano.
