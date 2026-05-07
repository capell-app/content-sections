# Capell Content Sections

Reusable content sections for Capell admin and frontend surfaces.

This package provides the standalone scaffold for extracting section models, resources, and rendering integrations from LayoutBuilder in later tasks.

## Frontend section rendering

Content Sections registers neutral frontend component keys for section assets:

- `section.block`
- `section.team-member`

Saved section and widget asset data should use those keys instead of package Blade namespaces. At render time, `FrontendComponentRegistryInterface` resolves the key to the active Blade component. LayoutBuilder and theme packages can override the same key while keeping old names as aliases, so existing saved content continues to render.

```php
use Capell\Frontend\Contracts\FrontendComponentRegistryInterface;

$this->callAfterResolving(
    FrontendComponentRegistryInterface::class,
    fn (FrontendComponentRegistryInterface $registry): FrontendComponentRegistryInterface => $registry
        ->register(
            key: 'section.block',
            component: 'capell-example-theme::section.block',
            aliases: [
                'capell-content-sections::section.block',
                'capell-layout-builder::section.block',
            ],
        ),
);
```

New packages should add stable keys for new section families and keep the Blade namespace as an implementation detail.
