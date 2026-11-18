<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\BlockLibrary\Actions\BuildBuilderBlockRenderDataAction;
use Capell\Core\Contracts\Security\SanitizesPublicHtml;
use Capell\Core\Enums\HtmlSanitizerProfile;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Sanitises editor-authored HTML before it is rendered as raw markup on the
 * anonymous frontend. Section summaries and nested meta values are emitted via
 * Blade `{!! !!}` in the catalog views, so any unsanitised `<script>` or event
 * handler authored by an editor would execute for every visitor. This action
 * strips dangerous markup while preserving safe rich-text elements. Icon meta
 * is also normalised here because block-library catalog views pass those keys
 * to the Blade icon resolver.
 */
class SanitizeSectionHtmlAction
{
    use AsFake;
    use AsObject;

    /**
     * Recursively sanitise a string or array of editor-authored values.
     *
     * Non-string scalars (ints, bools, null) and object values are returned
     * untouched; only strings are passed through the HTML sanitiser. Array keys
     * named `icon` are treated as icon identifiers and must match the package's
     * Heroicon picker format.
     */
    public function handle(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->sanitizer()->sanitize($value, HtmlSanitizerProfile::RichText);
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            if (is_string($key) && $key === 'icon') {
                $value[$key] = NormalizeSectionIconAction::run($item);

                continue;
            }

            if (is_string($key) && $this->isUrlKey($key)) {
                $value[$key] = $this->sanitizeUrl($item);

                continue;
            }

            $value[$key] = $this->handle($item);
        }

        return $value;
    }

    private function isUrlKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));

        return $normalized === 'href'
            || $normalized === 'redirect'
            || str_ends_with($normalized, 'url');
    }

    private function sanitizeUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        // Sections render the block catalogue's views, so they share its link policy: web, mail and phone
        // links only, no protocol-relative or backslash hosts, and no admin or editor paths.
        $url = BuildBuilderBlockRenderDataAction::make()->url($value);

        return $url === '' ? null : $url;
    }

    private function sanitizer(): SanitizesPublicHtml
    {
        return resolve(SanitizesPublicHtml::class);
    }
}
