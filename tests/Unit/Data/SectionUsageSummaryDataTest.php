<?php

declare(strict_types=1);

use Capell\ContentSections\Data\SectionUsageDestinationData;
use Capell\ContentSections\Data\SectionUsageSummaryData;

it('reports zero total and unused for an empty summary', function (): void {
    $summary = SectionUsageSummaryData::blank();

    expect($summary->totalCount())->toBe(0)
        ->and($summary->isUsed())->toBeFalse()
        ->and($summary->impactSummary())->toBe(__('capell-content-sections::message.usage_none'));
});

it('reconciles total count as the sum of attachment and widget counts', function (): void {
    $summary = new SectionUsageSummaryData(attachmentCount: 3, widgetCount: 4, isAuthoritative: true);

    expect($summary->totalCount())->toBe(7)->and($summary->isUsed())->toBeTrue();
});

it('groups destinations by kind', function (): void {
    $summary = new SectionUsageSummaryData(
        attachmentCount: 1,
        widgetCount: 1,
        isAuthoritative: true,
        destinations: [
            new SectionUsageDestinationData(kind: 'attachment', type: 'Page', title: 'A'),
            new SectionUsageDestinationData(kind: 'widget', type: 'Layout', title: 'B'),
        ],
    );

    $grouped = $summary->groupedDestinations();

    expect($grouped)->toHaveKeys(['attachment', 'widget'])
        ->and($grouped['attachment'])->toHaveCount(1)
        ->and($grouped['widget'])->toHaveCount(1);
});

it('previews a bounded list of destination titles with an overflow count', function (): void {
    $destinations = collect(range(1, 6))
        ->map(fn (int $i): SectionUsageDestinationData => new SectionUsageDestinationData(
            kind: 'attachment',
            type: 'Page',
            title: 'Page ' . $i,
        ))
        ->all();

    $summary = new SectionUsageSummaryData(
        attachmentCount: 6,
        widgetCount: 0,
        isAuthoritative: true,
        destinations: $destinations,
    );

    $preview = $summary->destinationsPreview(3);

    expect($preview)->toContain('Page 1', 'Page 2', 'Page 3')
        ->and($preview)->not->toContain('Page 4')
        ->and($preview)->toContain('3');
});

it('uses the partial impact message for a non-authoritative summary', function (): void {
    $summary = new SectionUsageSummaryData(attachmentCount: 1, widgetCount: 0, isAuthoritative: false);

    expect($summary->impactSummary())->toContain('at least');
});
