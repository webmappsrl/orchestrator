<?php

namespace App\Rules;

use App\Services\Quotes\QuoteRichText;
use App\Services\Quotes\RichTextHtmlInspector;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rifiuta solo l'HTML che può eseguire codice o caricare risorse esterne
 * (oc:8631). Il messaggio dice cosa togliere, raggruppato per tipo.
 */
class SafeRichTextHtml implements ValidationRule
{
    private RichTextHtmlInspector $inspector;

    public function __construct(?RichTextHtmlInspector $inspector = null)
    {
        $this->inspector = $inspector ?? new RichTextHtmlInspector();
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $violations = $this->inspector->inspect($value);
        if ($violations === []) {
            return;
        }

        $details = QuoteRichText::limitDetails(
            array_map(fn (array $v) => $this->describe($v), $violations),
            '...and :count more kinds of elements not allowed'
        );

        $fail(__('The :attribute field contains HTML that is not allowed: :details.', [
            'attribute' => $attribute,
            'details'   => implode('; ', $details),
        ]));
    }

    private function describe(array $violation): string
    {
        $replace = [
            // Nomi di tag e attributi vengono dall'HTML dell'utente (es. `o:p`, `xlink:href`).
            'name'        => QuoteRichText::protectPlaceholders($violation['name']),
            'count'       => $violation['count'],
            'occurrences' => __($violation['count'] === 1 ? 'occurrence' : 'occurrences'),
            'host'        => $this->inspector->allowedOrigin(),
        ];

        return match ($violation['kind']) {
            'tag'          => __('tag <:name> not allowed (:count :occurrences): remove it, it can run code or embed external content', $replace),
            'attribute'    => __('attribute :name not allowed (:count :occurrences): remove attributes starting with "on", they run code', $replace),
            'presentation' => __('attribute :name with a value that is not allowed (:count :occurrences): use a plain value such as center, 100%, 200 or #ff0000, without ; ( ) : / or \\', $replace),
            'style'        => __('style with :name not allowed (:count :occurrences): in style do not use url(, image-set(, expression(, @import, javascript:, backslashes or comments', $replace),
            'depth'        => __('HTML nested more than :name levels deep: simplify the structure', $replace),
            default        => $this->describeUrl($violation['name'], $replace),
        };
    }

    private function describeUrl(string $attribute, array $replace): string
    {
        return match ($attribute) {
            'href'                                  => __('link not allowed (:count :occurrences): href must start with https://, http://, mailto:, # or be a relative path', $replace),
            'src', 'background', 'poster', 'lowsrc', 'dynsrc'
                                                    => __('attribute :name not allowed (:count :occurrences): images and resources must be a path under /storage/ (e.g. /storage/tiptap/logo.png) or a URL on :host under /storage/', $replace),
            default                                 => __('attribute :name not allowed (:count :occurrences): remove it, URLs are accepted only in href and in the src of images', $replace),
        };
    }
}
