<?php

declare(strict_types=1);

namespace Capell\ContentSections\Support;

use Capell\Admin\Contracts\ConfiguratorInterface;
use Capell\ContentSections\Data\SectionDefinitionData;
use InvalidArgumentException;

class SectionRegistry
{
    /**
     * @var array<string, SectionDefinitionData>
     */
    private array $widgets = [];

    /**
     * @var array<string, string>
     */
    private array $configuratorIndex = [];

    public function register(SectionDefinitionData $widget): void
    {
        if (isset($this->widgets[$widget->key])) {
            throw new InvalidArgumentException(sprintf('Section [%s] is already registered.', $widget->key));
        }

        $this->widgets[$widget->key] = $widget;
        $this->configuratorIndex[$this->normalizeConfigurator($widget->configurator)] = $widget->key;

        if (is_subclass_of($widget->configurator, ConfiguratorInterface::class)) {
            $this->configuratorIndex[$this->normalizeConfigurator($widget->configurator::getKey())] = $widget->key;
        }
    }

    /**
     * @return array<string, SectionDefinitionData>
     */
    public function all(): array
    {
        return $this->widgets;
    }

    public function get(string $key): ?SectionDefinitionData
    {
        return $this->widgets[$key] ?? null;
    }

    public function getByConfigurator(string $configurator): ?SectionDefinitionData
    {
        $key = $this->configuratorIndex[$this->normalizeConfigurator($configurator)] ?? null;

        return $key !== null ? $this->get($key) : null;
    }

    private function normalizeConfigurator(string $configurator): string
    {
        return ltrim($configurator, '\\');
    }
}
