<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\EnsureSectionBlueprintForKeyAction;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Media;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\HtmlCache\Actions\ClearCachedUrlsForModelAction;
use Capell\HtmlCache\Models\CachedModelUrl;
use Capell\HtmlCache\Support\ModelServing\ModelEventRegistrar;
use Capell\HtmlCache\Support\ModelServing\RetrievedModelStore;
use Capell\LayoutBuilder\Actions\BuildPublicLayoutGraphAction;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

afterEach(function (): void {
    Model::preventAccessingMissingAttributes();
});

/**
 * Renders a page that shows a logo section, records which models that render retrieved against its
 * URL exactly as the Html Cache middleware does, and returns the media involved.
 *
 * @return array{media: Media, translation: Translation, url: string}
 */
function renderedPageWithBlockMedia(): array
{
    // The model tracker reads keys of every retrieved model, including partially selected ones.
    Model::preventAccessingMissingAttributes(false);
    Storage::fake('public');
    config()->set('filesystems.disks.public.visibility', 'public');
    config()->set('capell-html-cache.model_event_registration_mode', 'sync');
    resolve(ModelEventRegistrar::class)->registerModels();

    $language = Language::factory()->create();
    $site = Site::factory()->create(['language_id' => $language->id]);
    $section = Section::factory()->site($site)->blueprint(EnsureSectionBlueprintForKeyAction::run('logos'))
        ->withTranslations($language, ['title' => 'Trusted by', 'content' => '<p>Partners</p>'])
        ->create(['name' => 'Partners', 'visible_until' => now()->addDay()]);
    $added = $section->addMedia(UploadedFile::fake()->image('acme-mark.png', 40, 40))->toMediaCollection(Section::BLOCK_MEDIA_COLLECTION);
    $media = Media::query()->whereKey($added->getKey())->firstOrFail();
    $translation = Translation::factory()->translatable($media)->create(['title' => 'x', 'meta' => ['alt' => 'Acme logo']]);
    $section->update(['meta' => ['logos' => [['name' => 'Acme', 'media' => $media->uuid]]]]);

    $widget = Widget::factory()->create(['key' => 'cache-widget']);
    $layout = Layout::factory()->site($site)->create(['containers' => ['main' => ['widgets' => [['widget_key' => $widget->key, 'occurrence' => 1]]]]]);
    $page = Page::factory()->site($site)->layout($layout)->withTranslations($language)->create();
    WidgetAsset::factory()->widget($widget)->asset($section)->create(['order' => 1]);

    // The tracker only records what a request retrieves, so start the render from a clean slate.
    resolve(RetrievedModelStore::class)->flushToUrl('');
    $html = BuildPublicLayoutGraphAction::run($layout, $page, $language, includeHtml: true)->containers[0]->widgets[0]->html ?? '';
    expect($html)->toContain('acme-mark.png')->toContain('alt="Acme logo"');

    $url = 'https://cache-test.example/partners';
    resolve(RetrievedModelStore::class)->flushToUrl($url);

    return ['media' => $media, 'translation' => $translation, 'url' => $url];
}

it('tracks the media and alt text translation a cached page rendered', function (): void {
    $rendered = renderedPageWithBlockMedia();

    expect(CachedModelUrl::query()->where('url', $rendered['url'])->where('cacheable_type', $rendered['media']->getMorphClass())->where('cacheable_id', $rendered['media']->getKey())->exists())->toBeTrue()
        ->and(CachedModelUrl::query()->where('url', $rendered['url'])->where('cacheable_type', $rendered['translation']->getMorphClass())->where('cacheable_id', $rendered['translation']->getKey())->exists())->toBeTrue();
});

it('clears the cached page when its block media is replaced, deleted, or its alt text edited without saving the section', function (string $change): void {
    $rendered = renderedPageWithBlockMedia();
    $media = $rendered['media'];
    $translation = $rendered['translation'];
    Queue::fake();

    match ($change) {
        'file replaced' => $media->forceFill(['size' => $media->size + 1, 'file_name' => 'acme-mark-v2.png'])->save(),
        'deleted' => $media->delete(),
        'alt edited' => $translation->update(['meta' => ['alt' => 'Acme wordmark']]),
        'marked decorative' => $translation->update(['meta' => ['decorative' => true]]),
        default => throw new LogicException('Unknown change.'),
    };

    $subject = $change === 'alt edited' || $change === 'marked decorative' ? $translation : $media;

    ClearCachedUrlsForModelAction::assertPushed(static fn (mixed $action, array $parameters): bool => ($parameters[0] ?? null) === $subject->getMorphClass()
        && (int) ($parameters[1] ?? 0) === $subject->id);
})->with(['file replaced', 'deleted', 'alt edited', 'marked decorative']);
