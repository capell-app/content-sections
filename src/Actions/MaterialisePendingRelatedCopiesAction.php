<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Models\Section;
use Capell\ContentSections\Support\SectionSiteScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

/**
 * Turns the related items an editor asked to copy into real sections while the parent is being saved.
 *
 * Runs inside the parent save's transaction, so the copies commit or roll back with the parent. The
 * source is looked up again within the actor's site scope and the replicate permission is checked
 * again at this moment, since either may have changed after the editor clicked copy. Copied media
 * files are not covered by the transaction, so they are deleted if it rolls back.
 *
 * @method static array<array-key, int> run(array<array-key, int> $pending, string $errorKey)
 */
final class MaterialisePendingRelatedCopiesAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<array-key, int>  $pending  repeater item key => source section id
     * @return array<array-key, int> repeater item key => copied section id
     */
    public function handle(array $pending, string $errorKey): array
    {
        if ($pending === []) {
            return [];
        }

        $materialise = fn (): array => $this->materialise($pending, $errorKey);

        // Without a surrounding save transaction the copies still succeed or fail together.
        return DB::transactionLevel() > 0 ? $materialise() : DB::transaction($materialise);
    }

    /**
     * @param  array<array-key, int>  $pending
     * @return array<array-key, int>
     */
    private function materialise(array $pending, string $errorKey): array
    {
        $copies = [];

        foreach ($pending as $key => $sourceId) {
            $source = Section::query()
                ->with(['blueprint'])
                ->tap(fn (Builder $query): Builder => SectionSiteScope::applyForCurrentActor($query, 'sections.site_id'))
                ->find($sourceId);

            if (! $source instanceof Section) {
                throw ValidationException::withMessages([
                    $errorKey => __('capell-content-sections::message.content_not_accessible'),
                ]);
            }

            Gate::authorize('replicate', $source);

            $copy = ReplicateContentAction::run($source);

            $this->deleteFilesOnRollback($copy);

            $copies[$key] = $copy->id;
        }

        return $copies;
    }

    private function deleteFilesOnRollback(Section $copy): void
    {
        /** @var list<Media> $media */
        $media = $copy->media()->get()->all();

        if ($media === []) {
            return;
        }

        DB::afterRollBack(static function () use ($media): void {
            foreach ($media as $item) {
                $generator = PathGeneratorFactory::create($item);

                Storage::disk($item->disk)->deleteDirectory($generator->getPath($item));

                $conversionsDisk = $item->conversions_disk ?: $item->disk;

                Storage::disk($conversionsDisk)->deleteDirectory($generator->getPathForConversions($item));
                Storage::disk($conversionsDisk)->deleteDirectory($generator->getPathForResponsiveImages($item));
            }
        });
    }
}
