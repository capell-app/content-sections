<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Models\Section;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Section run(Section $content, array<array-key, mixed> $data = [])
 */
class ReplicateContentAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function handle(Section $content, array $data = []): Section
    {
        $content->load('translations');

        // Supplied translations replace the original's; without them the copy
        // starts with its own independent copy of every original translation.
        $suppliedTranslations = array_key_exists('translations', $data);
        $translations = $suppliedTranslations && is_array($data['translations']) ? $data['translations'] : [];
        unset($data['translations']);

        $className = $content::class;

        $model = $className::query()
            ->whereKey($content->getKey())
            ->firstOrFail();

        $model->fill($data);

        return DB::transaction(function () use ($model, $content, $translations, $suppliedTranslations): Section {
            $replica = $model->replicate();

            $replica->created_at = CarbonImmutable::now();
            $replica->updated_at = CarbonImmutable::now();

            $replica->save();

            if ($suppliedTranslations) {
                foreach ($translations as $translation) {
                    $replica->translations()->create($translation);
                }
            } else {
                foreach ($content->translations as $translation) {
                    $replica->translations()->save($translation->replicate());
                }
            }

            $replica->load('translations');

            // Last, so a failure here cannot leave copied files behind a rolled back replica.
            CopySectionMediaAction::run($content, $replica);

            return $replica;
        });
    }
}
