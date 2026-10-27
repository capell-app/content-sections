<?php

declare(strict_types=1);

use Capell\Admin\Contracts\Media\AdminMediaFieldFactory;
use Capell\ContentSections\Actions\CloneSectionIntoWorkspaceAction;
use Capell\ContentSections\Actions\EnsureSectionBlueprintForKeyAction;
use Capell\ContentSections\Actions\HydrateSectionMediaSlotsAction;
use Capell\ContentSections\Actions\ReplicateContentAction;
use Capell\ContentSections\Actions\ResolveSectionMediaAction;
use Capell\ContentSections\Data\SectionMediaData;
use Capell\ContentSections\Filament\Resources\Sections\Pages\EditSection;
use Capell\ContentSections\Filament\Resources\Sections\SectionResource;
use Capell\ContentSections\Models\Section;
use Capell\Core\Data\Media\ExternalVideoData;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Media;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\LayoutBuilder\Actions\BuildPublicLayoutGraphAction;
use Capell\LayoutBuilder\Data\PublicLayoutWidgetData;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Capell\PublishingStudio\Models\Workspace;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function sectionBlockMedia(Section $section, array $attributes = []): Media
{
    return Media::factory()->model($section)->create([
        'collection_name' => Section::BLOCK_MEDIA_COLLECTION,
        'name' => 'Acme mark',
        'file_name' => 'acme-mark.png',
        'mime_type' => 'image/png',
        ...$attributes,
    ]);
}

function resolvedAlt(Section $section, Media $media, ?Language $language = null): ?string
{
    $resolved = ResolveSectionMediaAction::run($section, $media->uuid, $language);
    throw_unless($resolved instanceof SectionMediaData, RuntimeException::class, 'Expected the media to resolve.');

    return $resolved->alt;
}

/**
 * @param  array<string, mixed>  $meta
 */
function mediaMetadata(Media $media, array $meta): void
{
    Translation::factory()->translatable($media)->create(['title' => $media->name, 'meta' => $meta]);
}

/**
 * @param  array<string, mixed>  $meta
 */
function sectionForBlock(string $key, array $meta = []): Section
{
    $language = Language::factory()->create();
    $site = Site::factory()->create(['language_id' => $language->id]);

    return Section::factory()
        ->site($site)
        ->blueprint(EnsureSectionBlueprintForKeyAction::run($key))
        ->withTranslations($language, ['title' => 'Trusted by', 'content' => '<p>Partners</p>'])
        ->create(['name' => 'Partner ' . $key, 'meta' => $meta, 'visible_until' => now()->addDay()]);
}

/**
 * @return list<array<string, mixed>>
 */
function sectionLogos(Section $section): array
{
    $meta = $section->refresh()->meta;
    $logos = is_array($meta) ? ($meta['logos'] ?? []) : [];

    $items = [];

    foreach (is_array($logos) ? $logos : [] as $logo) {
        if (is_array($logo)) {
            $items[] = collect($logo)->all();
        }
    }

    return $items;
}

function publicBlockWidget(Section $section): PublicLayoutWidgetData
{
    $site = Site::query()->findOrFail($section->site_id);
    $language = Language::query()->findOrFail($site->language_id);
    $widget = Widget::factory()->create(['key' => 'media-widget']);
    $layout = Layout::factory()->site($site)->create([
        'containers' => ['main' => ['widgets' => [['widget_key' => $widget->key, 'occurrence' => 1]]]],
    ]);
    $page = Page::factory()->site($site)->layout($layout)->withTranslations($language)->create();
    WidgetAsset::factory()->widget($widget)->asset($section)->create(['order' => 1]);

    return BuildPublicLayoutGraphAction::run($layout, $page, $language, includeHtml: true)->containers[0]->widgets[0];
}

