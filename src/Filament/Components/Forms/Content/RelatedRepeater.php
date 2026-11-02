<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Components\Forms\Content;

use Capell\ContentSections\Actions\MaterialisePendingRelatedCopiesAction;
use Capell\ContentSections\Filament\Components\Forms\ContentSelect;
use Capell\ContentSections\Models\Section;
use Capell\ContentSections\Support\SectionSiteScope;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RelatedRepeater
{
    /**
     * Marks an item as a copy still to be made: it holds the source section id. The item keeps showing
     * the source until the parent is saved, when the copy is made and the item points at it instead.
     */
    public const string PENDING_COPY_KEY = 'copy_of';

    public static function make(Schema $configurator): Repeater
    {
        return Repeater::make('related')
            ->label(__('capell-content-sections::form.related_contents'))
            ->statePath('related')
            ->hiddenLabel()
            ->cloneable()
            ->cloneAction(
                fn (Action $action): Action => $action
                    ->visible(
                        fn (array $state, array $arguments): bool => self::itemContentId($state, $arguments['item'] ?? null) !== null,
                    )
                    ->action(function (Repeater $component, array $arguments): void {
                        $newUuid = $component->generateUuid();

                        $items = self::rawItems($component);

                        $sourceId = self::itemContentId($items, $arguments['item'] ?? null);

                        $existingContent = $sourceId === null ? null : Section::query()
                            ->tap(fn (Builder $query): Builder => SectionSiteScope::applyForCurrentActor($query, 'sections.site_id'))
                            ->find($sourceId);

                        throw_unless($existingContent instanceof Section, Exception::class, 'Content not found with ID: ' . $sourceId);

                        Gate::authorize('replicate', $existingContent);

                        // Nothing is copied yet: the copy is made when the parent is saved.
                        $newData = ['content_id' => $sourceId, self::PENDING_COPY_KEY => $sourceId];

                        if (! in_array($newUuid, [null, '', '0'], true)) {
                            $items[$newUuid] = $newData;
                        } else {
                            $items[] = $newData;
                        }

                        $component->state($items);

                        $component->collapsed(false, shouldMakeComponentCollapsible: false);

                        $component->callAfterStateUpdated();
                    }),
            )
            ->mutateDehydratedStateUsing(static fn (Repeater $component, ?array $state): array => $component->dehydrateItems(
                self::materialisePendingCopies($component, $state ?? []),
            ))
            ->simple(
                ContentSelect::make('content_id')
                    ->hiddenLabel()
                    ->required()
                    ->preload(fn (string $operation): bool => in_array($operation, ['create', 'createOption'], true))
                    ->when(
                        $configurator->isCreating(),
                        fn (ContentSelect $component): Select => $component->withCreateForm(),
                        fn (ContentSelect $component): Select => $component->withEditForm(),
                    ),
            );
    }

    /**
     * @param  array<array-key, mixed>  $state
     * @return array<string, array<string, mixed>>
     */
    private static function materialisePendingCopies(Repeater $component, array $state): array
    {
        $rawState = self::rawItems($component);

        /** @var array<string, array<string, mixed>> $items */
        $items = [];
        $pending = [];

        foreach ($state as $key => $item) {
            $key = (string) $key;
            $item = is_array($item) ? $item : [];
            $items[$key] = $item;

            $copyOf = $rawState[$key][self::PENDING_COPY_KEY] ?? null;

            // An item whose selection was changed after copying no longer asks for a copy.
            if (is_numeric($copyOf) && self::itemContentId($items, $key) === (int) $copyOf) {
                $pending[$key] = (int) $copyOf;
            }
        }

        $copies = MaterialisePendingRelatedCopiesAction::run($pending, (string) $component->getStatePath());

        if ($copies === []) {
            return $items;
        }

        $materialisedState = $rawState;

        foreach ($copies as $key => $copyId) {
            $items[(string) $key]['content_id'] = $copyId;
            $materialisedState[(string) $key] = ['content_id' => $copyId];
        }

        // A later save by the same editor reuses the copy; if this save rolls back, the items ask again.
        $component->rawState($materialisedState);

        if (DB::transactionLevel() > 0) {
            DB::afterRollBack(static function () use ($component, $rawState): void {
                $component->rawState($rawState);
            });
        }

        return $items;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function rawItems(Repeater $component): array
    {
        $rawState = $component->getRawState();

        if (! is_array($rawState)) {
            return [];
        }

        $items = [];

        foreach ($rawState as $key => $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $items[(string) $key] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<array-key, mixed>  $items
     */
    private static function itemContentId(array $items, mixed $key): ?int
    {
        if (! is_string($key) && ! is_int($key)) {
            return null;
        }

        $item = $items[$key] ?? null;
        $contentId = is_array($item) ? ($item['content_id'] ?? null) : null;

        return is_numeric($contentId) && (int) $contentId > 0 ? (int) $contentId : null;
    }
}
