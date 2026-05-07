<?php

declare(strict_types=1);

it('spreads attributes onto dynamic section components', function (): void {
    $asset = new class
    {
        public object $translation;

        /** @var array<string, mixed> */
        public array $meta = [];

        public ?object $linkedPage = null;

        public function __construct()
        {
            $this->translation = (object) [
                'label' => 'Alice Johnson',
                'summary' => 'Leads the product team.',
            ];
        }

        public function getMeta(string $key, mixed $default = null): mixed
        {
            return [
                'color' => 'blue',
                'icon' => null,
            ][$key] ?? $default;
        }
    };

    $this->blade(
        <<<'BLADE'
        <x-capell-content-sections::section.asset
            :asset="$asset"
            component-item="section.block"
            :loop="$loop"
            :class="$class"
        />
        BLADE,
        [
            'asset' => $asset,
            'class' => ['widget-block-item'],
            'loop' => (object) ['index' => 0],
        ],
    )
        ->assertSee('Alice Johnson')
        ->assertSee('section-asset')
        ->assertSee('widget-block-item');
});
