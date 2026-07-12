<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\Core\Models\Language;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Returns the id of the default language, used to scope section-asset
 * translations when no language filter is active.
 *
 * @method static ?int run()
 */
class GetDefaultLanguageIdAction
{
    use AsObject;

    public function handle(): ?int
    {
        /** @var class-string<Language> $model */
        $model = Language::class;

        $id = $model::query()->default()->value('id');

        return is_numeric($id) ? (int) $id : null;
    }
}
