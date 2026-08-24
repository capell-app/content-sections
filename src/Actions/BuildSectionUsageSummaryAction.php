<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\Admin\Support\AdminSurfaceLookup;
use Capell\Admin\Support\MediaScope;
use Capell\ContentSections\Data\SectionUsageDestinationData;
use Capell\ContentSections\Data\SectionUsageSummaryData;
use Capell\ContentSections\Models\Section;
use Capell\ContentSections\Support\SectionUsageScope;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\AssetAttachment;
use Capell\Core\Models\Layout;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Throwable;

/**
 * Projects where a Section is currently used, over the existing generic
 * AssetAttachment ("related" side) and Layout Builder WidgetAsset ("pageable"
 * placement) relations. Read-only: it adds no new placement store and issues no
 * queries from the public render path. Every count and destination is scoped to
 * what the current actor is authorised to see, matching the admin table/edit-page
 * access rules the rest of the package already enforces.
 *
 * @method static SectionUsageSummaryData run(Section $section)
 */
final class BuildSectionUsageSummaryAction
{
    use AsFake;
    use AsObject;

    private const int DESTINATION_LIMIT = 12;

    public function handle(Section $section): SectionUsageSummaryData
    {
        $actor = auth()->user();

        if (! $actor instanceof Authenticatable) {
            return SectionUsageSummaryData::blank();
        }

        $isAuthoritative = SectionUsageScope::isGlobalActor();

        $attachmentQuery = MediaScope::applyAssetAttachmentsForCurrentActor(
            $section->assetRelations()->getQuery(),
        );
        $attachmentCount = (clone $attachmentQuery)->count();

        $widgetQuery = SectionUsageScope::applyPlacedForCurrentActor($this->widgetAssetQuery($section));
        $widgetCount = (clone $widgetQuery)->count();

        $destinations = array_values(
            $this->attachmentDestinations($attachmentQuery)
                ->concat($this->widgetDestinations($widgetQuery))
                ->take(self::DESTINATION_LIMIT)
                ->all(),
        );

        return new SectionUsageSummaryData(
            attachmentCount: $attachmentCount,
            widgetCount: $widgetCount,
            isAuthoritative: $isAuthoritative,
            destinations: $destinations,
        );
    }

    /** @return Builder<WidgetAsset> */
    private function widgetAssetQuery(Section $section): Builder
    {
        $key = $section->getKey();

        return WidgetAsset::query()
            ->where('asset_type', $section->getMorphClass())
            ->where('asset_id', is_int($key) || is_string($key) ? (string) $key : '');
    }

    /**
     * @param  Builder<AssetAttachment>  $query
     * @return Collection<int, SectionUsageDestinationData>
     */
    private function attachmentDestinations(Builder $query): Collection
    {
        /** @var Collection<int, SectionUsageDestinationData> $destinations */
        $destinations = (clone $query)
            ->with(['related' => function (Relation $relation): void {
                $this->eagerLoadSiteOnMorph($relation);
            }])
            ->orderByDesc('order')
            ->limit(self::DESTINATION_LIMIT)
            ->get()
            ->map(function (AssetAttachment $attachment): ?SectionUsageDestinationData {
                /**
                 * The relation's declared type is non-nullable, but a
                 * morphed-to record that no longer exists (hard-deleted, or
                 * excluded by its own global scope such as a soft-delete
                 * scope) resolves to null at runtime — proven by this
                 * projection's own "deleted/missing owner" test coverage.
                 *
                 * @var Model|null $related
                 */
                $related = $attachment->related;

                return $related instanceof Model ? $this->destination(kind: 'attachment', model: $related) : null;
            })
            ->filter()
            ->values();

        return $destinations;
    }

    /**
     * @param  Builder<WidgetAsset>  $query
     * @return Collection<int, SectionUsageDestinationData>
     */
    private function widgetDestinations(Builder $query): Collection
    {
        /** @var Collection<int, SectionUsageDestinationData> $destinations */
        $destinations = (clone $query)
            ->with(['pageable' => function (Relation $relation): void {
                $this->eagerLoadSiteOnMorph($relation);
            }])
            ->orderByDesc('updated_at')
            ->limit(self::DESTINATION_LIMIT)
            ->get()
            ->map(function (WidgetAsset $widgetAsset): ?SectionUsageDestinationData {
                $pageable = $widgetAsset->pageable;

                if (! $pageable instanceof Model) {
                    return null;
                }

                return $this->destination(
                    kind: 'widget',
                    model: $pageable,
                    context: is_string($widgetAsset->container) ? $widgetAsset->container : null,
                );
            })
            ->filter()
            ->values();

        return $destinations;
    }

    private function destination(string $kind, Model $model, ?string $context = null): SectionUsageDestinationData
    {
        return new SectionUsageDestinationData(
            kind: $kind,
            type: Str::headline(class_basename($model)),
            title: $this->modelTitle($model),
            url: $this->resourceUrl($model),
            site: $this->modelSite($model),
            context: $context,
        );
    }

    private function modelTitle(Model $model): string
    {
        $name = $model->getAttribute('name');

        if (is_string($name) && $name !== '') {
            return $name;
        }

        $key = $model->getKey();

        return sprintf(
            '%s #%s',
            Str::headline(class_basename($model)),
            is_int($key) || is_string($key) ? $key : '?',
        );
    }

    /**
     * Eager-loads `site` for every morph type that plausibly has it, so
     * {@see modelSite()} never has to lazy load — this test/production
     * environment runs with `Model::preventLazyLoading()`, and a per-row lazy
     * load would also silently reintroduce the N+1 this projection avoids.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     */
    private function eagerLoadSiteOnMorph(Relation $relation): void
    {
        if ($relation instanceof MorphTo) {
            $relation->morphWith($this->siteAwareMorphMap());
        }
    }

    /** @return array<class-string<Model>, list<string>> */
    private function siteAwareMorphMap(): array
    {
        $map = [];

        foreach ([...CapellCore::getPageVariationModels(), Layout::class, Section::class] as $modelClass) {
            $map[$modelClass] = ['site'];
        }

        return $map;
    }

    private function modelSite(Model $model): ?string
    {
        if (! $model instanceof Pageable && ! $model instanceof Layout) {
            return null;
        }

        if (! $model->relationLoaded('site')) {
            return null;
        }

        $site = $model->getRelation('site');

        if (! $site instanceof Model) {
            return null;
        }

        $name = $site->getAttribute('name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    private function resourceUrl(Model $model): ?string
    {
        $modelType = class_basename($model);
        $resource = AdminSurfaceLookup::resourceIfRegistered($modelType);

        if ($resource === null && $model instanceof Pageable) {
            $resource = AdminSurfaceLookup::resourceIfRegistered('Page', Str::lower($modelType));
        }

        if ($resource === null || ! $this->canEditResourceRecord($resource, $model)) {
            return null;
        }

        try {
            return $resource::getUrl('edit', ['record' => $model->getKey()]);
        } catch (RouteNotFoundException) {
            return null;
        }
    }

    /**
     * @param  class-string  $resource
     */
    private function canEditResourceRecord(string $resource, Model $model): bool
    {
        try {
            return $resource::hasPage('edit') && $resource::canEdit($model);
        } catch (Throwable) {
            return false;
        }
    }
}
