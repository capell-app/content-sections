<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Language;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The only way section meta reaches a public renderer. Every public path,
 * inline layout widgets and shared fragments alike, sanitises editor HTML,
 * filters URL schemes and normalises icons before media slots are hydrated,
 * so no renderer can emit raw authored meta.
 */
final class PreparePublicSectionMetaAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function handle(Section $section, array $meta, Language $language): array
    {
        /** @var array<string, mixed> $sanitised */
        $sanitised = SanitizeSectionHtmlAction::run($meta);

        return HydrateSectionMediaSlotsAction::run($section, $sanitised, $language);
    }
}
