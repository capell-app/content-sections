<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\Fragments\RenderPublicSectionFragmentAction;
use Capell\ContentSections\Actions\InvalidateDueScheduledSectionCachesAction;
use Capell\ContentSections\Enums\PublicSectionFragmentOutcome;
use Capell\ContentSections\Fragments\ContentSectionsFragmentUrlResolver;
use Capell\ContentSections\Models\Section;
use Capell\ContentSections\Tests\Support\PublicSectionFragmentState;
use Capell\Core\Data\RenderableDefinitionData;
use Capell\Core\Database\Factories\TranslationFactory;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Media;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\Core\Support\Publishing\PublishSentinel;
use Capell\Core\Support\Renderables\RenderableRegistry;
use Capell\Frontend\Actions\Fragments\ResolvePublicFragmentAssetVersionAction;
use Capell\Frontend\Actions\Fragments\ResolvePublicFragmentCacheIdentityAction;
use Capell\Frontend\Actions\Fragments\ResolvePublicFragmentContentVersionAction;
use Capell\Frontend\Contracts\Fragments\PublicFragmentReferenceCodec;
use Capell\Frontend\Data\Fragments\PublicFragmentReferenceData;
use Capell\Frontend\Support\Cache\FragmentCache;
use Capell\Frontend\Support\Renderables\RenderableDynamicDataRegistry;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Capell\LayoutBuilder\Support\Loader\LayoutLoader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

it('returns identical empty public failures for invalid capability tokens', function (): void {
    $this->get(route('capell-content-sections.fragments.show', ['reference' => 'invalid'], false))->assertNotFound()->assertContent('');
});

it('separates internal rendering crashes from absent sections without exposing internals', function (): void {
    app()->instance(PublicFragmentReferenceCodec::class, new class implements PublicFragmentReferenceCodec
    {
        #[Override]
        public function encode(PublicFragmentReferenceData $reference): string
        {
            throw new LogicException;
        }

        #[Override]
        public function decode(string $token): PublicFragmentReferenceData
        {
            throw new RuntimeException('private details');
        }
    });
    Log::spy();
    $this->get(route('capell-content-sections.fragments.show', ['reference' => 'opaque'], false))->assertStatus(500)->assertContent('');
});

it('renders a non-marketing public section through its registered owner and invalidates the shared fragment', function (): void {
    $fixture = sharedSectionFragmentFixture();
    $fixture['state']->html = '<section>Shared public section</section>';
    expect(RenderPublicSectionFragmentAction::run($fixture['token']))->toBe('<section>Shared public section</section>');
    expect(RenderPublicSectionFragmentAction::run($fixture['token']))->toBe('<section>Shared public section</section>');
    resolve(FragmentCache::class)->invalidateBySurrogateKey('site-' . $fixture['reference']->siteId);
    expect(RenderPublicSectionFragmentAction::run($fixture['token']))->toBe('<section>Shared public section</section>');
    expect($fixture['state']->renders)->toBe(2);
});

it('revokes cached sections when detached, unpublished, or changed', function (Closure $mutate): void {
    $fixture = sharedSectionFragmentFixture();
    $fixture['state']->html = '<section>Shared section</section>';
    expect(RenderPublicSectionFragmentAction::run($fixture['token']))->toBe('<section>Shared section</section>');
    $mutate($fixture);
    app()->forgetInstance(LayoutLoader::class);
    expect(RenderPublicSectionFragmentAction::make()->result($fixture['token'])->outcome)->toBe(PublicSectionFragmentOutcome::Unavailable);
})->with([
    'detached' => fn (array $fixture) => $fixture['attachment']->delete(),
    'draft' => fn (array $fixture) => $fixture['section']->update(['visible_from' => PublishSentinel::draftValue()]),
    'translation changed' => fn (array $fixture) => $fixture['section']->translation->update(['title' => 'Revised title']),
    'draft page' => fn (array $fixture) => $fixture['page']->update(['visible_from' => PublishSentinel::draftValue()]),
    'disabled widget' => fn (array $fixture) => $fixture['widget']->update(['status' => false]),
]);

