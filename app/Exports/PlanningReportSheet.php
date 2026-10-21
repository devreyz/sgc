<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class PlanningReportSheet extends StringValueBinder implements FromArray, WithTitle, ShouldAutoSize, WithCustomValueBinder
{
    public function __construct(private string $name, private array $rows) {}
    public function array(): array { return $this->rows; }
    public function title(): string { return $this->name; }
}
