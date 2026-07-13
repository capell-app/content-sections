<?php

declare(strict_types=1);

namespace Capell\ContentSections\Actions;

use Capell\ContentSections\Models\Section;
use Capell\Core\Contracts\Actionable;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Section run(array<array-key, mixed> $data)
 */
class CreateSectionContentAction implements Actionable
{
    use AsObject;

    /**
     * @param  array<int, array{language_id: int|string, title: string, content?: mixed}>  $translations
     */
    public function createTranslations(Section $content, array $translations): void
    {
        foreach ($translations as $translation) {
            $content->translations()->create([
                'language_id' => $translation['language_id'],
                'title' => $translation['title'],
                'content' => $translation['content'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function handle(array $data): Section
    {
        $translations = $this->normalizeTranslations($data['translations'] ?? []);
        unset($data['translations']);

        if (! isset($data['name']) && isset($translations[0])) {
            $data['name'] = $translations[0]['title'];
        }

        $content = Section::query()->create($data);

        if ($translations !== []) {
            $this->createTranslations($content, $translations);
        }

        return $content;
    }

    /**
     * @return list<array{language_id: int|string, title: string, content?: mixed}>
     */
    private function normalizeTranslations(mixed $translations): array
    {
        if (! is_array($translations)) {
            return [];
        }

        $normalized = [];

        foreach ($translations as $translation) {
            if (! is_array($translation)) {
                continue;
            }

            $languageId = $translation['language_id'] ?? null;
            $title = $translation['title'] ?? null;

            if ((! is_int($languageId) && ! is_string($languageId)) || ! is_string($title)) {
                continue;
            }

            $normalized[] = [
                'language_id' => $languageId,
                'title' => $title,
                'content' => $translation['content'] ?? null,
            ];
        }

        return $normalized;
    }
}