it('rejects request-varying dynamic section contributors before shared caching', function (): void {
    $fixture = sharedSectionFragmentFixture();
    resolve(RenderableDynamicDataRegistry::class)->register('section', 'shared-test', fn () => ['private' => 'visitor state']);

    expect(RenderPublicSectionFragmentAction::make()->result($fixture['token'])->outcome)->toBe(PublicSectionFragmentOutcome::Unavailable);
});

it('rejects authoring output without storing it as a shared fragment', function (): void {
    $fixture = sharedSectionFragmentFixture();
    $fixture['state']->html = '<div data-capell-model-id="42">Editor</div>';
    expect(RenderPublicSectionFragmentAction::make()->result($fixture['token'])->outcome)->toBe(PublicSectionFragmentOutcome::AuthoringSurfaceRejected);
    expect(resolve(FragmentCache::class)->remember(ResolvePublicFragmentCacheIdentityAction::run($fixture['reference']), fn () => 'not cached'))->toBe('not cached');
});

it('renders block media in a shared fragment from hydrated data, never from raw meta', function (): void {
    Storage::fake('public');
    config()->set('filesystems.disks.public.visibility', 'public');
    $uuid = null;
    $fixture = sharedSectionFragmentFixture(function (Section $section) use (&$uuid): void {
        $added = $section->addMedia(UploadedFile::fake()->image('acme-mark.png', 40, 40))->toMediaCollection(Section::BLOCK_MEDIA_COLLECTION);
        $uuid = $added->uuid;
        $section->update(['meta' => ['kind' => 'logos-media', 'columns' => '4', 'logos' => [
            ['name' => 'Acme', 'url' => 'https://acme.example', 'media' => $uuid, 'image' => ['url' => 'https://evil.example/tracker.png', 'alt' => 'raw']],
            ['name' => 'Globex', 'url' => 'https://globex.example', 'image' => ['url' => 'https://evil.example/other.png']],
        ]]]);
    }, 'logos-media');

    $html = RenderPublicSectionFragmentAction::make()->result($fixture['token']);

    expect($html->outcome)->toBe(PublicSectionFragmentOutcome::Rendered)
        ->and($html->html)->toContain('acme-mark.png')
        ->and($html->html)->toContain('Globex')
        ->and($html->html)->not->toContain('evil.example')
        ->and($html->html)->not->toContain((string) $uuid);
});

it('sanitises section meta in a shared fragment exactly as the inline renderer does', function (): void {
    $fixture = sharedSectionFragmentFixture(function (Section $section): void {
        $section->update(['meta' => ['kind' => 'logos-media', 'columns' => '4', 'logos' => [
            ['name' => 'Acme', 'url' => 'javascript:alert(document.cookie)'],
            ['name' => 'Globex', 'url' => ' JaVaScRiPt:alert(1)'],
            ['name' => 'Initech', 'url' => 'https://initech.example'],
        ]]]);
    }, 'logos-media');

    $result = RenderPublicSectionFragmentAction::make()->result($fixture['token']);

    expect($result->outcome)->toBe(PublicSectionFragmentOutcome::Rendered)
        ->and($result->html)->toContain('Acme')
        ->and($result->html)->toContain('https://initech.example')
        ->and(strtolower((string) $result->html))->not->toContain('javascript:');
});