it('resolves only an image attached to the same section on a public disk without a signed url', function (): void {
    $section = sectionForBlock('logos');
    $other = sectionForBlock('logos');
    $own = sectionBlockMedia($section);
    mediaMetadata($own, ['alt' => ' Acme logo ']);
    $decorative = sectionBlockMedia($section, ['file_name' => 'flourish.png']);
    mediaMetadata($decorative, ['alt' => 'Ignored', 'decorative' => true]);
    $unlabelled = sectionBlockMedia($section, ['file_name' => 'plain.png']);
    $foreign = sectionBlockMedia($other);
    $document = sectionBlockMedia($section, ['mime_type' => 'application/pdf', 'file_name' => 'brochure.pdf']);
    $private = sectionBlockMedia($section, ['disk' => 'local']);
    $deleted = sectionBlockMedia($section);
    $deleted->delete();
    $section->refresh();

    $resolved = ResolveSectionMediaAction::run($section, $own->uuid);

    expect($resolved)->toBeInstanceOf(SectionMediaData::class)
        ->and($resolved?->alt)->toBe('Acme logo')
        ->and($resolved?->url)->toContain('acme-mark.png')->not->toContain('?')
        ->and(resolvedAlt($section, $decorative))->toBe('')
        ->and(resolvedAlt($section, $unlabelled))->toBeNull()
        ->and(ResolveSectionMediaAction::run($section, $foreign->uuid))->toBeNull()
        ->and(ResolveSectionMediaAction::run($section, $document->uuid))->toBeNull()
        ->and(ResolveSectionMediaAction::run($section, $private->uuid))->toBeNull()
        ->and(ResolveSectionMediaAction::run($section, $deleted->uuid))->toBeNull()
        ->and(ResolveSectionMediaAction::run($section, 'not-a-uuid'))->toBeNull()
        ->and(ResolveSectionMediaAction::run($section, null))->toBeNull()
        ->and(ResolveSectionMediaAction::run($section, ['array']))->toBeNull();
});

it('persists the media chosen for a logo from the section edit form', function (): void {
    test()->actingAsAdmin();
    $section = sectionForBlock('logos');
    $media = sectionBlockMedia($section);

    livewire(EditSection::class, ['record' => $section->getRouteKey()])
        ->assertSuccessful()
        ->fillForm(['meta' => ['logos' => [['name' => 'Acme', 'url' => 'https://acme.example', 'media' => $media->uuid]], 'columns' => '4']])
        ->call('save')
        ->assertHasNoFormErrors();

    $logos = sectionLogos($section);

    expect($logos)->toHaveCount(1)
        ->and($logos[0]['media'] ?? null)->toBe($media->uuid)
        ->and($logos[0]['name'] ?? null)->toBe('Acme');
});

it('stores images uploaded in the section editor on a public disk even when Filament defaults to a private one', function (): void {
    test()->actingAsAdmin();
    config()->set('filament.default_filesystem_disk', 'local');
    config()->set('filesystems.disks.public.visibility', 'public');
    config()->set('media-library.disk_name', 'local');
    // A package that replaces the Admin media field leaves the plain Spatie upload, which would
    // otherwise take Filament's default (private) disk.
    app()->bind(AdminMediaFieldFactory::class, static fn (): AdminMediaFieldFactory => new class implements AdminMediaFieldFactory
    {
        public function make(string $name): Field
        {
            return TextInput::make($name);
        }
    });
    $section = sectionForBlock('logos', ['logos' => [['name' => 'Acme', 'url' => 'https://acme.example']]]);

    $editor = livewire(EditSection::class, ['record' => $section->getRouteKey()])->assertSuccessful()->instance();
    throw_unless($editor instanceof EditSection, RuntimeException::class, 'Expected the section editor.');
    $form = $editor->getSchema('form');
    $uploads = array_values(array_filter(
        $form?->getFlatFields(withHidden: true) ?? [],
        static fn (mixed $field): bool => $field instanceof SpatieMediaLibraryFileUpload && $field->getCollection() === Section::BLOCK_MEDIA_COLLECTION,
    ));

    expect($uploads)->toHaveCount(1)
        ->and($uploads[0]->getDiskName())->toBe('public')
        ->and($uploads[0]->getVisibility())->toBe('public');
});

it('refuses media that is not attached to the section being edited', function (): void {
    test()->actingAsAdmin();
    $section = sectionForBlock('logos', ['logos' => []]);
    $foreign = sectionBlockMedia(sectionForBlock('logos'));

    livewire(EditSection::class, ['record' => $section->getRouteKey()])
        ->fillForm(['meta' => ['logos' => [['name' => 'Acme', 'media' => $foreign->uuid]], 'columns' => '4']])
        ->call('save')
        ->assertHasFormErrors();

    expect(sectionLogos($section))->toBe([]);
});

