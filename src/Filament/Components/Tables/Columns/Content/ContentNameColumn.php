<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Components\Tables\Columns\Content;

use Capell\ContentSections\Models\Section;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\HtmlString;
use Override;

class ContentNameColumn extends TextColumn
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->searchable()
            ->sortable()
            ->wrap()
            ->weight(FontWeight::Medium)
            ->description(function (Section $record): ?HtmlString {
                $ancestors = $record->relationLoaded('ancestors')
                    ? $record->getRelation('ancestors')
                    : $record->ancestors()->get();

                if ($ancestors->isEmpty()) {
                    return null;
                }

                return new HtmlString($ancestors->pluck('name')->join(' &raquo; '));
            })
            ->suffix(function (Section $record): ?HtmlString {
                $count = $this->getChildCount($record);

                if ($count === 0) {
                    return null;
                }

                return new HtmlString(view('capell-content-sections::tables.columns.children-badge', [
                    'count' => $count,
                ])->render());
            });
    }

    private function getChildCount(Section $record): int
    {
        if ($record->getAttributeValue('children_count') === null) {
            $record->loadCount('children');
        }

        return (int) $record->children_count;
    }
}
