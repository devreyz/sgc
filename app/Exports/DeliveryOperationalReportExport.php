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
    private const HEADINGS = [
        'Registro', 'Data', 'Membro', 'Produto', 'Cliente / destino',
        'Qtd. recebida', 'Qtd. distribuída', 'Unidade', 'Valor unitário',
        'Valor bruto', 'Taxas', 'Valor líquido', 'Situação',
    ];

    private array $rows = [];

    private array $groupRows = [];

    private array $parentRows = [];

    private int $headingRow = 4;

    private int $totalRow = 5;

    public function __construct(private readonly array $report)
    {
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
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = Coordinate::stringFromColumnIndex(count(self::HEADINGS));

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
                        'font' => ['bold' => true, 'color' => ['rgb' => '1F2937']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
                    ]);
                }

                foreach ($this->parentRows as $row) {
                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
                        'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '9CA3AF']]],
                    ]);
                }

                $sheet->getStyle("A{$this->totalRow}:{$lastColumn}{$this->totalRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '374151']]],
                ]);
                $sheet->getStyle("F5:L{$this->totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("F5:G{$this->totalRow}")->getNumberFormat()->setFormatCode('#,##0.000');
                $sheet->getStyle("I5:L{$this->totalRow}")->getNumberFormat()->setFormatCode('"R$ "#,##0.00');
                $sheet->getStyle("A1:{$lastColumn}{$this->totalRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A1:{$lastColumn}{$this->totalRow}")->getAlignment()->setWrapText(true);
                $sheet->getColumnDimension('A')->setWidth(19);
                $sheet->getColumnDimension('C')->setWidth(28);
                $sheet->getColumnDimension('D')->setWidth(25);
                $sheet->getColumnDimension('E')->setWidth(28);
            },
        ];
    }

    private function build(): void
    {
        $project = $this->report['project'];
        $totals = $this->report['totals'];
        $this->rows = [
            [$this->title().' - '.$project->title],
            ['Gerado em '.now()->format('d/m/Y H:i').' | Valores financeiros calculados somente pelas distribuições.'],
            [],
            self::HEADINGS,
        ];

        foreach ($this->report['groups'] as $group) {
            $this->rows[] = [$group['title'].($group['subtitle'] ? ' - '.$group['subtitle'] : '')];
            $this->groupRows[] = count($this->rows);

            foreach ($group['deliveries'] as $delivery) {
                $parentRow = count($this->rows) + 1;
                $firstChild = $parentRow + 1;
                $lastChild = $firstChild + $delivery['distributions']->count() - 1;
                $hasChildren = $lastChild >= $firstChild;

                $this->rows[] = [
                    'Entrega #'.$delivery['id'],
                    $delivery['date'],
                    $delivery['associate'],
                    $delivery['product'],
                    null,
                    $this->report['type'] === 'customer' ? null : $delivery['received_quantity'],
                    $hasChildren ? "=SUM(G{$firstChild}:G{$lastChild})" : 0,
                    $delivery['unit'],
                    null,
                    $hasChildren ? "=SUM(J{$firstChild}:J{$lastChild})" : 0,
                    $hasChildren ? "=SUM(K{$firstChild}:K{$lastChild})" : 0,
                    $hasChildren ? "=SUM(L{$firstChild}:L{$lastChild})" : 0,
                    $delivery['status'],
                ];
                $this->parentRows[] = $parentRow;

                foreach ($delivery['distributions'] as $distribution) {
                    $this->rows[] = [
                        '  Distribuição #'.$distribution['id'],
                        $delivery['date'],
                        $delivery['associate'],
                        $delivery['product'],
                        $distribution['customer'],
                        null,
                        $distribution['quantity'],
                        $delivery['unit'],
                        $distribution['unit_price'],
                        $distribution['gross'],
                        $distribution['fees'],
                        $distribution['net'],
                        $distribution['status'],
                    ];
                }
            }
        }

        $dataEnd = count($this->rows);
        $parentRows = collect($this->parentRows);
        $parentFormula = fn (string $column): string => $parentRows->isEmpty()
            ? '0'
            : '='.$parentRows->map(fn (int $row) => "{$column}{$row}")->implode('+');

        $this->rows[] = [
            'TOTAL GERAL', null, null, null, null,
            $this->report['type'] === 'customer' ? $totals['received_quantity'] : $parentFormula('F'),
            $parentFormula('G'), null, null,
            $parentFormula('J'),
            $parentFormula('K'),
            $parentFormula('L'),
            null,
        ];
        $this->totalRow = count($this->rows);

        if ($dataEnd < $this->headingRow + 1) {
            $this->totalRow = $this->headingRow + 1;
        }
    }
}
