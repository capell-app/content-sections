<?php

declare(strict_types=1);

use Capell\ContentSections\Models\Section;
use Capell\ContentSections\Support\SectionSiteScope;
use Capell\Core\Models\Site;
use Capell\Tests\Fixtures\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    config(['permission.teams' => true]);
    resolve(PermissionRegistrar::class)->teams = true;
    resolve(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    auth()->logout();
    resolve(PermissionRegistrar::class)->setPermissionsTeamId(null);
    resolve(PermissionRegistrar::class)->teams = false;
    resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    config(['permission.teams' => false]);
});

it('scopes sections by the real team-backed site assignments', function (): void {
    $assignedSite = Site::factory()->create();
    $otherSite = Site::factory()->create();
    $assignedSection = Section::factory()->create(['site_id' => $assignedSite->getKey()]);
    $otherSection = Section::factory()->create(['site_id' => $otherSite->getKey()]);
    $user = User::factory()->create();
    $role = Role::findOrCreate('section-site-scope-test-role', 'web');

    $user->assignRoleForSite($assignedSite, $role);
    resolve(PermissionRegistrar::class)->setPermissionsTeamId($assignedSite->getKey());
    auth()->setUser($user);

    expect(SectionSiteScope::applyForCurrentActor(Section::query())->pluck('id')->all())
        ->toBe([$assignedSection->getKey()])
        ->and($user->getAssignedSiteIds()->all())
        ->toBe([$assignedSite->getKey()])
        ->and($otherSection->exists)->toBeTrue();
});
