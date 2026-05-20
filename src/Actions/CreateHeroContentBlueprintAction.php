<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Enums\LayoutTypeEnum;
use Capell\ContentSections\Filament\Configurators\Sections\HeroSectionConfigurator;
use Capell\Core\Models\Blueprint;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Blueprint run()
 */
class CreateHeroContentBlueprintAction
{
    use AsFake;
    use AsObject;

    public function handle(): Blueprint
    {
        /** @var class-string<Blueprint> */
        $blueprint = Blueprint::class;

        return $blueprint::query()->firstOrCreate([
            'key' => 'hero',
            'type' => LayoutTypeEnum::Section,
        ], [
            'name' => __('capell-content-sections::generic.hero'),
            'admin' => [
                'configurator' => HeroSectionConfigurator::getKey(),
                'notes' => __('capell-content-sections::type.hero_section_description'),
            ],
        ]);
    }
}
