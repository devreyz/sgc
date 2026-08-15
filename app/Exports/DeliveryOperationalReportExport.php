<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DeliveryOperationalReportExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    private array $rows = [];

    private array $groupRows = [];

    private array $sectionRows = [];

    private array $subtotalRows = [];

    private array $groupTotalRows = [];

    private array $dataRows = [];

    private array $columns;

    private array $headings;

    private int $headingRow = 4;

    private int $totalRow = 5;

    public function __construct(private readonly array $report)
    {
        $this->columns = $report['columns'];
        $labels = $report['column_labels'];
        $labels['associate'] = $report['project']->tenant?->associateTerm() ?? 'Membro';
        $this->headings = array_map(fn (string $column) => $labels[$column] ?? $column, $this->columns);
        $this->build();
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return match ($this->report['type']) {
            'product' => 'Entregas por produto',
            'customer' => 'Distribuições por cliente',
            default => 'Entregas por membro',
        };
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $lastColumn = Coordinate::stringFromColumnIndex(count($this->columns));
            $sheet->mergeCells("A1:{$lastColumn}1");
            $sheet->mergeCells("A2:{$lastColumn}2");
            $sheet->freezePane('A5');
            $sheet->setAutoFilter("A{$this->headingRow}:{$lastColumn}{$this->headingRow}");
            $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            ]);
            $sheet->getStyle("A{$this->headingRow}:{$lastColumn}{$this->headingRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4B5563']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            foreach ($this->groupRows as $row) {
                $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
                ]);
            }
            foreach ($this->sectionRows as $row) {
                $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
                ]);
            }
            foreach (array_merge($this->subtotalRows, [$this->totalRow]) as $row) {
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '9CA3AF']]],
                ]);
            }
            $sheet->getStyle("A1:{$lastColumn}{$this->totalRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A1:{$lastColumn}{$this->totalRow}")->getAlignment()->setWrapText(true);
            foreach ($this->columns as $index => $column) {
                $letter = Coordinate::stringFromColumnIndex($index + 1);
                if (in_array($column, ['received_quantity', 'distributed_quantity'], true)) {
                    $sheet->getStyle("{$letter}5:{$letter}{$this->totalRow}")->getNumberFormat()->setFormatCode('#,##0.000');
                }
                if (in_array($column, ['unit_value', 'gross_value', 'admin_fee', 'net_value'], true)) {
                    $sheet->getStyle("{$letter}5:{$letter}{$this->totalRow}")->getNumberFormat()->setFormatCode('"R$ "#,##0.00');
                }
                if (in_array($column, ['destinations', 'associate', 'product'], true)) {
                    $sheet->getColumnDimension($letter)->setWidth($column === 'destinations' ? 34 : 25);
                }
            }
        }];
    }

    private function build(): void
    {
        $project = $this->report['project'];
        $this->rows = [
            [$this->title().' - '.$project->title],
            ['Gerado em '.now()->format('d/m/Y H:i').' | Valores financeiros calculados somente pelas distribuições.'],
            [],
            $this->headings,
        ];

        foreach ($this->report['groups'] as $group) {
            $this->rows[] = [$group['title'].($group['subtitle'] ? ' - '.$group['subtitle'] : '').' | Líquido: R$ '.number_format($group['totals']['net'], 2, ',', '.')];
            $this->groupRows[] = count($this->rows);
            $groupSubtotalRows = [];

            foreach ($group['sections'] as $section) {
                $this->rows[] = [$section['title'].' | Distribuído: '.number_format($section['totals']['distributed_quantity'], 3, ',', '.').' | Bruto: R$ '.number_format($section['totals']['gross'], 2, ',', '.')];
                $this->sectionRows[] = count($this->rows);
                $firstDataRow = count($this->rows) + 1;
                foreach ($section['rows'] as $row) {
                    $this->rows[] = array_map(fn (string $column) => $this->value($row, $column), $this->columns);
                    $this->dataRows[] = count($this->rows);
                }
                $lastDataRow = count($this->rows);
                $this->rows[] = $this->formulaRow('Subtotal', $firstDataRow, $lastDataRow);
                $this->subtotalRows[] = count($this->rows);
                $groupSubtotalRows[] = count($this->rows);
            }
            $this->groupTotalRows[] = $groupSubtotalRows;
        }

        $this->rows[] = $this->referenceFormulaRow('TOTAL GERAL', $this->subtotalRows);
        $this->totalRow = count($this->rows);
    }

    private function value(array $row, string $column): mixed
    {
        return match ($column) {
            'date' => $row['date'], 'associate' => $row['associate'], 'product' => $row['product'],
            'destinations' => $row['destinations'] ?: null, 'received_quantity' => $row['received_quantity'],
            'distributed_quantity' => $row['distributed_quantity'], 'unit_value' => $row['unit_price'],
            'gross_value' => $row['gross'], 'admin_fee' => $row['fees'], 'net_value' => $row['net'],
            'status' => $row['status'], default => null,
        };
    }

    private function formulaRow(string $label, int $firstRow, int $lastRow): array
    {
        return array_map(function (string $column, int $index) use ($label, $firstRow, $lastRow): mixed {
            if ($index === 0) {
                return $label;
            }
            if (! in_array($column, ['received_quantity', 'distributed_quantity', 'gross_value', 'admin_fee', 'net_value'], true)) {
                return null;
            }
            $letter = Coordinate::stringFromColumnIndex($index + 1);

            return $lastRow >= $firstRow ? "=SUM({$letter}{$firstRow}:{$letter}{$lastRow})" : 0;
        }, $this->columns, array_keys($this->columns));
    }

    private function referenceFormulaRow(string $label, array $rows): array
    {
        return array_map(function (string $column, int $index) use ($label, $rows): mixed {
            if ($index === 0) {
                return $label;
            }
            if ($rows === [] || ! in_array($column, ['received_quantity', 'distributed_quantity', 'gross_value', 'admin_fee', 'net_value'], true)) {
                return null;
            }
            $letter = Coordinate::stringFromColumnIndex($index + 1);

            return '='.$letter.implode("+{$letter}", $rows);
        }, $this->columns, array_keys($this->columns));
    }
}
