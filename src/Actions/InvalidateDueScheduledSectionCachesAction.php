<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Fragments\SectionFragmentSurrogateKey;
use Capell\ContentSections\Models\Section;
use Capell\Frontend\Support\Cache\CacheInvalidationRegistry;
use Capell\Frontend\Support\Cache\FragmentCache;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/**
 * Retires cached pages and fragments when a section's visible_from or visible_until passes, so a promotion
 * scheduled to end stops showing on time instead of at the next unrelated invalidation. Mirrors how Core
 * invalidates pages that cross a scheduled-publication boundary.
 */
final class InvalidateDueScheduledSectionCachesAction
{
    use AsFake;
    use AsObject;

    public const string CHECKPOINT_CACHE_KEY = 'capell:content-sections:scheduled-visibility-checkpoint';

    private const int FALLBACK_SCAN_MINUTES = 2;

    private const int CHECKPOINT_OVERLAP_SECONDS = 5;

    public function handle(?CarbonImmutable $until = null): int
    {
        $until ??= CarbonImmutable::now();
        $from = $this->checkpoint($until);
        $invalidated = 0;
        $registry = resolve(CacheInvalidationRegistry::class);
        $fragments = resolve(FragmentCache::class);

        Section::query()
            ->where(function (Builder $query) use ($from, $until): void {
                $query
                    ->whereBetween('visible_from', [$from, $until])
                    ->orWhereBetween('visible_until', [$from, $until]);
            })
            ->chunkById(100, function ($sections) use (&$invalidated, $registry, $fragments): void {
                foreach ($sections as $section) {
                    $registry->invalidateChangedModel($section);
                    // Shared fragments are keyed by section, not by the page rules the registry plans.
                    $fragments->invalidateBySurrogateKey(SectionFragmentSurrogateKey::for($section));
                    $invalidated++;
                }
            });

        Cache::forever(self::CHECKPOINT_CACHE_KEY, $until->toIso8601String());

        return $invalidated;
    }

    private function checkpoint(CarbonImmutable $until): CarbonImmutable
    {
        $stored = Cache::get(self::CHECKPOINT_CACHE_KEY);

        if (! is_string($stored)) {
            return $until->subMinutes(self::FALLBACK_SCAN_MINUTES);
        }

        try {
            $from = CarbonImmutable::parse($stored)->setTimezone($until->getTimezone());
        } catch (Throwable) {
            return $until->subMinutes(self::FALLBACK_SCAN_MINUTES);
        }

        if ($from->greaterThan($until)) {
            return $until->subMinutes(self::FALLBACK_SCAN_MINUTES);
        }

        return $from->subSeconds(self::CHECKPOINT_OVERLAP_SECONDS);
    }
}
