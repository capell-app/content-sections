<?php

declare(strict_types=1);

namespace Capell\ContentSections\Http\Controllers;

use Capell\ContentSections\Actions\Fragments\RenderPublicSectionFragmentAction;
use Capell\ContentSections\Enums\PublicSectionFragmentOutcome;
use Illuminate\Http\Response;

final class PublicSectionFragmentController
{
    public function __invoke(string $reference): Response
    {
        $result = RenderPublicSectionFragmentAction::make()->result($reference);
        if ($result->outcome !== PublicSectionFragmentOutcome::Rendered) {
            return response('', $result->outcome->httpStatus());
        }

        return response($result->html)->header('Content-Type', 'text/html; charset=UTF-8')->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')->header('X-Robots-Tag', 'noindex');
    }
}
