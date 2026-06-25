<?php

declare(strict_types=1);

use Capell\Admin\Filament\Livewire\PublishStatusPanel;
use Capell\ContentSections\Models\Section;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    test()->actingAsAdmin();
});

function sectionPanel(Section $section): Testable
{
    return Livewire::test(PublishStatusPanel::class, [
        'recordClass' => Section::class,
        'recordId' => $section->getKey(),
    ]);
}

it('shows publish controls for a live section', function (): void {
    $section = Section::factory()->create([
        'visible_from' => now()->subDay(),
        'visible_until' => null,
    ]);

    sectionPanel($section)
        ->assertOk()
        ->assertActionVisible('unpublish');
});

it('publishes a draft section immediately via the panel', function (): void {
    $section = Section::factory()->create([
        'visible_from' => now()->addYears(100),
        'visible_until' => null,
    ]);

    sectionPanel($section)->callAction('publishNow');

    expect($section->fresh()->isPending())->toBeFalse();
});

it('unpublishes a live section via the panel', function (): void {
    $section = Section::factory()->create([
        'visible_from' => now()->subDay(),
        'visible_until' => null,
    ]);

    sectionPanel($section)->callAction('unpublish');

    expect($section->fresh()->isExpired())->toBeTrue();
});
