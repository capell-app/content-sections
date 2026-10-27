<?php

declare(strict_types=1);

use Capell\ContentSections\Models\Section;
use Capell\ContentSections\Policies\SectionAssetAttachmentPolicy;
use Capell\Core\Models\AssetAttachment;
use Capell\Core\Models\Page;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('resolves section attachment policies and requires section editing permission', function (): void {
    $user = User::factory()->create();
    $attachment = new AssetAttachment;
    $attachment->setRelation('related', new Section);

    expect(Gate::getPolicyFor(AssetAttachment::class))->toBeInstanceOf(SectionAssetAttachmentPolicy::class)
        ->and(Gate::forUser($user)->allows('create', AssetAttachment::class))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $attachment))->toBeFalse();

    Permission::findOrCreate('Update:Section');
    $user->givePermissionTo('Update:Section');

    expect(Gate::forUser($user)->allows('create', AssetAttachment::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('deleteAny', AssetAttachment::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('viewAny', AssetAttachment::class))->toBeFalse();

    // Registering a policy for the neutral attachment model must not grant
    // Section editors access to attachments owned by another model.
    $attachment->setRelation('related', new Page);
    expect(Gate::forUser($user)->allows('view', $attachment))->toBeFalse()
        ->and(Gate::forUser($user)->allows('update', $attachment))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $attachment))->toBeFalse();
});

it('scopes section attachment mutations to the owning section', function (): void {
    $user = User::factory()->create();
    Permission::findOrCreate('Update:Section');
    $user->givePermissionTo('Update:Section');
    $section = new Section(['site_id' => 123456]);
    $attachment = new AssetAttachment;
    $attachment->setRelation('related', $section);
    $policy = resolve(SectionAssetAttachmentPolicy::class);

    expect($policy->update($user, $attachment))->toBeFalse()
        ->and($policy->delete($user, $attachment))->toBeFalse();

    $global = User::factory()->create();
    $global->assignRole(Role::findOrCreate('super_admin'));

    expect($policy->viewAny($global))->toBeTrue()
        ->and($policy->view($global, $attachment))->toBeTrue()
        ->and($policy->update($global, $attachment))->toBeTrue()
        ->and($policy->delete($global, $attachment))->toBeTrue();
});
