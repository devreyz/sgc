<?php

namespace Tests\Unit;

use Tests\TestCase;

class AssociateDashboardAjaxMarkupContractTest extends TestCase
{
    public function test_ajax_refresh_reuses_the_dashboard_styled_markup(): void
    {
        $script = file_get_contents(public_path('js/associate-portal-ajax.js'));

        self::assertStringContainsString('class="projects-wrap"', $script);
        self::assertStringContainsString('class="projects-table"', $script);
        self::assertStringContainsString('class="project-row ${rowTone}"', $script);
        self::assertStringContainsString('class="deliveries-wrap"', $script);
        self::assertStringContainsString('class="delivery-row ${tone}"', $script);
        self::assertStringContainsString('class="delivery-copy"', $script);
        self::assertStringNotContainsString('class="project-item"', $script);
        self::assertStringNotContainsString('class="delivery-item"', $script);
    }
}
