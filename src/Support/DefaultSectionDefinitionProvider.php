<?php

declare(strict_types=1);

namespace Capell\ContentSections\Support;

use Capell\Admin\Contracts\ConfiguratorInterface;
use Capell\BlockLibrary\Support\DefaultBlockCatalog;
use Capell\ContentSections\Contracts\SectionDefinitionProvider;
use Capell\ContentSections\Data\SectionDefinitionData;
use Capell\ContentSections\Enums\SectionConfiguratorEnum;

class DefaultSectionDefinitionProvider implements SectionDefinitionProvider
{
    /**
     * @return iterable<SectionDefinitionData>
     */
    public function definitions(): iterable
    {
        return [
            $this->definition('content', SectionConfiguratorEnum::Default->value),
            $this->definition('hero', SectionConfiguratorEnum::Hero->value),
            $this->definition('testimonial', SectionConfiguratorEnum::Testimonial->value),
            $this->definition('accordion', SectionConfiguratorEnum::Accordion->value),
            $this->definition('call_to_action', SectionConfiguratorEnum::CallToAction->value),
            $this->definition('comparison', SectionConfiguratorEnum::Comparison->value),
            $this->definition('counter', SectionConfiguratorEnum::Counter->value),
            $this->definition('divider', SectionConfiguratorEnum::Divider->value),
            $this->definition('faq', SectionConfiguratorEnum::Faq->value),
            $this->definition('features', SectionConfiguratorEnum::Features->value),
            $this->definition('logos', SectionConfiguratorEnum::Logos->value),
            $this->definition('pricing', SectionConfiguratorEnum::Pricing->value),
            $this->definition('stats', SectionConfiguratorEnum::Stats->value),
            $this->definition('table', SectionConfiguratorEnum::Table->value),
            $this->definition('tabs', SectionConfiguratorEnum::Tabs->value),
            $this->definition('team', SectionConfiguratorEnum::Team->value),
            $this->definition('timeline', SectionConfiguratorEnum::Timeline->value),
        ];
    }

    private function definition(string $key, string $configurator): SectionDefinitionData
    {
        /** @var class-string<ConfiguratorInterface> $configurator */
        return new SectionDefinitionData(
            key: $key,
            label: __('capell-content-sections::section.' . $key . '.label'),
            description: __('capell-content-sections::section.' . $key . '.description'),
            // One icon map for the section picker and the builder picker.
            icon: DefaultBlockCatalog::icon($key),
            group: 'main',
            configurator: $configurator,
            component: 'capell-block-library::blocks.catalog.' . str_replace('_', '-', $key),
            defaults: [],
        );
    }
}
