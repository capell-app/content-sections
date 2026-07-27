<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Components\Forms;

use Aimeos\Nestedset\NestedSet;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Admin\Filament\Concerns\HasCustomSelectOption;
use Capell\ContentSections\Models\Section;
use Capell\ContentSections\Support\SectionSiteScope;
use Capell\Core\Data\Database\SqlFragment;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Facades\CapellDatabase;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use RuntimeException;

class ContentSelect extends Select
{
    use HasCustomSelectOption;

    private null|string|Closure $contentType = null;

    private ?Closure $modifySelectOptionsQueryUsing = null;

    private ?string $parentContentType = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('capell-admin::form.content'))
            ->searchable()
            ->preload()
            ->optionsLimit(100)
            ->allowHtml()
            ->rules([
                fn (self $component, Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component, $get): void {
                    $sectionIds = collect(Arr::wrap($value))
                        ->filter(fn (mixed $sectionId): bool => filled($sectionId))
                        ->filter(fn (mixed $sectionId): bool => is_numeric($sectionId))
                        ->map(fn (mixed $sectionId): int => (int) $sectionId)
                        ->unique()
                        ->values();

                    if ($sectionIds->isEmpty()) {
                        return;
                    }

                    $siteId = self::normalizeSiteId($get('site_id'));
                    $accessibleCount = $component->getContentQuery($siteId)
                        ->whereKey($sectionIds->all())
                        ->count();

                    if ($accessibleCount !== $sectionIds->count()) {
                        $fail(__('capell-content-sections::message.content_not_accessible'));
                    }
                },
            ])
            ->getSearchResultsUsing(function (self $component, Get $get, string $search): array {
                $siteId = self::normalizeSiteId($get('site_id'));

                return $component->getContentOptions(
                    siteId: $siteId,
                    search: $search,
                );
            })
            ->getOptionLabelUsing(fn (self $component, ?int $value): string => (string) Section::query()
                ->with(['blueprint'])
                ->tap(fn (Builder $query): Builder => SectionSiteScope::applyForCurrentActor($query, 'sections.site_id'))
                ->find($value, ['id', 'name', 'blueprint_id'])
                ?->name)
            ->options(fn (self $component): array => $component->getContentOptions());
    }

    public function contentType(string|Closure $contentType): self
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function getContentType(): ?string
    {
        return $this->evaluate($this->contentType);
    }

    public function modifySelectOptionsQueryUsing(?Closure $callback): static
    {
        $this->modifySelectOptionsQueryUsing = $callback;

        return $this;
    }

    public function parentContentType(string $contentType): self
    {
        $this->parentContentType = $contentType;

        return $this;
    }

    public function withCreateForm(): Select
    {
        $asset = CapellCore::getAsset('Section');

        $adminAsset = CapellAdmin::getAsset('Section');

        $createOptionUsing = $this->getCreateOptionUsing();

        return $this->createOptionAction(
            fn (Action $action): Action => $this->modifyCreateAction($action)
                ->fillForm(fn (): array => in_array($adminAsset->defaultDataAction, [null, '', '0'], true) ? [] : $adminAsset->defaultDataAction::run()),
        )
            ->createOptionForm(
                fn (Schema $configurator): Schema => $adminAsset->formClass::configure(
                    $configurator->operation('createOption')->model(Section::class),
                ),
            )
            ->createOptionUsing(function (Select $component, array $data) use ($asset, $adminAsset, $createOptionUsing): int|string {
                Gate::authorize('create', Section::class);
                self::assertCanUseSubmittedSite($data);
                self::assertCanUseSubmittedParent($data);

                $record = in_array($adminAsset->createAction, [null, '', '0'], true)
                    ? $component->evaluate($createOptionUsing)
                    : $adminAsset->createAction::run($data);

                throw_unless($record instanceof Section, RuntimeException::class, 'Content select create action must return a section.');

                Notification::make()
                    ->title(__('capell-admin::message.asset_created_successfully', ['name' => $asset->name]))
                    ->body($record->name)
                    ->send();

                return $record->getKey();
            })
            ->getOptionLabelFromRecordUsing(fn (Section $record): string => static::getSelectOption($record));
    }

    public function withEditForm(): self
    {
        $asset = CapellAdmin::getAsset('Section');

        return $this->editOptionForm(function (?int $state, Schema $configurator) use ($asset): Schema {
            if ($state === null) {
                return $configurator;
            }

            return $asset->formClass::configure($configurator->operation('editOption'));
        })
            ->editOptionAction(
                fn (Action $action): Action => $action
                    ->modalHeading(
                        fn (self $component): string => __(
                            'capell-content-sections::heading.edit_content_record',
                            ['name' => (string) $component->getSelectedRecord()?->getAttribute('name')],
                        ),
                    )
                    ->modalWidth(Width::ScreenExtraLarge)
                    ->visible(fn (mixed $state): bool => (bool) $state)
                    ->successNotificationTitle(
                        fn (Action $action): string => __(
                            'capell-admin::notification.updated_successfully',
                            ['name' => $this->htmlableText($action->getModalHeading())],
                        ),
                    )
                    ->after(function (Action $action): void {
                        $action->success();
                    }),
            )
            ->fillEditOptionActionFormUsing(static function (self $component): array {
                /** @var Section $record */
                $record = $component->getSelectedRecord();

                return $record->attributesToArray() ?? [];
            })
            ->getSelectedRecordUsing(static fn (?int $state): ?Section => $state === null ? null : Section::query()
                ->with(['blueprint'])
                ->tap(fn (Builder $query): Builder => SectionSiteScope::applyForCurrentActor($query, 'sections.site_id'))
                ->findOrFail($state))
            ->updateOptionUsing(static function (array $data, Schema $configurator): void {
                $record = $configurator->getRecord();

                throw_unless($record instanceof Section, RuntimeException::class, 'Content select edit action must have a section record.');

                self::assertCanUseRecord($record);
                Gate::authorize('update', $record);
                self::assertCanUseSubmittedSite($data);
                self::assertCanUseSubmittedParent($data);

                $record->update($data);
            });
    }

    private static function assertCanUseSubmittedSite(array $data): void
    {
        if (! array_key_exists('site_id', $data)) {
            return;
        }

        $siteId = self::normalizeSiteId($data['site_id']);

        throw_unless(
            SectionSiteScope::actorCanMutateSiteId(auth()->user(), $siteId),
            AuthorizationException::class,
        );
    }

    private static function assertCanUseRecord(Section $section): void
    {
        throw_unless(
            SectionSiteScope::actorCanUseSection(auth()->user(), $section),
            AuthorizationException::class,
        );
    }

    private static function assertCanUseSubmittedParent(array $data): void
    {
        if (! array_key_exists('parent_id', $data) || blank($data['parent_id'])) {
            return;
        }

        $parent = Section::query()->find((int) $data['parent_id']);

        throw_unless(
            $parent instanceof Section && SectionSiteScope::actorCanUseSection(auth()->user(), $parent),
            AuthorizationException::class,
        );
    }

    private static function normalizeSiteId(mixed $siteId): ?int
    {
        if (blank($siteId)) {
            return null;
        }

        return (int) $siteId;
    }

    private function htmlableText(Htmlable|string|null $value): string
    {
        return $value instanceof Htmlable ? $value->toHtml() : (string) $value;
    }

    private function getContentOptionLabel(Section $record, ?int $siteId): HtmlString
    {
        $label = '';

        if (($siteId === null || $siteId === 0) && $record->site !== null) {
            $label .= $record->site->name . ' &raquo; ';
        }

        $ancestors = $record->relationLoaded('ancestors')
            ? $record->getRelation('ancestors')
            : $record->ancestors()->get();

        $visibleAncestors = $ancestors
            ->filter(fn (Section $ancestor): bool => SectionSiteScope::actorCanUseSection(auth()->user(), $ancestor));

        if ($visibleAncestors->isNotEmpty()) {
            $label .= $visibleAncestors->pluck('name')
                ->map(fn (string $name): string => Str::limit($name, 30))
                ->implode(' &raquo; ')
                . ' &raquo; ';
        }

        return new HtmlString($label . Str::limit($record->name, 40));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getContentOptions(?int $siteId = null, ?string $search = null): array
    {
        $contents = $this->getContentQuery($siteId, $search)
            ->orderBy('site_id')
            ->orderBy(NestedSet::LFT, 'ASC')
            ->get();

        return $contents->mapWithKeys(
            fn (Section $content): array => [$content->getKey() => $this->getContentOptionLabel($content, $siteId)],
        )
            ->toArray();
    }

    private function getContentQuery(?int $siteId = null, ?string $search = null): Builder
    {
        $relations = [
            'ancestors.blueprint',
            'blueprint',
        ];

        if ($siteId === null || $siteId === 0) {
            $relations[] = 'site';
        }

        $contentType = $this->getContentType();

        $parentContentType = $this->parentContentType;

        /** @var class-string<Section> $model */
        $model = Section::class;

        return $model::query()->select('sections.*')
            ->with($relations)
            ->join('blueprints', 'sections.blueprint_id', '=', 'blueprints.id')
            ->tap(fn (Builder $query): Builder => SectionSiteScope::applyForCurrentActor($query, 'sections.site_id'))
            ->when(
                $this->modifySelectOptionsQueryUsing instanceof Closure,
                fn (Builder $query): mixed => $this->evaluate($this->modifySelectOptionsQueryUsing, [
                    'query' => $query,
                    'record' => $this->getRecord(),
                ]),
            )
            ->when(
                $contentType,
                fn (Builder $query): Builder => $query->whereHas('blueprint', fn (BuilderContract $query): BuilderContract => $query->where('key', $contentType)),
            )
            ->when(
                $siteId !== null && $siteId > 0,
                fn (Builder $query): Builder => $query->where(
                    fn (Builder $query): Builder => $query
                        ->whereNull('sections.site_id')
                        ->orWhere('sections.site_id', $siteId),
                ),
            )
            ->when(
                $siteId === 0,
                fn (Builder $query): Builder => $query->whereNull('sections.site_id'),
            )
            ->when(
                $parentContentType,
                fn (Builder $query): Builder => $query->whereHas(
                    'parent.blueprint',
                    fn (BuilderContract $query): BuilderContract => $query->where('key', $parentContentType),
                ),
            )
            ->when(
                $search,
                function (Builder $query, string $search): Builder {
                    $grammar = $query->getQuery()->getGrammar();
                    $name = SqlFragment::raw($grammar->wrap('sections.name'));
                    $relevance = CapellDatabase::for($query->getModel())
                        ->queryDialect()
                        ->textRelevance($name, $search);

                    return $query
                        ->where('sections.name', 'like', sprintf('%%%s%%', $search))
                        ->orderByRaw($relevance->sql, $relevance->bindings)
                        ->orderBy('sections.name');
                },
                fn (Builder $query): Builder => $query->limit(10),
            );
    }

    private function modifyCreateAction(Action $action): Action
    {
        return $action->slideOver()
            ->modalWidth(Width::ScreenLarge)
            ->closeModalByClickingAway(false)
            ->successNotificationTitle(
                fn (Action $action): string => __(
                    'capell-admin::notification.created_successfully',
                    ['name' => $this->htmlableText($action->getModalHeading())],
                ),
            )
            ->after(function (Action $action): void {
                $action->success();
            });
    }
}
