<?php

declare(strict_types=1);

namespace Capell\ContentSections\Policies;

use Capell\Admin\Policies\Concerns\ResolvesShieldPermission;
use Capell\Admin\Support\SiteScope;
use Capell\ContentSections\Models\Section;
use Illuminate\Foundation\Auth\User;
use Throwable;

final class SectionPolicy
{
    use ResolvesShieldPermission;

    private const string SUBJECT = 'Section';

    public function viewAny(User $user): bool
    {
        return $this->hasAnyPermission($user, ['view_any', 'view']);
    }

    public function view(User $user, Section $section): bool
    {
        return $this->hasAnyPermission($user, ['view_any', 'view'])
            && $this->canUseSectionSite($user, $section);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'create');
    }

    public function update(User $user, Section $section): bool
    {
        return $this->hasPermission($user, 'update')
            && $this->canUseSectionSite($user, $section);
    }

    public function delete(User $user, Section $section): bool
    {
        return $this->hasPermission($user, 'delete')
            && $this->canUseSectionSite($user, $section);
    }

    public function deleteAny(User $user): bool
    {
        return $this->hasPermission($user, 'delete_any');
    }

    public function restore(User $user, Section $section): bool
    {
        return $this->hasPermission($user, 'restore')
            && $this->canUseSectionSite($user, $section);
    }

    public function restoreAny(User $user): bool
    {
        return $this->hasPermission($user, 'restore_any');
    }

    public function forceDelete(User $user, Section $section): bool
    {
        return $this->hasPermission($user, 'force_delete')
            && $this->canUseSectionSite($user, $section);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->hasPermission($user, 'force_delete_any');
    }

    public function replicate(User $user, Section $section): bool
    {
        return $this->hasPermission($user, 'replicate')
            && $this->canUseSectionSite($user, $section);
    }

    public function reorder(User $user): bool
    {
        return $this->hasPermission($user, 'reorder');
    }

    /**
     * @param  list<string>  $abilities
     */
    private function hasAnyPermission(User $user, array $abilities): bool
    {
        foreach ($abilities as $ability) {
            if ($this->hasPermission($user, $ability)) {
                return true;
            }
        }

        return false;
    }

    private function hasPermission(User $user, string $ability): bool
    {
        if (SiteScope::isGlobalActor($user)) {
            return true;
        }

        if (! method_exists($user, 'checkPermissionTo')) {
            return false;
        }

        try {
            return $user->checkPermissionTo(self::permission($ability, self::SUBJECT));
        } catch (Throwable) {
            return false;
        }
    }

    private function canUseSectionSite(User $user, Section $section): bool
    {
        if ($section->site_id === null || SiteScope::isGlobalActor($user)) {
            return true;
        }

        if (method_exists($user, 'getAssignedSiteIds')) {
            return $user->getAssignedSiteIds()->contains($section->site_id);
        }

        return true;
    }
}
