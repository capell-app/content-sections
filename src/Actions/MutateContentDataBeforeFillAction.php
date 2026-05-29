<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\Core\Contracts\Actionable;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static array<array-key, mixed> run(array<array-key, mixed> $data = [])
 */
class MutateContentDataBeforeFillAction implements Actionable
{
    use AsObject;

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function handle(array $data = []): array
    {
        $site = Site::getDefault();

        $data['blueprint_id'] = ResolveRequestedSectionBlueprintAction::run($data)?->getKey()
            ?? ResolveRequestedSectionBlueprintAction::make()->defaultBlueprint()->getKey();

        $data['translations'] = $site?->translations->mapWithKeys(fn (Translation $translation): array => [
            (string) Str::uuid() => [
                'language_id' => $translation->language_id,
            ],
        ])
            ->all();

        return $data;
    }
}
