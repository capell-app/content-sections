<?php

declare(strict_types=1);

namespace Capell\ContentSections\Models\Concerns;

use Capell\ContentSections\Models\Section;
use Carbon\Carbon;
use Carbon\CarbonInterface;

trait SectionNestedSet
{
    private ?Carbon $sectionDeletedAt = null;

    public static function bootNodeTrait(): void
    {
        static::saving(static function (Section $model): void {
            $model->callPendingAction();
        });

        static::deleting(static function (Section $model): void {
            if ($model->deletingAsDescendant) {
                return;
            }

            if (! static::usesSoftDelete() || $model->isForceDeleting()) {
                $model->refreshNode();
            }

            $model->deleteDescendants();
        });

        if (static::usesSoftDelete()) {
            static::restoring(static function (Section $model): void {
                $deletedAt = $model->getAttribute($model->getDeletedAtColumn());

                if ($deletedAt instanceof CarbonInterface) {
                    $model->sectionDeletedAt = new Carbon($deletedAt);
                }
            });

            static::restored(static function (Section $model): void {
                $deletedAt = $model->sectionDeletedAt;
                $model->sectionDeletedAt = null;

                if ($deletedAt instanceof Carbon) {
                    $model->restoreDescendants($deletedAt);
                }
            });
        }
    }
}
