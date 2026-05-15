<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use BackedEnum;
use Capell\ContentSections\Data\SectionDefinitionData;
use Capell\ContentSections\Enums\LayoutTypeEnum;
use Capell\ContentSections\Support\SectionRegistry;
use Capell\Core\Models\Blueprint;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Blueprint run(string $key)
 */
class EnsureSectionBlueprintForKeyAction
{
    use AsObject;

    public function handle(string $key): Blueprint
    {
        $registry = resolve(SectionRegistry::class);

        if ($registry->all() === []) {
            RegisterDefaultSectionsAction::run($registry);
        }

        $definition = $registry->get($key);

        if (! $definition instanceof SectionDefinitionData) {
            throw new InvalidArgumentException(sprintf('section [%s] is not registered.', $key));
        }

        $configuratorKey = $definition->configurator::getKey();

        /** @var Blueprint|null $blueprint */
        $blueprint = Blueprint::query()
            ->where('type', LayoutTypeEnum::Section->value)
            ->where('key', $definition->key)
            ->first();

        if ($blueprint instanceof Blueprint) {
            return $blueprint;
        }

        /** @var Blueprint $blueprint */
        $blueprint = Blueprint::query()->create([
            'name' => $definition->label,
            'key' => $definition->key,
            'type' => LayoutTypeEnum::Section->value,
            'group' => $definition->group,
            'default' => $definition->key === 'content',
            'status' => true,
            'admin' => [
                'configurator' => $configuratorKey,
                'icon' => $definition->icon instanceof BackedEnum ? $definition->icon->value : $definition->icon,
            ],
        ]);

        return $blueprint;
    }
}
