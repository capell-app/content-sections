<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions\Fragments;

use Capell\ContentSections\Actions\PreparePublicSectionMetaAction;
use Capell\ContentSections\Data\PublicSectionFragmentResultData;
use Capell\ContentSections\Enums\PublicSectionFragmentOutcome;
use Capell\ContentSections\Fragments\ContentSectionsFragmentUrlResolver;
use Capell\ContentSections\Fragments\SectionFragmentSurrogateKey;
use Capell\ContentSections\Models\Section;
use Capell\Core\Enums\PublishVisibilityStateEnum;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Theme;
use Capell\Core\Support\Renderables\RenderableRegistry;
use Capell\Frontend\Actions\AssertPublicHtmlContainsNoAuthoringSurfaceAction;
use Capell\Frontend\Actions\Fragments\ResolvePublicFragmentAssetVersionAction;
use Capell\Frontend\Actions\Fragments\ResolvePublicFragmentCacheIdentityAction;
use Capell\Frontend\Actions\Fragments\ResolvePublicFragmentContextAction;
use Capell\Frontend\Actions\RenderRenderableAction;
use Capell\Frontend\Contracts\Fragments\PublicFragmentReferenceCodec;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Frontend\Data\Fragments\PublicFragmentContextData;
use Capell\Frontend\Data\Fragments\PublicFragmentReferenceData;
use Capell\Frontend\Exceptions\PublicFragmentReferenceInvalid;
use Capell\Frontend\Exceptions\PublicRenderContractViolationException;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\Cache\FragmentCache;
use Capell\Frontend\Support\Renderables\RenderableDynamicDataRegistry;
use Capell\Frontend\Support\State\FrontendState;
use Capell\LayoutBuilder\Contracts\PublicLayoutAssetMembership;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

final class RenderPublicSectionFragmentAction
{
    use AsFake;
    use AsObject;

    public function __construct(private readonly PublicLayoutAssetMembership $membership) {}

    public function handle(string $reference): ?string
    {
        return $this->result($reference)->html;
    }

    public function result(string $token): PublicSectionFragmentResultData
    {
        try {
            $reference = resolve(PublicFragmentReferenceCodec::class)->decode($token);
            $aliases = config('capell-content-sections.fragments.owner_aliases', []);
            if ($reference->owner !== ContentSectionsFragmentUrlResolver::OWNER && ! in_array($reference->owner, is_array($aliases) ? $aliases : [], true)) {
                return new PublicSectionFragmentResultData(PublicSectionFragmentOutcome::Unavailable);
            }

            return $this->render($reference);
        } catch (PublicFragmentReferenceInvalid|ModelNotFoundException) {
            return new PublicSectionFragmentResultData(PublicSectionFragmentOutcome::Unavailable);
        } catch (PublicRenderContractViolationException) {
            Log::warning('Public section fragment rejected an authoring surface.');

            return new PublicSectionFragmentResultData(PublicSectionFragmentOutcome::AuthoringSurfaceRejected);
        } catch (Throwable $throwable) {
            // Never log the encrypted token or raw owner context.
            Log::error('Public section fragment failed to render.', ['exception' => $throwable]);

            return new PublicSectionFragmentResultData(PublicSectionFragmentOutcome::RenderFailed);
        }
    }

