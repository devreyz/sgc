<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GenericExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    protected array $columns;
    protected array $data;
    protected string $title;

    public function __construct(array $columns, array $data, string $title)
    {
        $this->columns = $columns;
        $this->data = $data;
        $this->title = $title;
    }

    public function array(): array
    {
        return array_map(function ($row) {
            $formattedRow = [];
            foreach (array_keys($this->columns) as $field) {
                $value = $row[$field] ?? null;
                $formattedRow[] = $this->formatValue($value);
            }
            return $formattedRow;
        }, $this->data);
    }

    public function headings(): array
    {
        return array_values($this->columns);
    }

    public function title(): string
    {
        return substr($this->title, 0, 31); // Excel limita a 31 caracteres
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
                'font' => [
                    'color' => ['rgb' => 'FFFFFF'],
                    'bold' => true,
                ],
            ],
        ];
    }

    protected function formatValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \Carbon\Carbon) {
            return $value->format('d/m/Y');
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    }
}
