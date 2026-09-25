<?php

namespace App\Services\Quotes;

use DOMElement;
use DOMNode;
use Masterminds\HTML5;

/**
 * Analizza l'HTML dei campi rich-text del preventivo e riporta solo ciò che
 * può eseguire codice o caricare risorse esterne (oc:8631). Usa lo stesso
 * parser di DomPDF, così validatore e rendering leggono l'HTML allo stesso modo.
 *
 * URL e style vengono normalizzati come li leggono browser e DomPDF prima del
 * controllo: tab/newline e caratteri di controllo tolti, `\` letto come `/`,
 * commenti CSS rimossi. Senza normalizzazione `java\tscript:` o
 * `url/**\/(…)` passerebbero il controllo e verrebbero eseguiti o scaricati.
 */
class RichTextHtmlInspector
{
    public const ALLOWED_TAGS = [
        'p', 'br', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'mark', 'code',
        'pre', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'hr', 'a',
        'img', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'colgroup', 'col',
        'caption', 'font', 'sup', 'sub', 'small',
    ];

    /** Tag rifiutati perché eseguono codice o incorporano contenuti esterni. */
    public const DANGEROUS_TAGS = [
        'script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'style', 'link',
        'meta', 'base', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math',
        'noscript', 'template', 'video', 'audio', 'source', 'track', 'picture', 'html', 'head',
        'body',
    ];

    public const DANGEROUS_STYLE_TOKENS = ['url(', 'image-set(', 'expression(', '@import', 'javascript:', '\\'];

    /** Attributi che contengono l'URL di una risorsa caricata automaticamente. */
    private const RESOURCE_URL_ATTRIBUTES = ['src', 'background', 'poster', 'lowsrc', 'dynsrc'];

    /** Attributi con URL rifiutati sempre: non servono a Tiptap e sono difficili da validare. */
    private const FORBIDDEN_URL_ATTRIBUTES = ['srcset', 'action', 'formaction', 'data'];

    private const LINK_ATTRIBUTES = ['href', 'xlink:href'];

    private const SAFE_HREF_SCHEMES = ['http', 'https', 'mailto'];

    /** Le immagini di Tiptap vivono sul disco public, servito sotto /storage/. */
    private const RESOURCE_PATH_PREFIX = '/storage/';

    private string $appHost;

    private ?int $appPort;

    /** @var array<string, array{kind: string, name: string, count: int}> */
    private array $violations = [];

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
            } elseif (in_array($name, self::LINK_ATTRIBUTES, true) && ! $this->isSafeHref($value)) {
                $this->add('url', 'href');
            } elseif (in_array($name, self::RESOURCE_URL_ATTRIBUTES, true) && ! $this->isSafeResourceUrl($value)) {
                $this->add('url', 'src');
            } elseif (in_array($name, self::FORBIDDEN_URL_ATTRIBUTES, true)) {
                $this->add('url', 'src');
            }
        }
    }

    private function checkStyle(string $style): void
    {
        // DomPDF toglie i commenti prima di leggere lo style (Css/Stylesheet.php):
        // vanno tolti anche qui, altrimenti `url/**/(` sfugge al controllo.
        $withoutComments = preg_replace('~/\*.*?\*/~s', '', $style);
        $normalized = strtolower(preg_replace('/\s+/', '', $withoutComments));

        foreach (self::DANGEROUS_STYLE_TOKENS as $token) {
            if (str_contains($normalized, $token)) {
                $this->add('style', $token);
            }
        }
        // Un commento non chiuso nasconde il resto dello style a DomPDF ma non al browser.
        if (str_contains($withoutComments, '/*')) {
            $this->add('style', '/*');
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

        return in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
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

    private function add(string $kind, string $name): void
    {
        $key = $kind . ':' . $name;
        $this->violations[$key] ??= ['kind' => $kind, 'name' => $name, 'count' => 0];
        $this->violations[$key]['count']++;
    }
}
