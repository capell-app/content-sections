<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\Core\Contracts\Actionable;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static array<array-key, mixed> run(array<array-key, mixed> $data = [])
 */
class BuildSectionCreateFormDataAction implements Actionable
{
    use AsFake;
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

        $translations = $site instanceof Site
            ? $site->translations()->get()
            : collect();

        $data['translations'] = $translations->mapWithKeys(fn (Translation $translation): array => [
            (string) Str::uuid() => [
                'language_id' => $translation->language_id,
            ],
        ])->all();

        return $data;
    }
}
