<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Lorisleiva\Actions\Concerns\AsObject;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitises editor-authored HTML before it is rendered as raw markup on the
 * anonymous frontend. Section summaries and nested meta values are emitted via
 * Blade `{!! !!}` in the catalog views, so any unsanitised `<script>` or event
 * handler authored by an editor would execute for every visitor. This action
 * strips dangerous markup while preserving safe rich-text elements.
 */
class SanitizeSectionHtmlAction
{
    use AsObject;

    private static ?HtmlSanitizer $sanitizer = null;

    /**
     * Recursively sanitise a string or array of editor-authored values.
     *
     * Non-string scalars (ints, bools, null) and object values are returned
     * untouched; only strings are passed through the HTML sanitiser.
     */
    public function handle(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->sanitizer()->sanitize($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->handle($item);
        }

        return $value;
    }

    private function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer instanceof HtmlSanitizer) {
            return self::$sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowAttribute('class', '*')
            ->withMaxInputLength(-1);

        return self::$sanitizer = new HtmlSanitizer($config);
    }
}