it('refuses a user without section permissions from editing the media assignment', function (): void {
    test()->actingAsUser();
    $section = sectionForBlock('logos', ['logos' => []]);
    $media = sectionBlockMedia($section);

    get(SectionResource::getUrl('edit', ['record' => $section]))->assertForbidden();

    expect(sectionLogos($section))->toBe([])
        ->and(ResolveSectionMediaAction::run($section, $media->uuid))->toBeInstanceOf(SectionMediaData::class);
});

it('renders the selected logo image publicly without media identifiers or authoring state', function (): void {
    $section = sectionForBlock('logos');
    $media = sectionBlockMedia($section);
    mediaMetadata($media, ['alt' => 'Acme logo']);
    $section->update(['meta' => ['columns' => '4', 'logos' => [['name' => 'Acme', 'url' => 'https://acme.example', 'media' => $media->uuid]]]]);

    $widget = publicBlockWidget($section->refresh());
    $html = $widget->html ?? '';
    $payload = json_encode($widget->data, JSON_THROW_ON_ERROR);

    expect($html)
        ->toContain('<img')
        ->toContain('acme-mark.png')
        ->toContain('alt="Acme logo"')
        ->not->toContain($media->uuid)
        ->not->toContain('wire:')
        ->not->toContain('signed-editor')
        ->not->toContain('Signature=')
        ->and($payload)
        ->toContain('acme-mark.png')
        ->not->toContain($media->uuid)
        ->not->toContain('"media"');
});

it('falls back to the logo name when the selected media was deleted or never existed', function (): void {
    $section = sectionForBlock('logos');
    $deleted = sectionBlockMedia($section);
    $section->update(['meta' => ['columns' => '4', 'logos' => [
        ['name' => 'Acme', 'url' => 'https://acme.example', 'media' => $deleted->uuid],
        ['name' => 'Globex', 'url' => 'https://globex.example', 'media' => '00000000-0000-4000-8000-000000000000'],
    ]]]);
    $deleted->delete();

    $html = publicBlockWidget($section->refresh())->html ?? '';

    expect($html)
        ->toContain('Acme')
        ->toContain('Globex')
        ->not->toContain('<img')
        ->not->toContain($deleted->uuid);
});

it('renders a team member photo from the same media reference', function (): void {
    $section = sectionForBlock('team');
    $media = sectionBlockMedia($section);
    $section->update(['meta' => ['columns' => '3', 'members' => [['name' => 'Ada Lovelace', 'role' => 'Engineer', 'media' => $media->uuid]]]]);

    $html = publicBlockWidget($section->refresh())->html ?? '';

    expect($html)
        ->toContain('acme-mark.png')
        ->toContain('alt=""')
        ->not->toContain($media->uuid);
});

/**
 * A section whose block media is a real stored file, referenced by one item of the given slot.
 *
 * @return array{0: Section, 1: Media}
 */
function sectionWithStoredMedia(string $blockKey = 'logos', string $listKey = 'logos'): array
{
    Storage::fake('public');
    config()->set('filesystems.disks.public.visibility', 'public');
    $section = sectionForBlock($blockKey);
    $added = $section->addMedia(UploadedFile::fake()->image('acme-mark.png', 40, 40))
        ->toMediaCollection(Section::BLOCK_MEDIA_COLLECTION);
    $media = Media::query()->whereKey($added->getKey())->firstOrFail();
    $section->update(['meta' => ['columns' => '4', $listKey => [['name' => 'Acme', 'media' => $media->uuid]]]]);

    return [$section->refresh(), $media];
}

/**
 * @param  array<array-key, mixed>|null  $meta
 * @return array<string, mixed>
 */
function firstItem(?array $meta, string $listKey = 'logos'): array
{
    return itemAt($meta, 0, $listKey);
}

/**
 * @param  array<array-key, mixed>|null  $meta
 * @return array<string, mixed>
 */
function itemAt(?array $meta, int $index, string $listKey = 'logos'): array
{
    $items = $meta[$listKey] ?? [];
    $item = is_array($items) ? ($items[$index] ?? []) : [];

    return is_array($item) ? collect($item)->all() : [];
}

/**
 * @param  array<string, mixed>  $item
 */
function itemImageUrl(array $item): string
{
    $image = is_array($item['image'] ?? null) ? $item['image'] : [];
    $url = $image['url'] ?? '';

    return is_string($url) ? $url : '';
}

