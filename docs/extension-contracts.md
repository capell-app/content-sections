# Worked extension examples

These developer-facing recipes are kept beside the package contract. Replace the example values with the site-specific records and data objects used by the calling workflow.

<!-- example: contract Capell\ContentSections\Contracts\SectionDefinitionProvider -->

```php
<?php
declare(strict_types=1);
final class ExampleSectionDefinitionProviderImplementation implements \Capell\ContentSections\Contracts\SectionDefinitionProvider
{
    /**
     * @return iterable<SectionDefinitionData>
     */
    public function definitions(): iterable
    {
        throw new LogicException('Implement this package contract for the calling site.');
    }
}

app()->bind(\Capell\ContentSections\Contracts\SectionDefinitionProvider::class, ExampleSectionDefinitionProviderImplementation::class);
```

## Public deferred sections

Set a section's `meta.performance.defer` to `true` and `fragment_owner` to
`content-sections`. Layout Builder emits an encrypted, versioned reference through
the Frontend fragment registry. `capell-content-sections.fragments.show` validates
the page, site, language, public widget attachment, section publication and raw
asset/translation version before rendering. It uses the existing renderable and
dynamic-data registries, rejects unsafe dynamic contributors and authoring HTML,
and caches safe output with Frontend's surrogate-aware `FragmentCache`.

Dynamic-data contributors must explicitly declare cache safety using the final
`RenderableDynamicDataRegistry::register()` argument, either `true` or a predicate
for the exact public subtypes. Unknown safety is rejected. Preview audiences always render inline, including draft and scheduled pages,
so they never depend on a public capability endpoint. An unsafe section is
rendered inline by Layout Builder instead of receiving a shared fragment URL.

`capell-content-sections.fragments.owner_aliases` accepts explicit legacy owners
for existing encrypted references; applications retain their old route as a thin
adapter if previously cached shells still point to it. `cache_seconds` defaults
to 1800. Invalid or unavailable fragments return a bare 404; crashed renders a
bare 500, with details confined to server logs.
