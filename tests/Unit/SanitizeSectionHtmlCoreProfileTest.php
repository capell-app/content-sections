<?php

declare(strict_types=1);

use Capell\ContentSections\Actions\SanitizeSectionHtmlAction;
use Capell\Core\Contracts\Security\SanitizesPublicHtml;
use Capell\Core\Enums\HtmlSanitizerProfile;

it('sanitises through Core under the rich text profile', function (): void {
    $sanitizer = new class implements SanitizesPublicHtml
    {
        /** @var list<HtmlSanitizerProfile> */
        public array $profiles = [];

        public function sanitize(string $html, HtmlSanitizerProfile $profile = HtmlSanitizerProfile::Strict): string
        {
            $this->profiles[] = $profile;

            return '<p>core</p>';
        }
    };
    app()->instance(SanitizesPublicHtml::class, $sanitizer);

    expect(SanitizeSectionHtmlAction::run('<p onclick="x()">Hi</p>'))->toBe('<p>core</p>')
        ->and($sanitizer->profiles)->toBe([HtmlSanitizerProfile::RichText]);
});
