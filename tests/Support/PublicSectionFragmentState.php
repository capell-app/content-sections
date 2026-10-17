<?php

declare(strict_types=1);

namespace Capell\ContentSections\Tests\Support;

final class PublicSectionFragmentState
{
    public int $renders = 0;

    public string $html = '<section>Shared section</section>';
}
