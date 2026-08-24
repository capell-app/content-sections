<?php

declare(strict_types=1);

namespace Capell\ContentSections\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * A typed projection of where a Section is currently used, kept separate from the
 * Section's own outgoing/contained assets. Built fresh on every read so destructive
 * actions (unpublish/delete/force-delete) always confirm against current state.
 */
final class SectionUsageSummaryData extends Data
{
    private const int DESTINATION_PREVIEW_LIMIT = 5;

    public function __construct(
        public int $attachmentCount,
        public int $widgetCount,
        public bool $isAuthoritative,
        /** @var array<int, SectionUsageDestinationData> */
        public array $destinations = [],
    ) {}

    public static function blank(): self
    {
        return new self(attachmentCount: 0, widgetCount: 0, isAuthoritative: true, destinations: []);
    }

    public function totalCount(): int
    {
        return $this->attachmentCount + $this->widgetCount;
    }

    public function isUsed(): bool
    {
        return $this->totalCount() > 0;
    }

    public function impactSummary(): string
    {
        if (! $this->isUsed()) {
            return (string) __('capell-content-sections::message.usage_none');
        }

        $key = $this->isAuthoritative
            ? 'capell-content-sections::message.usage_count'
            : 'capell-content-sections::message.usage_count_partial';

        return (string) trans_choice($key, $this->totalCount(), ['count' => $this->totalCount()]);
    }

    public function destinationsPreview(int $limit = self::DESTINATION_PREVIEW_LIMIT): string
    {
        if ($this->destinations === []) {
            return '';
        }

        /** @var Collection<int, SectionUsageDestinationData> $shown */
        $shown = collect($this->destinations)->take($limit);

        $labels = $shown
            ->map(fn (SectionUsageDestinationData $destination): string => $destination->title)
            ->implode(', ');

        $remaining = $this->totalCount() - $shown->count();

        if ($remaining <= 0) {
            return $labels;
        }

        return $labels . ' ' . (string) trans_choice(
            'capell-content-sections::message.usage_destinations_more',
            $remaining,
            ['count' => $remaining],
        );
    }

    /**
     * @return array<string, array<int, SectionUsageDestinationData>>
     */
    public function groupedDestinations(): array
    {
        /** @var array<string, array<int, SectionUsageDestinationData>> $grouped */
        $grouped = collect($this->destinations)
            ->groupBy(fn (SectionUsageDestinationData $destination): string => $destination->kind)
            ->map(fn (Collection $group): array => $group->values()->all())
            ->all();

        return $grouped;
    }
}
