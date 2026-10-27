<?php

declare(strict_types=1);

namespace Capell\ContentSections\Policies;

use Capell\Admin\Policies\Concerns\ResolvesShieldPermission;
use Capell\Admin\Support\SiteScope;
use Capell\ContentSections\Models\Section;
use Capell\Core\Models\AssetAttachment;
use Illuminate\Foundation\Auth\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/** An attachment belongs to its section; it has no independent editing role. */
final class SectionAssetAttachmentPolicy
{
    use ResolvesShieldPermission;

    public function __construct(private readonly SectionPolicy $sections) {}

    public function viewAny(User $user): bool
    {
        return $this->sections->viewAny($user);
    }

    public function view(User $user, AssetAttachment $attachment): bool
    {
        $section = $attachment->related;

        return $section instanceof Section && $this->sections->view($user, $section);
    }

    public function create(User $user): bool
    {
        if (SiteScope::isGlobalActor($user)) {
            return true;
        }

        try {
            return $user->checkPermissionTo(self::permission('update', 'Section'));
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public function update(User $user, AssetAttachment $attachment): bool
    {
        $section = $attachment->related;

        return $section instanceof Section && $this->sections->update($user, $section);
    }

    public function delete(User $user, AssetAttachment $attachment): bool
    {
        return $this->update($user, $attachment);
    }

    public function deleteAny(User $user): bool
    {
        return $this->create($user);
    }
}