/** @return array{language: Language, site: Site, widget: Widget, layout: Layout, page: Page, section: Section, attachment: WidgetAsset, reference: PublicFragmentReferenceData, token: string, state: PublicSectionFragmentState} */
it('renders a placement\'s per-page overrides in the fragment exactly as the inline renderer does', function (): void {
    $fixture = sharedSectionFragmentFixture(fn (Section $section) => $section->update(['meta' => ['kind' => 'logos-media', 'logos' => [['name' => 'Section Co']]]]), 'logos-media');
    $fixture['attachment']->update(['meta' => ['logos' => [['name' => 'Placement Co']]]]);
    $base = $fixture['reference'];
    $widgetAssetId = $fixture['attachment']->id;
    $ownerContext = [...$base->ownerContext, 'widgetAssetId' => (int) $widgetAssetId, 'placementVersion' => hash('sha256', json_encode(['logos' => [['name' => 'Placement Co']]], JSON_THROW_ON_ERROR))];
    $reference = new PublicFragmentReferenceData(owner: $base->owner, formatVersion: 1, pageableType: $base->pageableType, pageableId: $base->pageableId, siteId: $base->siteId, languageId: $base->languageId, contentVersion: ResolvePublicFragmentContentVersionAction::run($fixture['page'], $fixture['site'], $fixture['language'], $fixture['layout'], $ownerContext), ownerContext: $ownerContext);
    $token = resolve(PublicFragmentReferenceCodec::class)->encode($reference);

    $result = RenderPublicSectionFragmentAction::make()->result($token);

    expect($result->outcome)->toBe(PublicSectionFragmentOutcome::Rendered)
        ->and($result->html)->toContain('Placement Co')
        ->and($result->html)->not->toContain('Section Co');

    $fixture['attachment']->update(['meta' => ['logos' => [['name' => 'Edited Co']]]]);
    expect(RenderPublicSectionFragmentAction::make()->result($token)->outcome)->toBe(PublicSectionFragmentOutcome::Unavailable);
});

it('throttles the anonymous fragment endpoint per client', function (): void {
    config()->set('capell-content-sections.fragments.rate_limit_per_minute', 2);
    $url = route('capell-content-sections.fragments.show', ['reference' => 'invalid'], false);

    $this->get($url)->assertNotFound();
    $this->get($url)->assertNotFound();
    $this->get($url)->assertStatus(429);
});

it('evicts a cached fragment when its section visibility window closes', function (): void {
    $fixture = sharedSectionFragmentFixture(fn (Section $section) => $section->update(['visible_until' => now()->addHour()]));
    $fixture['state']->html = '<section>Promo</section>';
    expect(RenderPublicSectionFragmentAction::run($fixture['token']))->toBe('<section>Promo</section>');
    $identity = ResolvePublicFragmentCacheIdentityAction::run($fixture['reference']);
    expect(resolve(FragmentCache::class)->remember($identity, fn () => 'miss'))->toBe('<section>Promo</section>');

    Section::query()->whereKey($fixture['section']->getKey())->toBase()->update(['visible_until' => now()->subSeconds(30)]);
    InvalidateDueScheduledSectionCachesAction::run();

    expect(resolve(FragmentCache::class)->remember($identity, fn () => 'miss'))->toBe('miss');
});

/** @return array{language: Language, site: Site, widget: Widget, layout: Layout, page: Page, section: Section, attachment: WidgetAsset, reference: PublicFragmentReferenceData, token: string, state: PublicSectionFragmentState} */
function sharedSectionFragmentFixture(?Closure $prepare = null, string $kind = 'shared-test'): array
{
    $state = new PublicSectionFragmentState;
    app('view')->addNamespace('shared-section-fixture', __DIR__ . '/../../Fixtures/views');
    app('view')->composer('shared-section-fixture::section', function ($view) use ($state): void {
        $state->renders++;
        $view->with('html', $state->html);
    });
    $language = Language::factory()->create(['status' => true]);
    $site = Site::factory()->language($language)->withTranslations($language)->create(['status' => true]);
    $widget = Widget::factory()->create(['key' => 'shared-section-widget', 'status' => true]);
    TranslationFactory::new()->translatable($widget)->language($language)->create(['title' => 'Widget']);
    $layout = Layout::factory()->site($site)->create(['status' => true, 'containers' => ['main' => ['widgets' => [['widget_key' => $widget->key, 'occurrence' => 1]]]]]);
    $page = Page::factory()->site($site)->layout($layout)->withTranslations($language)->create(['visible_from' => now()->subDay(), 'visible_until' => null]);
    $section = Section::factory()->site($site)->withTranslations($language)->create(['visible_from' => now()->subDay(), 'visible_until' => null, 'workspace_id' => 0, 'meta' => ['kind' => $kind]]);
    if ($prepare instanceof Closure) {
        $prepare($section);
    }
    $section->refresh()->load(['translation' => fn ($query) => $query->where('language_id', $language->getKey())]);
    $attachment = WidgetAsset::query()->create(['widget_id' => $widget->getKey(), 'pageable_type' => $page->getMorphClass(), 'pageable_id' => $page->getKey(), 'container' => 'main', 'occurrence' => 1, 'workspace_id' => 0, 'asset_type' => $section->getMorphClass(), 'asset_id' => $section->getKey()]);
    resolve(RenderableRegistry::class)->register(new RenderableDefinitionData(key: 'shared-test', type: 'section', blade: 'shared-section-fixture::section'));
    resolve(RenderableRegistry::class)->register(new RenderableDefinitionData(key: 'logos-media', type: 'section', blade: 'capell-block-library::blocks.catalog.logos'));
    $ownerContext = ['layoutId' => $layout->getKey(), 'assetType' => $section->getMorphClass(), 'assetId' => $section->getKey(), 'assetVersion' => ResolvePublicFragmentAssetVersionAction::run($section)];
    $reference = new PublicFragmentReferenceData(owner: ContentSectionsFragmentUrlResolver::OWNER, formatVersion: 1, pageableType: $page->getMorphClass(), pageableId: $page->getKey(), siteId: $site->getKey(), languageId: $language->getKey(), contentVersion: ResolvePublicFragmentContentVersionAction::run($page, $site, $language, $layout, $ownerContext), ownerContext: $ownerContext);
    $token = resolve(PublicFragmentReferenceCodec::class)->encode($reference);

    return compact('language', 'site', 'widget', 'layout', 'page', 'section', 'attachment', 'reference', 'token', 'state');
}

