<?php

namespace App\Services\Quotes;

use DOMElement;
use Masterminds\HTML5;

/**
 * Analizza l'HTML dei campi rich-text del preventivo e riporta solo ciò che
 * può eseguire codice o caricare risorse esterne (oc:8631). Usa lo stesso
 * parser di DomPDF, così validatore e rendering leggono l'HTML allo stesso modo.
 *
 * I tag sconosciuti ma innocui (`<o:p>` di Word, `<section>`, `<center>`)
 * passano: da Nova, con `editHtml`, si può salvare HTML libero, e rifiutarli
 * farebbe fallire il PATCH di un campo letto e rimandato senza modifiche.
 *
 * Gli URL vengono normalizzati come li legge il browser prima del controllo:
 * tab/newline e caratteri di controllo tolti, `\` letto come `/`. Senza
 * normalizzazione `java\tscript:` o `/\host` passerebbero il controllo.
 */
class RichTextHtmlInspector
{
    /** Oltre questa profondità l'HTML è rifiutato: Tiptap non va oltre poche decine di livelli. */
    public const MAX_DEPTH = 100;

    /** Le immagini di Tiptap vivono sul disco public, servito sotto /storage/. */
    public const RESOURCE_PATH_PREFIX = '/storage/';

    /** I soli tag rifiutati: eseguono codice, incorporano contenuti o sono controlli di form. */
    private const DANGEROUS_TAGS = [
        'script', 'noscript', 'template', 'slot', 'iframe', 'frame', 'frameset', 'portal',
        'fencedframe', 'object', 'embed', 'applet', 'param', 'style', 'link', 'meta', 'base',
        'title', 'form', 'input', 'button', 'textarea', 'select', 'option', 'keygen', 'isindex',
        'dialog', 'svg', 'math', 'video', 'audio', 'source', 'track', 'picture', 'bgsound',
        'html', 'head', 'body',
    ];

    /**
     * Nello style: `url(` e `image-set(` scaricano risorse, `expression(` e
     * `javascript:` eseguono codice, `@import` carica un foglio esterno.
     * Backslash (escape CSS come `u\72l(`) e commenti servono solo a nascondere
     * quei token: DomPDF toglie i commenti prima di leggere lo style, e un
     * commento dentro una stringa CSS inganna qualsiasi rimozione ingenua.
     */
    private const DANGEROUS_STYLE_TOKENS = ['url(', 'image-set(', 'expression(', '@import', 'javascript:', '\\', '/*'];

    /** Attributi che contengono l'URL di una risorsa caricata automaticamente. */
    private const RESOURCE_URL_ATTRIBUTES = ['src', 'background', 'poster', 'lowsrc', 'dynsrc'];

    /** Attributi con URL rifiutati sempre: non servono a Tiptap e sono difficili da validare. */
    private const FORBIDDEN_URL_ATTRIBUTES = ['srcset', 'action', 'formaction', 'data'];

    /**
     * Attributi di presentazione che DomPDF traduce in CSS con
     * sprintf('text-align: %s;', $valore), senza escape (Css/AttributeTranslator.php):
     * un `;` nel valore aggiunge proprietà CSS arbitrarie, comprese url(…) che
     * DomPDF scarica. Si ammettono solo valori semplici.
     */
    private const PRESENTATION_ATTRIBUTES = [
        'align', 'valign', 'width', 'height', 'bgcolor', 'color', 'text', 'link', 'border',
        'bordercolor', 'cellpadding', 'cellspacing', 'clear', 'face', 'frame', 'rules', 'size',
        'start', 'type', 'value', 'hspace', 'vspace', 'nowrap', 'noshade', 'compact', 'dir',
    ];

    /** Lettere, cifre, spazi e # % . , ' " _ -: bastano per center, 100%, #ff0000, "Times New Roman". */
    private const PRESENTATION_VALUE_PATTERN = '/^[\p{L}\p{N}\s#%.,\'"_-]*$/u';

    private const LINK_ATTRIBUTES = ['href', 'xlink:href'];

    private const SAFE_HREF_SCHEMES = ['http', 'https', 'mailto'];

    private const SAFE_RESOURCE_SCHEMES = ['http', 'https'];

    private string $appHost;

    private ?int $appPort;

    public function __construct(?string $appHost = null, ?int $appPort = null)
    {
        if ($appHost === null) {
            $appUrl = (string) config('app.url');
            $appHost = (string) parse_url($appUrl, PHP_URL_HOST);
            $appPort = parse_url($appUrl, PHP_URL_PORT) ?: null;
        }
        $this->appHost = strtolower($appHost);
        $this->appPort = $appPort;
    }

    public static function isDangerousTag(string $tag): bool
    {
        return in_array(strtolower($tag), self::DANGEROUS_TAGS, true);
    }

    /** Host (e porta, se presente) su cui sono ammesse le immagini: serve ai messaggi. */
    public function allowedOrigin(): string
    {
        return $this->appHost . ($this->appPort !== null ? ':' . $this->appPort : '');
    }

