<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Enums\LayoutTypeEnum;
use Capell\Core\Models\Blueprint;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Blueprint|null run(array $state = [])
 */
class ResolveRequestedSectionBlueprintAction
{
    use AsObject;

    public function handle(array $state = []): ?Blueprint
    {
        $blueprintId = $state['blueprint_id'] ?? null;

        if ($blueprintId !== null && $blueprintId !== '') {
            /** @var Blueprint|null $blueprint */
            $blueprint = Blueprint::query()->find($blueprintId);

            return $blueprint;
        }

        $sectionKey = request()->query('section');

        if (! is_string($sectionKey) || $sectionKey === '') {
            return null;
        }

        return EnsureSectionBlueprintForKeyAction::run($sectionKey);
    }

    public function defaultBlueprint(): Blueprint
    {
        EnsureSectionBlueprintForKeyAction::run('content');

        /** @var Blueprint $blueprint */
        $blueprint = Blueprint::query()
            ->where('type', LayoutTypeEnum::Section->value)
            ->orderBy('default', 'desc')
            ->orderBy('id')
            ->firstOrFail();

        return $blueprint;
    }
}
