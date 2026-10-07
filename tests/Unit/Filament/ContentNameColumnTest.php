<?php

declare(strict_types=1);

use Capell\ContentSections\Filament\Components\Tables\Columns\Content\ContentNameColumn;
use Capell\ContentSections\Models\Section;
use Illuminate\Contracts\Support\Htmlable;

it('renders the children count with a native Filament badge', function (): void {
    $record = new Section;
    $record->setAttribute('children_count', 3);

    $column = ContentNameColumn::make('name')->record($record);
    $suffix = $column->getSuffix();

    expect($suffix)->toBeInstanceOf(Htmlable::class)
        ->and($suffix instanceof Htmlable ? $suffix->toHtml() : $suffix)
        ->toContain('fi-badge', __('capell-admin::generic.total_children', ['total' => 3]));
});

it('omits the children badge when the section has no children', function (): void {
    $record = new Section;
    $record->setAttribute('children_count', 0);

    expect(ContentNameColumn::make('name')->record($record)->getSuffix())->toBeNull();
});

it('loads the child count when it was not preloaded', function (): void {
    $parent = Section::factory()->createOne();
    Section::factory()->createOne(['parent_id' => $parent->getKey()]);
    $suffix = ContentNameColumn::make('name')->record($parent)->getSuffix();

    expect($parent->children_count)->toBe(1)
        ->and($suffix instanceof Htmlable ? $suffix->toHtml() : $suffix)
        ->toContain(__('capell-admin::generic.total_children', ['total' => 1]));
});