    /**
     * @return array<int, array{kind: string, name: string, count: int}>
     */
    public function inspect(string $html): array
    {
        $violations = [];
        $fragment = (new HTML5(['disable_html_ns' => true]))->loadHTMLFragment($html);

        // Visita iterativa con profondità: un HTML annidato migliaia di livelli
        // non deve costare secondi di CPU né rischiare lo stack.
        // I figli vanno sullo stack in ordine inverso, così le violazioni restano
        // nell'ordine in cui compaiono nel testo.
        $stack = [];
        foreach (array_reverse(iterator_to_array($fragment->childNodes)) as $node) {
            $stack[] = [$node, 1];
        }
        while ($stack !== []) {
            [$node, $depth] = array_pop($stack);
            if (! $node instanceof DOMElement) {
                continue;
            }
            if ($depth > self::MAX_DEPTH) {
                $this->add($violations, 'depth', (string) self::MAX_DEPTH);
                break;
            }
            $this->checkElement($node, $violations);
            foreach (array_reverse(iterator_to_array($node->childNodes)) as $child) {
                $stack[] = [$child, $depth + 1];
            }
        }

        return array_values($violations);
    }

    private function checkElement(DOMElement $element, array &$violations): void
    {
        $tag = strtolower($element->tagName);
        if (self::isDangerousTag($tag)) {
            $this->add($violations, 'tag', $tag);
        }

        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->name);
            $value = (string) $attribute->value;

            if (str_starts_with($name, 'on')) {
                $this->add($violations, 'attribute', $name);
            } elseif ($name === 'style') {
                $this->checkStyle($value, $violations);
            } elseif (in_array($name, self::LINK_ATTRIBUTES, true)) {
                if (! $this->isSafeHref($value)) {
                    $this->add($violations, 'url', 'href');
                }
            } elseif (in_array($name, self::RESOURCE_URL_ATTRIBUTES, true)) {
                if (! $this->isSafeResourceUrl($value)) {
                    $this->add($violations, 'url', $name);
                }
            } elseif (in_array($name, self::FORBIDDEN_URL_ATTRIBUTES, true)) {
                $this->add($violations, 'url', $name);
            } elseif (in_array($name, self::PRESENTATION_ATTRIBUTES, true)
                && preg_match(self::PRESENTATION_VALUE_PATTERN, $value) !== 1) {
                $this->add($violations, 'presentation', $name);
            }
        }
    }

    private function checkStyle(string $style, array &$violations): void
    {
        $normalized = strtolower(preg_replace('/\s+/', '', $style));

        foreach (self::DANGEROUS_STYLE_TOKENS as $token) {
            if (str_contains($normalized, $token)) {
                $this->add($violations, 'style', $token);
            }
        }
    }

    /**
     * Normalizza un URL come fa il browser (WHATWG URL): toglie tab e a capo
     * ovunque, i caratteri di controllo e gli spazi ai bordi, e legge `\` come `/`.
     */
    private function normalizeUrl(string $url): string
    {
        $url = preg_replace('/[\t\n\r]/', '', $url);
        $url = trim($url, "\x00..\x20");

        return str_replace('\\', '/', $url);
    }

    private function hasScheme(string $url): bool
    {
        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1;
    }

    /** Link: http(s), mailto, ancora `#` o percorso relativo (non `//host`). */
    private function isSafeHref(string $href): bool
    {
        $href = $this->normalizeUrl($href);
        if ($href === '' || str_starts_with($href, '#')) {
            return true;
        }
        if (! $this->hasScheme($href)) {
            return ! str_starts_with($href, '//');
        }

        return in_array(strtolower((string) parse_url($href, PHP_URL_SCHEME)), self::SAFE_HREF_SCHEMES, true);
    }

    /**
     * Una risorsa caricata automaticamente (DomPDF ha enable_remote, Nova la
     * mostra nel browser) è ammessa solo sotto /storage/ dell'applicazione:
     * percorso relativo, oppure URL assoluto su host e porta di APP_URL.
     */
    private function isSafeResourceUrl(string $src): bool
    {
        $src = $this->normalizeUrl($src);
        if (str_starts_with($src, '//')) {
            return false;
        }

        if (! $this->hasScheme($src)) {
            return $this->isStoragePath($src);
        }

        $parts = parse_url($src);
        if ($parts === false) {
            return false;
        }

        return in_array(strtolower($parts['scheme'] ?? ''), self::SAFE_RESOURCE_SCHEMES, true)
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && strtolower($parts['host'] ?? '') === $this->appHost
            && ($parts['port'] ?? null) === $this->appPort
            && $this->isStoragePath($parts['path'] ?? '');
    }

    private function isStoragePath(string $path): bool
    {
        $path = (string) strtok($path, '?#');

        return str_starts_with($path, self::RESOURCE_PATH_PREFIX)
            && ! preg_match('~(^|/)\.\.?(/|$)~', rawurldecode($path));
    }

    private function add(array &$violations, string $kind, string $name): void
    {
        $key = $kind . ':' . $name;
        $violations[$key] ??= ['kind' => $kind, 'name' => $name, 'count' => 0];
        $violations[$key]['count']++;
    }
}
