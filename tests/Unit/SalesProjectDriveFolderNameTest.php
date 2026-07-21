<?php

namespace Tests\Unit;

use App\Models\SalesProject;
use PHPUnit\Framework\TestCase;

class SalesProjectDriveFolderNameTest extends TestCase
{
    public function test_folder_name_combines_padded_identifier_and_project_title(): void
    {
        $project = new SalesProject(['title' => 'PAA 2026']);
        $project->setAttribute('id', 1);

        $this->assertSame('01 - PAA 2026', $project->driveFolderName());
    }

    public function test_same_titles_remain_unique_by_project_identifier(): void
    {
        $first = new SalesProject(['title' => 'PAA 2026']);
        $first->setAttribute('id', 1);
        $second = new SalesProject(['title' => 'PAA 2026']);
        $second->setAttribute('id', 2);

        $this->assertSame('01 - PAA 2026', $first->driveFolderName());
        $this->assertSame('02 - PAA 2026', $second->driveFolderName());
        $this->assertNotSame($first->driveFolderName(), $second->driveFolderName());
    }
}
