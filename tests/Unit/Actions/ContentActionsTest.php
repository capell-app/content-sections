<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\CreateContentAction;
use Capell\ContentSections\Actions\CreateHeroContentBlueprintAction;
use Capell\ContentSections\Actions\ReplicateContentAction;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Translation;

it('creates section content with translated title fallback for the section name', function (): void {
    $language = Language::factory()->create();
    $sectionData = Section::factory()->make([
        'name' => null,
    ]);

    $section = CreateContentAction::run([
        'blueprint_id' => $sectionData->blueprint_id,
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
    $translation = $section->translations->first();

    throw_unless($translation instanceof Translation, RuntimeException::class, 'Expected section translation to be created.');

    expect($section)->toBeInstanceOf(Section::class)
        ->and($section->name)->toBe('Welcome hero')
        ->and($section->order)->toBe(3)
        ->and($section->translations)->toHaveCount(1)
        ->and($translation->language_id)->toBe($language->getKey())
        ->and($translation->title)->toBe('Welcome hero')
        ->and($translation->content)->toBe('Launch copy');
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
    $translation = $replica->translations->first();

    throw_unless($translation instanceof Translation, RuntimeException::class, 'Expected replicated section translation to be created.');

    expect($replica)->toBeInstanceOf(Section::class)
        ->and($replica->is($section))->toBeFalse()
        ->and($replica->name)->toBe('Replicated section')
        ->and($replica->order)->toBe(9)
        ->and($replica->translations)->toHaveCount(1)
        ->and($translation->title)->toBe('Replicated title')
        ->and($translation->content)->toBe('Replicated copy')
        ->and($section->fresh()->name)->toBe('Original section');
});

it('creates the hero section blueprint with an editor-facing description', function (): void {
    $blueprint = CreateHeroContentBlueprintAction::run();

    expect($blueprint)->toBeInstanceOf(Blueprint::class)
        ->and($blueprint->name)->toBe('Hero')
        ->and(($blueprint->admin ?? [])['notes'] ?? null)->toBe('A page-opening section for headline, supporting copy, artwork, and primary action.');
});
