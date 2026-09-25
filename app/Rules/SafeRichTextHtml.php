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
            $violation['kind'] === 'tag' && RichTextHtmlInspector::isDangerousTag($violation['name'])
                                               => __('tag <:name> not allowed (:count :occurrences): remove it, it can run code or embed external content', $replace),
            $violation['kind'] === 'tag'       => __('tag <:name> not supported (:count :occurrences): remove it or replace it with a supported tag (p, span, strong, em, ul, ol, li, table)', $replace),
            $violation['kind'] === 'attribute' => __('attribute :name not allowed (:count :occurrences): remove attributes starting with "on", they run code', $replace),
            $violation['kind'] === 'style'     => __('style with :name not allowed (:count :occurrences): in style do not use url(, image-set(, expression(, @import, javascript:, backslashes or comments', $replace),
            $violation['name'] === 'href'      => __('link not allowed (:count :occurrences): href must start with https://, http://, mailto: or #', $replace),
            default                            => __('image not allowed (:count :occurrences): src must be a path under /storage/ (e.g. /storage/tiptap/logo.png) or a URL on :host under /storage/; srcset is not supported', $replace),
        };
    }
}
