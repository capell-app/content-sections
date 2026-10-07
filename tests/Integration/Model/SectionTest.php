<?php

declare(strict_types=1);

use Capell\ContentSections\Models\Section;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;

afterEach(function (): void {
    Date::use(Carbon::class);
});

it('uses the existing sections table and section morph alias', function (): void {
    $section = Section::factory()->create(['name' => 'Feature strip']);

    expect($section->getTable())->toBe('sections')
        ->and($section->getMorphClass())->toBe('section')
        ->and(Relation::getMorphedModel('section'))->toBe(Section::class);
});

it('restores a Section tree under an immutable date class', function (): void {
    Date::use(CarbonImmutable::class);
    $this->freezeTime();

    $parent = Section::factory()->createOne();
    $child = Section::factory()->parent($parent)->createOne();
    $grandchild = Section::factory()->parent($child)->createOne();
    $sibling = Section::factory()->parent($parent)->createOne();
    $independent = Section::factory()->createOne();
    $independent->delete();
    $parent->refresh()->delete();

    $ids = [$parent->id, $child->id, $grandchild->id, $sibling->id];

    expect($parent->fresh()->deleted_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and(Section::onlyTrashed()->whereKey([...$ids, $independent->id])->count())->toBe(5)
        ->and($parent->restore())->toBeTrue()
        ->and(Section::onlyTrashed()->whereKey($ids)->count())->toBe(0)
        ->and($independent->fresh()->trashed())->toBeTrue();
});
