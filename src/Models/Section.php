<?php

declare(strict_types=1);

namespace Capell\ContentSections\Models;

use Aimeos\Nestedset\NodeTrait;
use Aimeos\Nestedset\QueryBuilder;
use Bkwld\Cloner\Cloneable;
use Capell\ContentSections\Database\Factories\SectionFactory;
use Capell\ContentSections\Models\Concerns\ComposhipsJsonRelationshipsTrait;
use Capell\ContentSections\Observers\SectionObserver;
use Capell\Core\Concerns\HasCapellMedia;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Enums\PublishStatusEnum;
use Capell\Core\Models\AssetRelation;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Concerns\HasAssets;
use Capell\Core\Models\Concerns\HasMetaData;
use Capell\Core\Models\Concerns\HasMorphModelRelations;
use Capell\Core\Models\Concerns\HasPublishDates;
use Capell\Core\Models\Concerns\HasTranslations;
use Capell\Core\Models\Concerns\HasUserstamps;
use Capell\Core\Models\Contracts\Blueprintable;
use Capell\Core\Models\Contracts\Publishable;
use Capell\Core\Models\Contracts\Userstampable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Staudenmeir\EloquentJsonRelations\Relations\BelongsToJson;

/**
 * @property-read EloquentCollection<int, AssetRelation> $assets
 * @property-read int|null $assets_count
 * @property-read int|null $audits_count
 * @property-read Collection<int, Section> $children
 * @property-read int|null $children_count
 * @property-read User|null $creator
 * @property-read User|null $destroyer
 * @property-read User|null $editor
 * @property-read array<array-key, mixed> $actions
 * @property-read PublishStatusEnum $publish_status
 * @property-read Media|null $image
 * @property-read EloquentCollection<int, Language> $languages
 * @property-read int|null $languages_count
 * @property-read Pageable|null $page
 * @property-read Section|null $parent
 * @property int|null $parent_id
 * @property-read Site|null $site
 * @property-read Translation|null $translation
 * @property-read EloquentCollection<int, Translation> $translations
 * @property-read int|null $translations_count
 * @property-read Blueprint|null $blueprint
 * @property-read EloquentCollection|Media[] $media
 * @property-read int|null $media_count
 * @property-read EloquentCollection|Section[] $related
 * @property-read int|null $related_count
 * @property-read Page|null $linkedPage
 * @property-read EloquentCollection<int, AssetRelation> $assetRelations
 * @property-read int|null $asset_relations_count
 * @property-read EloquentCollection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read string|null $title
 * @property int $id
 * @property string|null $uuid
 * @property int $workspace_id
 * @property int $shadowed_by_workspace_id
 * @property string $name
 * @property int $blueprint_id
 * @property int|null $site_id
 * @property array<array-key, mixed>|null $meta
 * @property int $order
 * @property CarbonImmutable|null $visible_from
 * @property CarbonImmutable|null $visible_until
 * @property int $_lft
 * @property int $_rgt
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 *
 * @mixin Model
 * @mixin QueryBuilder<Section>
 */
#[ObservedBy(SectionObserver::class)]
class Section extends Model implements Blueprintable, HasMedia, Publishable, Userstampable
{
    use Cloneable;
    use ComposhipsJsonRelationshipsTrait;
    use HasAssets;
    use HasCapellMedia;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasMetaData;
    use HasMorphModelRelations;
    use HasPublishDates;
    use HasTranslations;
    use HasUserstamps;
    use LogsActivity;
    use NodeTrait;
    use SoftDeletes;

