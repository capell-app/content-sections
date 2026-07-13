<?php

declare(strict_types=1);

namespace Capell\ContentSections\Health;

use Capell\ContentSections\Filament\Resources\Sections\SectionResource;
use Capell\ContentSections\Models\Section;
use Capell\ContentSections\Support\SectionPublicLayoutWidgetPayloadContributor;
use Capell\ContentSections\Support\SectionRegistry;
use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class ContentSectionsHealthCheck implements ChecksExtensionHealth
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }

    /**
     * @return Collection<int, DoctorCheckResultData>
     */
    public static function runDiagnostics(): Collection
    {
        $check = new self;

        return collect([
            $check->storageTableCheck(),
            $check->modelMorphAliasCheck(),
            $check->adminResourceCheck(),
            $check->sectionRegistryCheck(),
            $check->publicPayloadContributorCheck(),
        ]);
    }

    public static function passed(): bool
    {
        return self::runDiagnostics()
            ->every(static fn (DoctorCheckResultData $result): bool => $result->passed);
    }

    public function storageTableCheck(): DoctorCheckResultData
    {
        $passed = Schema::hasTable('sections');

        return new DoctorCheckResultData(
            label: (string) __('capell-content-sections::generic.health_storage_table_label'),
            passed: $passed,
            message: $passed
                ? (string) __('capell-content-sections::generic.health_storage_table_passed')
                : (string) __('capell-content-sections::generic.health_storage_table_failed'),
            remediation: $passed
                ? null
                : (string) __('capell-content-sections::generic.health_storage_table_remediation'),
        );
    }

    public function modelMorphAliasCheck(): DoctorCheckResultData
    {
        $passed = Relation::getMorphedModel('section') === Section::class;

        return new DoctorCheckResultData(
            label: (string) __('capell-content-sections::generic.health_morph_alias_label'),
            passed: $passed,
            message: $passed
                ? (string) __('capell-content-sections::generic.health_morph_alias_passed')
                : (string) __('capell-content-sections::generic.health_morph_alias_failed'),
            remediation: $passed
                ? null
                : (string) __('capell-content-sections::generic.health_morph_alias_remediation'),
        );
    }

    public function adminResourceCheck(): DoctorCheckResultData
    {
        $passed = class_exists(SectionResource::class);

        return new DoctorCheckResultData(
            label: (string) __('capell-content-sections::generic.health_admin_resource_label'),
            passed: $passed,
            message: $passed
                ? (string) __('capell-content-sections::generic.health_admin_resource_passed')
                : (string) __('capell-content-sections::generic.health_admin_resource_failed'),
            remediation: $passed
                ? null
                : (string) __('capell-content-sections::generic.health_admin_resource_remediation'),
        );
    }

    public function sectionRegistryCheck(): DoctorCheckResultData
    {
        $count = app()->bound(SectionRegistry::class)
            ? count(resolve(SectionRegistry::class)->all())
            : 0;
        $passed = $count >= 17;

        return new DoctorCheckResultData(
            label: (string) __('capell-content-sections::generic.health_section_registry_label'),
            passed: $passed,
            message: $passed
                ? (string) __('capell-content-sections::generic.health_section_registry_passed', ['count' => $count])
                : (string) __('capell-content-sections::generic.health_section_registry_failed', ['count' => $count]),
            remediation: $passed
                ? null
                : (string) __('capell-content-sections::generic.health_section_registry_remediation'),
        );
    }

    public function publicPayloadContributorCheck(): DoctorCheckResultData
    {
        $contract = sprintf('Capell\\%s\\Contracts\\PublicLayoutWidgetPayloadContributor', 'LayoutBuilder');
        $passed = interface_exists($contract)
            && is_subclass_of(SectionPublicLayoutWidgetPayloadContributor::class, $contract);

        return new DoctorCheckResultData(
            label: (string) __('capell-content-sections::generic.health_public_payload_contributor_label'),
            passed: $passed,
            message: $passed
                ? (string) __('capell-content-sections::generic.health_public_payload_contributor_passed')
                : (string) __('capell-content-sections::generic.health_public_payload_contributor_failed'),
            remediation: $passed
                ? null
                : (string) __('capell-content-sections::generic.health_public_payload_contributor_remediation'),
        );
    }
}