    private function render(PublicFragmentReferenceData $reference): PublicSectionFragmentResultData
    {
        $context = ResolvePublicFragmentContextAction::run($reference);
        $page = $context->page;
        if (! $page instanceof Page) {
            return new PublicSectionFragmentResultData(PublicSectionFragmentOutcome::Unavailable);
        }
        $layout = $page->getRelationValue('layout');
        $modelClass = is_string($reference->ownerContext['assetType'] ?? null) ? Relation::getMorphedModel($reference->ownerContext['assetType']) : null;
        $assetId = $reference->ownerContext['assetId'] ?? null;
        if (! $layout instanceof Layout || ! is_string($modelClass) || ! is_a($modelClass, Section::class, true) || (! is_int($assetId) && ! is_string($assetId))) {
            return new PublicSectionFragmentResultData(PublicSectionFragmentOutcome::Unavailable);
        }
        $section = $modelClass::query()->with(['translation' => fn ($query) => $query->where('language_id', $context->language->getKey())->where('workspace_id', 0)])->whereKey($assetId)->first();
        $translation = $section?->getRelationValue('translation');
        $version = $reference->ownerContext['assetVersion'] ?? null;
        if (! $section instanceof Section || ! $translation instanceof Model || $section->publishVisibilityState() !== PublishVisibilityStateEnum::published || (int) $section->workspace_id !== 0 || ($section->site_id !== null && (string) $section->site_id !== (string) $this->modelKey($context->site)) || ! is_string($version) || ! hash_equals($version, ResolvePublicFragmentAssetVersionAction::run($section))) {
            return new PublicSectionFragmentResultData(PublicSectionFragmentOutcome::Unavailable);
        }
        // Fragments share the inline renderer's preparation: sanitised HTML, allowlisted URL
        // schemes, normalised icons, and hydrated media (never a raw `image` value or media uuid).
        $placement = $this->placementMeta($reference, $section, $page);
        if ($placement === false) {
            return new PublicSectionFragmentResultData(PublicSectionFragmentOutcome::Unavailable);
        }
        $meta = PreparePublicSectionMetaAction::run($section, array_replace_recursive(is_array($section->meta) ? $section->meta : [], $placement), $context->language);
        $kind = is_string($meta['kind'] ?? null) && $meta['kind'] !== '' ? $meta['kind'] : 'section';
        $dynamic = resolve(RenderableDynamicDataRegistry::class);
        $definition = resolve(RenderableRegistry::class)->get('section', $kind);
        if ($definition->contribution?->cacheSafe === false || ! $dynamic->isCacheSafe('section', $kind, $section, $translation, $meta)) {
            return new PublicSectionFragmentResultData(PublicSectionFragmentOutcome::Unavailable);
        }
        $previous = app()->resolved(FrontendContextReader::class) ? resolve(FrontendContextReader::class) : null;
        try {
            $this->bindContext($context, $layout, $page);
            if (! $this->membership->contains($section, $page, $layout, $context->language)) {
                return new PublicSectionFragmentResultData(PublicSectionFragmentOutcome::Unavailable);
            }
            $configuredTtl = config('capell-content-sections.fragments.cache_seconds', 1800);
            $ttl = is_int($configuredTtl) || (is_string($configuredTtl) && is_numeric($configuredTtl)) ? max(1, (int) $configuredTtl) : 1800;
            $html = resolve(FragmentCache::class)->remember(
                ResolvePublicFragmentCacheIdentityAction::run($reference),
                function () use ($section, $translation, $meta, $kind, $dynamic): string {
                    $html = RenderRenderableAction::run(type: 'section', key: $kind, asset: $section, translation: $translation, meta: $meta, dynamicData: $dynamic->data('section', $kind, $section, $translation, $meta));
                    AssertPublicHtmlContainsNoAuthoringSurfaceAction::run(new Response($html, headers: ['Content-Type' => 'text/html; charset=UTF-8']));

                    return $html;
                },
                ttlSeconds: $ttl,
                surrogateKeys: ['site-' . $this->modelKey($context->site), 'page-' . $this->modelKey($page), SectionFragmentSurrogateKey::for($section), 'translation-' . $this->modelKey($translation), 'layout-' . $this->modelKey($layout)],
            );
            if (! is_string($html) || trim($html) === '') {
                return new PublicSectionFragmentResultData(PublicSectionFragmentOutcome::Unavailable);
            }
            AssertPublicHtmlContainsNoAuthoringSurfaceAction::run(new Response($html, headers: ['Content-Type' => 'text/html; charset=UTF-8']));

            return new PublicSectionFragmentResultData(PublicSectionFragmentOutcome::Rendered, $html);
        } finally {
            Frontend::clearResolvedInstance(FrontendContextReader::class);
            if ($previous instanceof FrontendContextReader) {
                app()->instance(FrontendContextReader::class, $previous);
            } else {
                app()->forgetInstance(FrontendContextReader::class);
            }
        }
    }

    /**
     * The per-page overrides the inline renderer merges over the section's own meta. A reference that names
     * a placement must still match a live placement of this section on this page, with unchanged overrides.
     *
     * @return array<string, mixed>|false false when the reference's placement is gone or has changed
     */
    private function placementMeta(PublicFragmentReferenceData $reference, Section $section, Page $page): array|false
    {
        $id = $reference->ownerContext['widgetAssetId'] ?? null;
        if ($id === null) {
            return [];
        }
        $version = $reference->ownerContext['placementVersion'] ?? null;
        if ((! is_int($id) && ! is_string($id)) || ! is_string($version)) {
            return false;
        }
        $placement = WidgetAsset::query()->whereKey($id)
            ->where('asset_type', $section->getMorphClass())
            ->where('asset_id', $section->getKey())
            ->where('pageable_type', $page->getMorphClass())
            ->where('pageable_id', $page->getKey())
            ->where('workspace_id', 0)
            ->first();
        $meta = $placement instanceof WidgetAsset && is_array($placement->meta) ? $placement->meta : [];
        // Must match the token layout-builder issues byte for byte (see LayoutBuilderPublicWidgetAssetsRenderer): it
        // sits in cached public HTML and the two packages release independently.
        if ($meta === [] || ! hash_equals($version, hash('sha256', json_encode($meta, JSON_THROW_ON_ERROR)))) {
            return false;
        }

        return $meta;
    }

    private function modelKey(Model $model): int|string
    {
        $key = $model->getKey();
        if (! is_int($key) && ! is_string($key)) {
            throw new PublicFragmentReferenceInvalid;
        }

        return $key;
    }

    private function bindContext(PublicFragmentContextData $context, Layout $layout, Page $page): void
    {
        $site = $context->site;
        $site->loadMissing('theme');
        $layout->loadMissing('theme');
        $theme = $site->theme instanceof Theme ? $site->theme : $layout->theme;
        $state = (new FrontendState)->withSite($site)->withLanguage($context->language)->withPage($page)->withLayout($layout);
        if ($theme instanceof Theme) {
            $state->withTheme($theme);
        }
        Frontend::clearResolvedInstance(FrontendContextReader::class);
        app()->instance(FrontendContextReader::class, $state);
    }
}