    protected $table = 'sections';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'meta',
        'name',
        'order',
        'parent_id',
        'uuid',
        'visible_from',
        'visible_until',
        'site_id',
        'blueprint_id',
    ];

    /**
     * Relations on this model that should be cloned
     *
     * @var array|string[]
     */
    protected array $cloneable_relations = [
        'translations',
    ];

    protected static string $factory = SectionFactory::class;

    /**
     * @return array<array-key, mixed>
     */
    public static function getMorphRelations(?Language $language = null, bool $normalizeKey = false): array
    {
        $languageId = $language instanceof Language ? (int) $language->id : null;

        $base = [
            'ancestors.blueprint',
            'image',
            'media',
            'linkedPage' => function (BuilderContract $query) use ($languageId): void {
                $query->with([
                    'translation' => function (BuilderContract $query) use ($languageId): void {
                        $query->with('language')
                            ->when(
                                $languageId !== null,
                                function (BuilderContract $query) use ($languageId): void {
                                    if (DB::getDriverName() === 'sqlite') {
                                        $query->orderByRaw(
                                            'CASE language_id '
                                            . sprintf('WHEN %d THEN 0 ELSE 1 END', $languageId),
                                        );
                                    } else {
                                        $query->orderByRaw('FIELD(language_id, ?)', [$languageId]);
                                    }
                                },
                            );
                    },
                    'pageUrl' => function (BuilderContract $query) use ($languageId): void {
                        $query->with('siteDomain')
                            ->when(
                                $languageId !== null,
                                function (BuilderContract $query) use ($languageId): void {
                                    if (DB::getDriverName() === 'sqlite') {
                                        $query->orderByRaw(
                                            'CASE language_id '
                                            . sprintf('WHEN %d THEN 0 ELSE 1 END', $languageId),
                                        );
                                    } else {
                                        $query->orderByRaw('FIELD(language_id, ?)', [$languageId]);
                                    }
                                },
                            );
                    },
                ]);
            },
            'translation' => fn (BuilderContract $query): BuilderContract => $query->with('language')
                ->when($languageId !== null, fn (BuilderContract $query): BuilderContract => $query->where('language_id', $languageId)),
            'blueprint',
        ];

        return static::mergeMorphRelationDefinitions($base, self::class, $language, $normalizeKey);
    }

    /** @return array<int, string> */
    public static function getTypes(): array
    {
        return self::query()
            ->select('blueprint_id')
            ->withWhereHas('blueprint')
            ->groupBy('blueprint_id')
            ->get()
            ->mapWithKeys(fn (self $section): array => [
                $section->blueprint_id => (string) $section->blueprint?->name,
            ])
            ->filter(fn (string $label): bool => $label !== '')
            ->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('content')
            ->logAll()
            ->logExcept([
                'updated_at',
                'created_at',
                'deleted_at',
                'workspace_id',
                'shadowed_by_workspace_id',
                '_lft',
                '_rgt',
                'created_by',
                'updated_by',
                'deleted_by',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollectionEnum::Image->value)->singleFile();
    }

    /** @return BelongsTo<Blueprint, $this> */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class, 'blueprint_id');
    }

    public function getBlueprint(): Blueprint
    {
        /** @var Blueprint $blueprint */
        $blueprint = $this->getRelationValue('blueprint') ?? $this->blueprint;

        return $blueprint;
    }

    public function loadParent(Language $language): void
    {
        $this->load([
            'parent' => fn (BuilderContract $query): BuilderContract => $query->withWhereHasLanguage($language->id),
        ]);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return MorphOne<Media, $this> */
    public function image(): MorphOne
    {
        return $this->morphOneMedia(MediaCollectionEnum::Image->value);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function linkedPage(): MorphTo
    {
        return $this->morphTo(type: 'meta->linked_pageable_type', id: 'meta->linked_pageable_id');
    }

    /** @return BelongsToJson<self, $this> */
    public function related(): BelongsToJson
    {
        return $this->belongsToJson(self::class, 'meta->related');
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function scopeOrdered(Builder $query, string $dir = 'asc'): void
    {
        $query->orderBy($this->qualifyColumn('order'))
            ->orderBy($this->qualifyColumn('name'));
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function getActionsAttribute(): array
    {
        return $this->meta['actions'] ?? [];
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'meta' => 'json',
            'visible_from' => 'datetime',
            'visible_until' => 'datetime',
        ];
    }
}
