<?php

declare(strict_types=1);

namespace Capell\ContentSections\Console\Commands;

use Capell\ContentSections\Actions\InvalidateDueScheduledSectionCachesAction;
use Capell\ContentSections\Providers\ContentSectionsServiceProvider;
use Capell\Core\Facades\CapellCore;
use Illuminate\Console\Command;

final class InvalidateDueScheduledSectionCachesCommand extends Command
{
    protected $signature = 'content-sections:invalidate-due-caches';

    protected $description = 'Refresh cached pages and fragments for sections whose visibility window has just opened or closed.';

    public function handle(): int
    {
        if (! CapellCore::isPackageEnabled(ContentSectionsServiceProvider::$packageName)) {
            return self::SUCCESS;
        }

        $count = InvalidateDueScheduledSectionCachesAction::run();
        $this->components->info(sprintf('Refreshed caches for %d scheduled section%s.', $count, $count === 1 ? '' : 's'));

        return self::SUCCESS;
    }
}
