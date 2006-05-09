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