it('refreshes a cached fragment when media attached to its section changes', function (string $change): void {
    Storage::fake('public');
    config()->set('filesystems.disks.public.visibility', 'public');
    $mediaId = null;
    $fixture = sharedSectionFragmentFixture(function (Section $section) use (&$mediaId): void {
        $added = $section->addMedia(UploadedFile::fake()->image('acme-mark.png', 40, 40))->toMediaCollection(Section::BLOCK_MEDIA_COLLECTION);
        $mediaId = $added->getKey();
        Translation::factory()->translatable($added)->create(['title' => 'x', 'meta' => ['alt' => 'Old alt']]);
        $section->update(['meta' => ['kind' => 'logos-media', 'logos' => [['name' => 'Acme', 'media' => $added->uuid]]]]);
    }, 'logos-media');

    expect(RenderPublicSectionFragmentAction::run($fixture['token']))->toContain('alt="Old alt"');

    $media = Media::query()->whereKey($mediaId)->firstOrFail();
    match ($change) {
        'alt' => $media->translations()->firstOrFail()->update(['meta' => ['alt' => 'New alt']]),
        'decorative' => $media->translations()->firstOrFail()->update(['meta' => ['decorative' => true]]),
        'deleted' => $media->delete(),
        'restored' => (function () use ($media, $fixture): void {
            $media->delete();
            expect((string) RenderPublicSectionFragmentAction::run($fixture['token']))->not->toContain('<img');
            $media->restore();
        })(),
        default => throw new LogicException('Unknown change.'),
    };

    $html = (string) RenderPublicSectionFragmentAction::run($fixture['token']);

    match ($change) {
        'alt' => expect($html)->toContain('alt="New alt"'),
        // The logo has no link, so a decorative image carries the logo's name: nothing else on it names the company.
        'decorative' => expect($html)->toContain('alt="Acme"')->not->toContain('Old alt'),
        'deleted' => expect($html)->not->toContain('<img'),
        default => expect($html)->toContain('<img'),
    };
})->with(['alt', 'decorative', 'deleted', 'restored']);

it('derives the placement version token as a SHA-256 digest of the placement metadata', function (): void {
    // Pinned literal: the issuer ships in layout-builder and this verifier must agree with it.
    expect(hash('sha256', json_encode(['logos' => [['name' => 'Placement Co']]], JSON_THROW_ON_ERROR)))
        ->toBe('f2c577383e915214a5b7f687e73ec6bfbe9e331dff17684fd3e87641c382b109');
});
