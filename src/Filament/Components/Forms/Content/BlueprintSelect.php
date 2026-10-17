<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Components\Forms\Content;

use Capell\Admin\Filament\Components\Forms\BlueprintSelect as BaseBlueprintSelect;
use Capell\ContentSections\Enums\LayoutTypeEnum;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Override;

class BlueprintSelect extends BaseBlueprintSelect
{
    #[Override]
    protected function setUp(?string $label = null): void
    {
        parent::setUp($label);

        $this->subject(LayoutTypeEnum::Section->value);
    }

    #[Override]
    public function withRelation(): static
    {
        return $this->relationship(
            name: 'blueprint',
            titleAttribute: 'name',
            modifyQueryUsing: fn (Builder $query): Builder => $query->select('blueprints.*')
                ->when(
                    $this->getBlueprint(),
                    fn (Builder $query, string $type): Builder => $query->where('type', $type),
                )
                ->when(
                    $this->modifySelectOptionsQueryUsing instanceof Closure,
                    fn (Builder $query): mixed => $this->evaluate($this->modifySelectOptionsQueryUsing, [
                        'query' => $query,
                        'record' => $this->getRecord(),
                    ]),
                )
                ->enabled()
                ->ordered(),
        )
            ->preload()
            ->savesBelongsToRelation();
    }
}
