<?php

declare(strict_types=1);

namespace Capell\ContentSections\Support;

use Capell\Admin\Support\SiteScope;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Site;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

final class SectionSiteScope
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyForCurrentActor(
        Builder $query,
        string $column = 'site_id',
        bool $includeGlobal = true,
        bool $denyWhenMissingActor = false,
    ): Builder {
        $actor = auth()->user();

        return self::applyForActor($query, $actor, $column, $includeGlobal, $denyWhenMissingActor);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyForActor(
        Builder $query,
        ?Authenticatable $actor,
        string $column = 'site_id',
        bool $includeGlobal = true,
        bool $denyWhenMissingActor = false,
    ): Builder {
        if (! $actor instanceof Authenticatable) {
            return $denyWhenMissingActor ? $query->whereRaw('1 = 0') : $query;
        }

        if (SiteScope::isGlobalActor($actor)) {
            return $query;
        }

        $assignedSiteIds = $actor->getAssignedSiteIds();

        return $query->where(function (Builder $query) use ($assignedSiteIds, $column, $includeGlobal): void {
            if ($includeGlobal) {
                $query->whereNull($column);
            }

            if ($assignedSiteIds->isNotEmpty()) {
                $includeGlobal
                    ? $query->orWhereIn($column, $assignedSiteIds)
                    : $query->whereIn($column, $assignedSiteIds);
            } elseif (! $includeGlobal) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    /**
     * @param  Builder<Site>  $query
     * @return Builder<Site>
     */
    public static function applySiteOptionsForCurrentActor(Builder $query): Builder
    {
        return SiteScope::applyForCurrentActor($query, 'id');
    }

    public static function actorCanUseSection(?Authenticatable $actor, Section $section): bool
    {
        return self::actorCanUseSiteId($actor, $section->site_id);
    }

    public static function actorCanUseSiteId(?Authenticatable $actor, int|string|null $siteId): bool
    {
        if ($siteId === null || $siteId === '' || (int) $siteId === 0) {
            return true;
        }

        if (! $actor instanceof Authenticatable) {
            return false;
        }

        if (SiteScope::isGlobalActor($actor)) {
            return true;
        }

        return $actor->getAssignedSiteIds()->contains((int) $siteId);
    }
}