function copyBlockMedia(string $how, Section $section): Section
{
    if ($how === 'replicate') {
        return ReplicateContentAction::run($section);
    }

    $clone = CloneSectionIntoWorkspaceAction::run($section, Workspace::factory()->create());
    throw_unless($clone instanceof Section, RuntimeException::class, 'Expected a cloned section.');

    return $clone;
}

it('copies block media with its file and repoints the stored meta when a section is cloned or duplicated', function (string $how, string $blockKey, string $listKey): void {
    [$section, $media] = sectionWithStoredMedia($blockKey, $listKey);

    $copied = copyBlockMedia($how, $section);
    $copy = $copied->getMedia(Section::BLOCK_MEDIA_COLLECTION)->sole();
    $stored = firstItem($copied->refresh()->meta, $listKey);
    $hydrated = firstItem(HydrateSectionMediaSlotsAction::run($copied, is_array($copied->meta) ? $copied->meta : []), $listKey);

    expect($copy->uuid)->not->toBe($media->uuid)
        ->and($copy->getKey())->not->toBe($media->getKey())
        ->and(Storage::disk('public')->exists($copy->getPathRelativeToRoot()))->toBeTrue()
        ->and(Storage::disk('public')->size($copy->getPathRelativeToRoot()))->toBeGreaterThan(0)
        ->and($stored['media'] ?? null)->toBe($copy->uuid)
        ->and(itemImageUrl($hydrated))->toBe($copy->getUrl())
        ->and($hydrated)->not->toHaveKey('media')
        ->and(firstItem($section->refresh()->meta, $listKey)['media'] ?? null)->toBe($media->uuid)
        ->and($section->getMedia(Section::BLOCK_MEDIA_COLLECTION))->toHaveCount(1)
        ->and(ResolveSectionMediaAction::run($copied, $media->uuid))->toBeNull();
})->with([
    'workspace clone, logos' => ['clone', 'logos', 'logos'],
    'workspace clone, team members' => ['clone', 'team', 'members'],
    'duplicate, logos' => ['replicate', 'logos', 'logos'],
    'duplicate, team members' => ['replicate', 'team', 'members'],
]);

it('keeps alt text and the decorative flag on every copy of a block image', function (string $how, array $metadata, ?string $expected): void {
    [$section, $media] = sectionWithStoredMedia();
    mediaMetadata($media, $metadata);
    expect(resolvedAlt($section, $media))->toBe($expected);

    $copied = copyBlockMedia($how, $section);
    $copy = $copied->getMedia(Section::BLOCK_MEDIA_COLLECTION)->sole();

    expect($copy->uuid)->not->toBe($media->uuid)
        ->and(resolvedAlt($copied, Media::query()->whereKey($copy->getKey())->firstOrFail()))->toBe($expected);
})->with(function (): array {
    $cases = [];

    foreach (['clone', 'replicate'] as $how) {
        $cases[$how . ' with alt text'] = [$how, ['alt' => 'Acme wordmark'], 'Acme wordmark'];
        $cases[$how . ' decorative'] = [$how, ['alt' => 'Ignored', 'decorative' => true], ''];
        $cases[$how . ' without alt text'] = [$how, [], null];
    }

    return $cases;
});

it('drops the reference instead of copying when the source file is missing or empty', function (string $how, string $problem): void {
    [$section, $media] = sectionWithStoredMedia();
    $path = $media->getPathRelativeToRoot();
    $problem === 'missing' ? Storage::disk('public')->delete($path) : Storage::disk('public')->put($path, '');
    $filesBefore = Storage::disk('public')->allFiles();

    $copied = copyBlockMedia($how, $section);

    expect($copied->getMedia(Section::BLOCK_MEDIA_COLLECTION))->toHaveCount(0)
        ->and(firstItem($copied->refresh()->meta)['media'] ?? null)->toBeNull()
        ->and(itemImageUrl(firstItem(HydrateSectionMediaSlotsAction::run($copied, is_array($copied->meta) ? $copied->meta : []))))->toBe('')
        ->and(Storage::disk('public')->allFiles())->toBe($filesBefore)
        ->and($section->getMedia(Section::BLOCK_MEDIA_COLLECTION))->toHaveCount(1);
})->with([
    'clone, missing' => ['clone', 'missing'],
    'clone, empty' => ['clone', 'empty'],
    'duplicate, missing' => ['replicate', 'missing'],
    'duplicate, empty' => ['replicate', 'empty'],
]);

