<?php

declare(strict_types=1);

namespace Capell\ContentSections\Support;

use Capell\Admin\Support\MediaScope;
use Capell\Admin\Support\SiteScope;
use Capell\ContentSections\Actions\BuildSectionUsageSummaryAction;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Layout;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Actor-scopes Layout Builder widget placement lookups (WidgetAsset rows) the same
 * way {@see MediaScope} scopes generic AssetAttachment lookups:
 * a non-global actor only sees placements on pages/layouts assigned to their sites,
 * plus site-less (global) layouts. Shared between the Sections table's bounded usage
 * count subquery and {@see BuildSectionUsageSummaryAction}
 * so both read the same authorisation rule.
 */
final class SectionUsageScope
{
    public static function isGlobalActor(): bool
    {
        $actor = auth()->user();

        return $actor instanceof Authenticatable && SiteScope::isGlobalActor($actor);
    }

    /**
     * Widget placements only count as usage when they resolve to a real page or
     * layout. A widget's reusable default asset (no pageable owner yet) is not a
     * destination that publishing/deleting the section would affect.
     *
     * @param  Builder<WidgetAsset>  $query
     * @return Builder<WidgetAsset>
     */
    public static function applyPlacedForCurrentActor(Builder $query): Builder
    {
        $query->whereNotNull('pageable_type')->whereNotNull('pageable_id');

        return self::applyPageableScopeForCurrentActor($query);
    }

    /**
     * @param  Builder<WidgetAsset>  $query
     * @return Builder<WidgetAsset>
     */
    private static function applyPageableScopeForCurrentActor(Builder $query): Builder
    {
        $actor = auth()->user();

        if (! $actor instanceof Authenticatable) {
            return $query->whereRaw('1 = 0');
        }

        if (SiteScope::isGlobalActor($actor)) {
            return $query;
        }

        $assignedSiteIds = $actor->getAssignedSiteIds();

        if ($assignedSiteIds->isEmpty()) {
            return $query->whereHasMorph(
                'pageable',
                [Layout::class],
                fn (Builder $ownerQuery): Builder => $ownerQuery->whereNull('site_id'),
            );
        }

        return $query->whereHasMorph(
            'pageable',
            [...CapellCore::getPageVariationModels(), Layout::class],
            function (Builder $ownerQuery, string $ownerType) use ($assignedSiteIds): Builder {
                $ownerClass = Relation::getMorphedModel($ownerType) ?? $ownerType;

                if (is_a($ownerClass, Layout::class, true)) {
                    return $ownerQuery->where(
                        fn (Builder $layoutQuery): Builder => $layoutQuery
                            ->whereNull('site_id')
                            ->orWhereIn('site_id', $assignedSiteIds),
                    );
                }

                return $ownerQuery->whereIn('site_id', $assignedSiteIds);
            },
        );
    }
}
