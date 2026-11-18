<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\BuildSectionRevisionSnapshotAction;
use Capell\ContentSections\Actions\RestoreSectionRevisionAction;
use Capell\ContentSections\Models\Section;
use Capell\ContentSections\Support\SectionRevisions;
use Capell\Core\Models\Language;
use Capell\Core\Models\Translation;
use Capell\PublishingStudio\Enums\PublishingRevisionEventEnum;
use Capell\PublishingStudio\Models\PublishingRevision;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(CreatesAdminUser::class);

function revisionSection(string $name, string $title, string $content): Section
{
    $section = Section::factory()->create(['name' => $name, 'workspace_id' => 0, 'meta' => ['tone' => 'calm']]);
    Translation::factory()->translatable($section)->create([
        'language_id' => Language::factory()->create()->id,
        'title' => $title,
        'content' => $content,
    ]);

    return $section->refresh();
}

it('keeps the state before the first tracked edit and each saved edit as revisions', function (): void {
    $section = revisionSection('Original', 'Original title', '<p>Original copy</p>');
    $before = BuildSectionRevisionSnapshotAction::run($section);

    $section->update(['name' => 'Edited']);
    $section->translations()->first()?->update(['title' => 'Edited title']);
    $section->unsetRelation('translations');

    SectionRevisions::recordSaved($section, $before, BuildSectionRevisionSnapshotAction::run($section), null);

    $history = SectionRevisions::history($section);

    expect(SectionRevisions::available())->toBeTrue()
        ->and($history)->toHaveCount(2)
        ->and($history[0]['changed'])->toContain('name')
        ->and(PublishingRevision::query()->where('revisionable_uuid', $section->uuid)->count())->toBe(2);
});

it('records nothing when a save changed nothing', function (): void {
    $section = revisionSection('Same', 'Same title', '<p>Same copy</p>');
    $snapshot = BuildSectionRevisionSnapshotAction::run($section);

    SectionRevisions::recordSaved($section, $snapshot, $snapshot, null);

    expect(SectionRevisions::history($section))->toBe([]);
});

it('restores a section to an earlier revision and records the restore', function (): void {
    $this->actingAsAdmin();
    $section = revisionSection('Original', 'Original title', '<p>Original copy</p>');
    $before = BuildSectionRevisionSnapshotAction::run($section);

    $section->update(['name' => 'Edited', 'meta' => ['tone' => 'loud']]);
    $section->translations()->first()?->update(['title' => 'Edited title', 'content' => '<p>Edited copy</p>']);
    $section->unsetRelation('translations');
    SectionRevisions::recordSaved($section, $before, BuildSectionRevisionSnapshotAction::run($section), null);

    $baseline = collect(SectionRevisions::history($section))->firstWhere('version', 1);
    throw_unless(is_array($baseline), RuntimeException::class, 'Expected a baseline revision.');

    $restored = RestoreSectionRevisionAction::run($section, $baseline['id']);
    $translation = $restored->translations()->first();

    expect($restored->name)->toBe('Original')
        ->and($restored->meta)->toBe(['tone' => 'calm'])
        ->and($translation?->title)->toBe('Original title')
        ->and($translation?->content)->toBe('<p>Original copy</p>')
        ->and(SectionRevisions::history($restored)[0]['event'])->toBe('restored');
});

it('refuses to restore a revision that belongs to another section', function (): void {
    $this->actingAsAdmin();
    $first = revisionSection('First', 'First title', '<p>First</p>');
    $second = revisionSection('Second', 'Second title', '<p>Second</p>');
    $before = BuildSectionRevisionSnapshotAction::run($second);
    $second->update(['name' => 'Second edited']);
    SectionRevisions::recordSaved($second, $before, BuildSectionRevisionSnapshotAction::run($second), null);
    $foreign = SectionRevisions::history($second)[0]['id'];

    expect(fn (): Section => RestoreSectionRevisionAction::run($first, $foreign))->toThrow(ValidationException::class)
        ->and($first->fresh()?->name)->toBe('First');
});

it('keeps translations when it restores a revision that does not carry them', function (): void {
    $this->actingAsAdmin();
    $section = revisionSection('Original', 'Kept title', '<p>Kept copy</p>');

    $revision = PublishingRevision::query()->create([
        'uuid' => (string) Str::uuid(),
        'revisionable_type' => Section::class,
        'revisionable_id' => $section->id,
        'revisionable_uuid' => $section->uuid,
        'version' => 1,
        'event_type' => PublishingRevisionEventEnum::Published,
        'after_payload' => ['name' => 'Older name'],
    ]);

    $restored = RestoreSectionRevisionAction::run($section, $revision->id);

    expect($restored->name)->toBe('Older name')
        ->and($restored->translations()->first()?->title)->toBe('Kept title');
});
