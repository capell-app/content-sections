<?php

declare(strict_types=1);

namespace Capell\ContentSections\Support;

use Capell\Core\Support\Http\PublicEndpointThrottle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Bounds anonymous fragment token decoding and section lookups per client, as declared in
 * capell.json `security.throttles`. A page issues a handful of fragments, so the default is
 * generous. The limit is read from configuration each time a request is throttled.
 */
final class SectionFragmentThrottle
{
    public const string PURPOSE = 'capell-content-sections-fragment';

    public static function register(): void
    {
        RateLimiter::for(self::PURPOSE, static fn (Request $request): array => self::throttle()->limitsFor($request));
    }

    public static function throttle(): PublicEndpointThrottle
    {
        $configured = config('capell-content-sections.fragments.rate_limit_per_minute', 120);

        return PublicEndpointThrottle::for(self::PURPOSE)
            ->allow(is_numeric($configured) ? max(1, (int) $configured) : 120)
            ->perSeconds(60)
            ->ignoringActor();
    }
}