it('never copies another section\'s media through a foreign reference in meta', function (string $how): void {
    [$section] = sectionWithStoredMedia();
    $foreign = sectionBlockMedia(sectionForBlock('logos'));
    $section->update(['meta' => ['logos' => [['name' => 'Acme', 'media' => $foreign->uuid], ['name' => 'Globex', 'media' => 'not-a-uuid']]]]);

    $copied = copyBlockMedia($how, $section->refresh());
    $meta = $copied->refresh()->meta;

    expect(itemAt($meta, 0)['media'] ?? null)->toBeNull()
        ->and(itemAt($meta, 1)['media'] ?? null)->toBeNull()
        ->and($copied->getMedia(Section::BLOCK_MEDIA_COLLECTION))->toHaveCount(1)
        ->and(Media::query()->where('uuid', $foreign->uuid)->count())->toBe(1);
})->with(['clone', 'replicate']);

it('leaves nothing behind when copying aborts part way', function (string $how): void {
    [$section] = sectionWithStoredMedia();
    $section->addMedia(UploadedFile::fake()->image('second.png', 30, 30))->toMediaCollection(Section::BLOCK_MEDIA_COLLECTION);
    $section->refresh();
    $filesBefore = Storage::disk('public')->allFiles();
    $rowsBefore = Media::query()->count();
    $sectionsBefore = Section::query()->withoutGlobalScopes()->count();
    $created = 0;
    Media::creating(static function () use (&$created): void {
        if (++$created === 2) {
            throw new RuntimeException('Simulated failure while copying the second image.');
        }
    });

    expect(fn (): Section => copyBlockMedia($how, $section))->toThrow(RuntimeException::class, 'Simulated failure');

    expect(Storage::disk('public')->allFiles())->toBe($filesBefore)
        ->and(Media::query()->count())->toBe($rowsBefore)
        ->and(Section::query()->withoutGlobalScopes()->count())->toBe($sectionsBefore);
})->with(['clone', 'replicate']);

it('does not touch meta when a section has no block media', function (): void {
    $section = sectionForBlock('logos');
    $replica = ReplicateContentAction::run($section);

    expect($replica->refresh()->getRawOriginal('meta'))->toBe($section->refresh()->getRawOriginal('meta'));

    $nullMeta = sectionForBlock('logos');
    DB::table('sections')->where('id', $nullMeta->getKey())->update(['meta' => null]);

    expect(ReplicateContentAction::run($nullMeta->refresh())->getRawOriginal('meta'))->toBeNull();
});

it('uses the alt text of the page language and falls back to the default language', function (): void {
    [$section, $media] = sectionWithStoredMedia();
    $default = Language::query()->findOrFail(Site::query()->findOrFail($section->site_id)->language_id);
    $default->forceFill(['default' => true])->save();
    $french = Language::factory()->create(['default' => false]);
    $german = Language::factory()->create(['default' => false]);
    Translation::factory()->translatable($media)->language($default)->create(['title' => 'x', 'meta' => ['alt' => 'Acme logo']]);
    Translation::factory()->translatable($media)->language($french)->create(['title' => 'x', 'meta' => ['alt' => 'Logo Acme']]);
    $section->refresh();

    expect(resolvedAlt($section, $media, $french))->toBe('Logo Acme')
        ->and(resolvedAlt($section, $media, $german))->toBe('Acme logo')
        ->and(resolvedAlt($section, $media))->toBe('Acme logo');
});

it('does not add a query per image when resolving alt text for a list of logos', function (): void {
    [$section, $media] = sectionWithStoredMedia();
    mediaMetadata($media, ['alt' => 'Acme logo']);
    $second = $section->addMedia(UploadedFile::fake()->image('b.png', 30, 30))->toMediaCollection(Section::BLOCK_MEDIA_COLLECTION);
    $third = $section->addMedia(UploadedFile::fake()->image('c.png', 30, 30))->toMediaCollection(Section::BLOCK_MEDIA_COLLECTION);
    $section->update(['meta' => ['logos' => [
        ['name' => 'A', 'media' => $media->uuid], ['name' => 'B', 'media' => $second->uuid], ['name' => 'C', 'media' => $third->uuid],
    ]]]);
    $section->refresh()->media;
    DB::flushQueryLog();
    DB::enableQueryLog();

    HydrateSectionMediaSlotsAction::run($section, is_array($section->meta) ? $section->meta : []);

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(3);
});

