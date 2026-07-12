<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Lorisleiva\Actions\Concerns\AsObject;

final class NormalizeSectionIconAction
{
    use AsObject;

    public function handle(mixed $icon): ?string
    {
        if (! is_string($icon)) {
            return null;
        }

        $icon = trim($icon);

        if ($icon === '') {
            return null;
        }

        return preg_match('/^heroicon-[oms]-[a-z0-9-]+$/', $icon) === 1 ? $icon : null;
    }
}
