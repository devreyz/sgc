<?php

namespace Tests\Unit;

use App\Models\ServiceVersionField;
use App\Services\Services\ServicePresetRegistry;
use App\Support\ServiceConfigurationLabels;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceConfigurationParityTest extends TestCase
{
    #[Test]
    public function every_service_preset_has_safe_fields_and_complete_calculation_defaults(): void
    {
        foreach (app(ServicePresetRegistry::class)->all() as $key => $preset) {
            $this->assertNotEmpty($preset['label'], $key);
            $this->assertNotEmpty($preset['description'], $key);
            $this->assertContains($preset['execution_config']['quantity_mode'], ['fixed_one', 'field', 'meter_difference'], $key);
            $this->assertSame('primary', $preset['execution_config']['customer_quantity_mode'], $key);
            $this->assertSame('primary', $preset['execution_config']['provider_quantity_mode'], $key);

            $fieldKeys = collect($preset['fields'])->pluck('key');
            $this->assertSame($fieldKeys->count(), $fieldKeys->unique()->count(), $key.' has duplicate field keys');
            foreach ($preset['fields'] as $field) {
                $this->assertContains($field['type'], ServiceVersionField::TYPES, $key.'.'.$field['key']);
                $this->assertContains($field['phase'], ServiceVersionField::PHASES, $key.'.'.$field['key']);
            }

            if ($preset['execution_config']['quantity_mode'] === 'field') {
                $this->assertTrue($fieldKeys->contains($preset['execution_config']['quantity_field']), $key);
            }
            if ($preset['execution_config']['quantity_mode'] === 'meter_difference') {
                $this->assertTrue($fieldKeys->contains($preset['execution_config']['meter_start_field']), $key);
                $this->assertTrue($fieldKeys->contains($preset['execution_config']['meter_end_field']), $key);
            }
        }
    }

    #[Test]
    public function technical_codes_have_portuguese_labels(): void
    {
        $this->assertEqualsCanonicalizing(ServiceVersionField::TYPES, array_keys(ServiceConfigurationLabels::fieldTypes()));
        $this->assertEqualsCanonicalizing(ServiceVersionField::PHASES, array_keys(ServiceConfigurationLabels::phases()));

        foreach (array_merge(ServiceConfigurationLabels::fieldTypes(), ServiceConfigurationLabels::phases()) as $code => $label) {
            $this->assertNotSame($code, $label);
        }
    }

    #[Test]
    public function portal_and_filament_share_the_same_catalog_services_and_permissions(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Services/ServiceCatalogController.php'));
        $filamentCreate = file_get_contents(app_path('Filament/Resources/ServiceResource/Pages/CreateService.php'));
        $filamentResource = file_get_contents(app_path('Filament/Resources/ServiceResource.php'));

        $this->assertStringContainsString('ServicePresetRegistry', $controller);
        $this->assertStringContainsString('ServicePresetRegistry', $filamentCreate);
        $this->assertStringContainsString('ServiceCatalogService', $controller);
        $this->assertStringContainsString('ServiceCatalogService', $filamentCreate);
        $this->assertStringContainsString("checkPermissionTo('manage_service_catalog')", $filamentResource);
        $this->assertStringContainsString("allow(\$request, 'manage_service_catalog')", $controller);
    }
}
