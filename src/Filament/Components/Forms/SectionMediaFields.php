<?php

declare(strict_types=1);

namespace Capell\ContentSections\Filament\Components\Forms;

use Capell\Admin\Actions\GetMaxUploadSizeInBytes;
use Capell\Admin\Filament\Components\Forms\MediaLibraryFileUpload;
use Capell\ContentSections\Actions\ResolveSectionMediaAction;
use Capell\ContentSections\Data\SectionMediaData;
use Capell\ContentSections\Models\Section;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * Authoring fields for media shown inside block items such as a logo or team member.
 *
 * Editors upload images once to the section, then choose one per item. The item stores only the
 * Spatie media uuid; the same resolver the public renderer uses decides whether a choice is valid.
 */
final class SectionMediaFields
{
    public static function upload(): SpatieMediaLibraryFileUpload
    {
        $field = MediaLibraryFileUpload::make(Section::BLOCK_MEDIA_COLLECTION);

        // The Admin factory carries the standard disk, metadata and image configuration; fall back to a
        // plain Spatie upload if another media backend replaced it.
        if (! $field instanceof SpatieMediaLibraryFileUpload) {
            $field = SpatieMediaLibraryFileUpload::make(Section::BLOCK_MEDIA_COLLECTION)
                ->collection(Section::BLOCK_MEDIA_COLLECTION)
                ->maxSize(GetMaxUploadSizeInBytes::run());
        }

        return $field
            ->label(__('capell-content-sections::form.block_media'))
            ->helperText(__('capell-content-sections::form.block_media_helper'))
            ->disk(self::uploadDisk())
            ->visibility('public')
            ->multiple()
            ->reorderable()
            ->image()
            ->columnSpanFull();
    }

    /**
     * Filament uploads to its own default disk, which is private ("local") on a standard Laravel
     * install. The public renderer only serves publicly visible disks, so a block image must be stored
     * on one: the media library's configured disk when it is public, otherwise the "public" disk.
     */
    public static function uploadDisk(): string
    {
        $configured = Config::get('media-library.disk_name');

        if (is_string($configured) && $configured !== '' && Config::get(sprintf('filesystems.disks.%s.visibility', $configured)) === 'public') {
            return $configured;
        }

        return 'public';
    }

    public static function select(): Select
    {
        return Select::make('media')
            ->label(__('capell-content-sections::form.media'))
            ->placeholder(__('capell-content-sections::form.media_none'))
            ->native(false)
            ->options(static fn (?Model $record): array => self::options($record))
            // A reference to deleted or unavailable media stays visible so the editor can clear it.
            ->getOptionLabelUsing(static fn (mixed $value, ?Model $record): string => self::options($record)[is_string($value) ? $value : ''] ?? __('capell-content-sections::form.media_missing'))
            ->selectablePlaceholder()
            ->rule(static fn (?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                if (blank($value)) {
                    return;
                }

                if (! $record instanceof Section || ! ResolveSectionMediaAction::run($record, $value) instanceof SectionMediaData) {
                    $fail(__('capell-content-sections::form.media_invalid'));
                }
            })
            ->columnSpanFull();
    }

    /**
     * @return array<string, string>
     */
    private static function options(?Model $record): array
    {
        if (! $record instanceof Section) {
            return [];
        }

        $options = [];

        foreach ($record->getMedia(Section::BLOCK_MEDIA_COLLECTION) as $media) {
            // Offer exactly what the public renderer will accept: public-disk images with a plain URL.
            if (is_string($media->uuid) && ResolveSectionMediaAction::run($record, $media->uuid) instanceof SectionMediaData) {
                $options[$media->uuid] = $media->name !== '' ? $media->name : $media->file_name;
            }
        }

        return $options;
    }
}