it('lets an editor clear a reference to deleted media and refuses to save it otherwise', function (): void {
    test()->actingAsAdmin();
    $section = sectionForBlock('logos');
    $media = sectionBlockMedia($section);
    $stale = $media->uuid;
    $media->delete();
    $section->update(['meta' => ['columns' => '4', 'logos' => [['name' => 'Acme', 'media' => $stale]]]]);

    livewire(EditSection::class, ['record' => $section->getRouteKey()])
        ->assertSuccessful()
        ->call('save')
        ->assertHasFormErrors();

    livewire(EditSection::class, ['record' => $section->getRouteKey()])
        ->fillForm(['meta' => ['columns' => '4', 'logos' => [['name' => 'Acme', 'media' => null]]]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(firstItem($section->refresh()->meta)['media'] ?? null)->toBeNull();
});

it('refuses to select an image that is not on a public disk', function (): void {
    test()->actingAsAdmin();
    $section = sectionForBlock('logos', ['logos' => []]);
    $private = sectionBlockMedia($section, ['disk' => 'local']);

    livewire(EditSection::class, ['record' => $section->getRouteKey()])
        ->fillForm(['meta' => ['logos' => [['name' => 'Acme', 'media' => $private->uuid]], 'columns' => '4']])
        ->call('save')
        ->assertHasFormErrors();

    expect($section->refresh()->meta['logos'] ?? [])->toBe([]);
});

it('copies the image and video collections with their files and alt text when a section is cloned or duplicated', function (string $how): void {
    Storage::fake('public');
    $section = sectionForBlock('hero');
    $image = $section->addMedia(UploadedFile::fake()->image('hero.jpg', 40, 40))->toMediaCollection('image');
    mediaMetadata(Media::query()->whereKey($image->getKey())->firstOrFail(), ['alt' => 'Hero banner']);
    $video = $section->addMedia(UploadedFile::fake()->createWithContent('intro.mp4', 'not-really-a-video'))->toMediaCollection('video');
    $section->refresh();

    $copied = copyBlockMedia($how, $section);
    $copiedImage = $copied->getMedia('image')->sole();
    $copiedVideo = $copied->getMedia('video')->sole();

    expect($copiedImage->uuid)->not->toBe($image->uuid)
        ->and($copiedVideo->uuid)->not->toBe($video->uuid)
        ->and(Storage::disk('public')->size($copiedImage->getPathRelativeToRoot()))->toBeGreaterThan(0)
        ->and(Storage::disk('public')->size($copiedVideo->getPathRelativeToRoot()))->toBeGreaterThan(0)
        ->and(Media::query()->whereKey($copiedImage->getKey())->firstOrFail()->getAltText())->toBe('Hero banner')
        ->and($section->getMedia('image'))->toHaveCount(1);
})->with(['clone', 'replicate']);

it('copies the row of an external video, which has no file', function (string $how): void {
    $section = sectionForBlock('hero');
    $video = Media::factory()->model($section)->create(['collection_name' => 'video', 'file_name' => 'youtube.json', 'mime_type' => 'video/youtube']);
    $video->setExternalVideo(new ExternalVideoData('youtube', 'abc123', 'https://youtu.be/abc123', 'https://www.youtube.com/embed/abc123', 'https://img.youtube.com/vi/abc123/0.jpg'));
    $video->save();
    $section->refresh();

    $copied = copyBlockMedia($how, $section);
    $copy = $copied->getMedia('video')->sole();

    expect($copy->uuid)->not->toBe($video->uuid)
        ->and($copy instanceof Media && $copy->isExternalVideo())->toBeTrue();
})->with(['clone', 'replicate']);

it('does not leave a broken image row behind when the source image file is missing', function (string $how): void {
    Storage::fake('public');
    $section = sectionForBlock('hero');
    $image = $section->addMedia(UploadedFile::fake()->image('hero.jpg', 40, 40))->toMediaCollection('image');
    Storage::disk('public')->delete($image->getPathRelativeToRoot());
    $filesBefore = Storage::disk('public')->allFiles();
    $section->refresh();

    $copied = copyBlockMedia($how, $section);

    expect($copied->getMedia('image'))->toHaveCount(0)
        ->and(Storage::disk('public')->allFiles())->toBe($filesBefore)
        ->and($section->getMedia('image'))->toHaveCount(1);
})->with(['clone', 'replicate']);
