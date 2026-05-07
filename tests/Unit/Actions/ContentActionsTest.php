<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\CreateContentAction;
use Capell\ContentSections\Actions\ReplicateContentAction;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Language;

it('creates section content with translated title fallback for the section name', function (): void {
    $language = Language::factory()->create();
    $sectionData = Section::factory()->make([
        'name' => null,
    ]);

    $section = CreateContentAction::run([
        'type_id' => $sectionData->type_id,
        'site_id' => $sectionData->site_id,
        'meta' => ['label' => 'Homepage hero'],
        'order' => 3,
        'translations' => [
            [
                'language_id' => $language->getKey(),
                'title' => 'Welcome hero',
                'content' => 'Launch copy',
            ],
        ],
    ]);

    expect($section)->toBeInstanceOf(Section::class)
        ->and($section->name)->toBe('Welcome hero')
        ->and($section->order)->toBe(3)
        ->and($section->translations)->toHaveCount(1)
        ->and($section->translations->first()->language_id)->toBe($language->getKey())
        ->and($section->translations->first()->title)->toBe('Welcome hero')
        ->and($section->translations->first()->content)->toBe('Launch copy');
});

it('replicates section content with replacement data and replacement translations', function (): void {
    $language = Language::factory()->create();
    $section = Section::factory()
        ->withTranslations($language, [
            'title' => 'Original title',
            'content' => 'Original copy',
        ])
        ->create([
            'name' => 'Original section',
            'order' => 1,
        ]);

    $replica = ReplicateContentAction::run($section, [
        'name' => 'Replicated section',
        'order' => 9,
        'translations' => [
            [
                'language_id' => $language->getKey(),
                'title' => 'Replicated title',
                'content' => 'Replicated copy',
            ],
        ],
    ]);

    expect($replica)->toBeInstanceOf(Section::class)
        ->and($replica->is($section))->toBeFalse()
        ->and($replica->name)->toBe('Replicated section')
        ->and($replica->order)->toBe(9)
        ->and($replica->translations)->toHaveCount(1)
        ->and($replica->translations->first()->title)->toBe('Replicated title')
        ->and($replica->translations->first()->content)->toBe('Replicated copy')
        ->and($section->fresh()->name)->toBe('Original section');
});
