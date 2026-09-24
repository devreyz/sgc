<?php

namespace Tests\Unit;

use Tests\TestCase;

class UiDesignSystemContractTest extends TestCase
{
    public function test_global_stylesheet_loads_theme_before_components(): void
    {
        $app = file_get_contents(resource_path('css/app.css'));
        $themePosition = strpos($app, "@import './theme.css'");
        $componentsPosition = strpos($app, "@import './design-system.css'");

        self::assertNotFalse($themePosition);
        self::assertNotFalse($componentsPosition);
        self::assertLessThan($componentsPosition, $themePosition);
    }

    public function test_design_system_exposes_the_core_component_families(): void
    {
        $css = file_get_contents(resource_path('css/design-system.css'));

        foreach ([
            '.ui-page',
            '.ui-section',
            '.ui-btn',
            '.ui-badge',
            '.ui-tabs',
            '.ui-metric',
            '.ui-field',
            '.ui-table',
            '.ui-record',
            '.ui-state',
            '.ui-dialog',
        ] as $component) {
            self::assertStringContainsString($component, $css);
        }
    }

    public function test_reference_member_pages_are_connected_to_the_shared_system(): void
    {
        $dashboard = file_get_contents(resource_path('views/associate/dashboard.blade.php'));
        $workspace = file_get_contents(resource_path('views/associate/project-workspace.blade.php'));

        self::assertStringContainsString('associate-dashboard ui-page', $dashboard);
        self::assertStringContainsString('dash-section ui-section', $dashboard);
        self::assertStringContainsString('project-workspace ui-page', $workspace);
        self::assertStringContainsString('tabs ui-tabs', $workspace);
        self::assertStringContainsString('var(--ui-color-success)', $dashboard);
        self::assertStringContainsString('var(--ui-color-success)', $workspace);
    }

    public function test_ai_facing_usage_guide_is_kept_with_the_codebase(): void
    {
        $guide = file_get_contents(base_path('docs/UI_DESIGN_SYSTEM.md'));

        self::assertStringContainsString('Catálogo de classes', $guide);
        self::assertStringContainsString('Mapa de migração do legado', $guide);
        self::assertStringContainsString('Instrução curta para IA', $guide);
    }
}
