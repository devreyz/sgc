<?php

namespace App\Exports;

use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class CustomerBillingReceiptExport implements FromArray, WithTitle, WithStyles, ShouldAutoSize, WithEvents
{
    // ── Colunas disponíveis ───────────────────────────────────────────────────
    public const AVAILABLE_COLUMNS = [
        'delivery_date'  => 'Data Entrega',
        'product'        => 'Produto',
        'customer'       => 'Cliente',
        'associate'      => 'Produtor',
        'quantity'       => 'Quantidade',
        'unit'           => 'Unidade',
        'unit_price'     => 'Preço Unit. (R$)',
        'gross'          => 'Valor Bruto (R$)',
        'fees'           => 'Deduções (R$)',
        'net'            => 'Valor Líquido (R$)',
        'receipt_number' => 'Nº Cobrança',
        'issued_at'      => 'Data de Emissão',
        'project'        => 'Projeto',
        'billing_status' => 'Status Dist.',
        'receipt_status' => 'Status Cobrança',
    ];

    public const DEFAULT_COLUMNS = [
        'delivery_date', 'product', 'customer', 'associate',
        'quantity', 'unit', 'unit_price', 'gross', 'fees', 'net',
    ];

    // Colunas numéricas (recebem formatação de número/moeda)
    private const NUMERIC_COLS = ['quantity', 'unit_price', 'gross', 'fees', 'net'];

    protected CustomerBillingReceipt $receipt;
    protected array $selectedColumns;
    protected array $allRows = [];

    // Índices de linha (1-based)
    protected int $titleRow  = 1;
    protected int $metaRow   = 2;
    protected int $spacerRow = 3;
    protected int $headRow   = 4;
    protected int $dataStart = 5;
    protected int $dataCount = 0;
    protected int $footerRow = 0;
    protected int $colCount  = 0;

    public function __construct(CustomerBillingReceipt $receipt, array $selectedColumns)
    {
        $this->receipt         = $receipt;
        $this->selectedColumns = array_values($selectedColumns);
        $this->colCount        = count($this->selectedColumns);
        $this->build();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Constrói o array de linhas
    // ─────────────────────────────────────────────────────────────────────────

    protected function build(): void
    {
        $receipt  = $this->receipt;
        $cols     = $this->selectedColumns;
        $colCount = $this->colCount;

        $customerName = $receipt->customer?->name
            ?? $receipt->organization?->name
            ?? '—';
        $projectName = $receipt->project?->title ?? '—';
        $issuedAt    = $receipt->issued_at?->format('d/m/Y') ?? '—';
        $totalNet    = 'R$ ' . number_format((float) ($receipt->total_net ?? 0), 2, ',', '.');

        // ── Linha de título (será mesclada) ──────────────────────────────────
        $pad = array_fill(0, max(0, $colCount - 1), null);
        $this->allRows[] = array_merge(
            ['Comprovante de Cobrança — Nº ' . $receipt->formatted_number],
            $pad
        );

        // ── Linha de metadados ───────────────────────────────────────────────
        $meta = "Emissão: {$issuedAt}   |   Cliente/Org.: {$customerName}   |   Projeto: {$projectName}   |   Valor Líquido: {$totalNet}";
        $this->allRows[] = array_merge([$meta], $pad);

        // ── Spacer ───────────────────────────────────────────────────────────
        $this->allRows[] = array_fill(0, $colCount, null);

        // ── Cabeçalho das colunas ────────────────────────────────────────────
        $this->allRows[] = array_map(
            fn ($col) => self::AVAILABLE_COLUMNS[$col] ?? $col,
            $cols
        );

        // ── Linhas de dados ──────────────────────────────────────────────────
        $deliveryIds   = $receipt->delivery_ids ?? [];
        $distributions = empty($deliveryIds)
            ? collect()
            : ProductionDelivery::whereIn('id', $deliveryIds)
                ->with(['product', 'customer', 'associate.user'])
                ->orderBy('delivery_date')
                ->get();

        $totalGross = $distributions->sum(fn ($d) => (float) $d->quantity * (float) $d->unit_price);
        $totalFees  = (float) ($receipt->total_fees ?? 0);

        foreach ($distributions as $d) {
            $gross = (float) $d->quantity * (float) $d->unit_price;
            $fees  = $totalGross > 0
                ? round($gross / $totalGross * $totalFees, 4)
                : 0.0;
            $net = $gross - $fees;

            $row = [];
            foreach ($cols as $col) {
                $row[] = match ($col) {
                    'receipt_number' => $receipt->formatted_number,
                    'issued_at'      => $receipt->issued_at?->format('d/m/Y') ?? '—',
                    'project'        => $receipt->project?->title ?? '—',
                    'delivery_date'  => $d->delivery_date?->format('d/m/Y') ?? '—',
                    'product'        => $d->product?->name ?? '—',
                    'customer'       => $d->customer?->name ?? '—',
                    'associate'      => $d->associate?->user?->name ?? '—',
                    'quantity'       => (float) $d->quantity,
                    'unit'           => $d->product?->unit ?? 'kg',
                    'unit_price'     => (float) $d->unit_price,
                    'gross'          => $gross,
                    'fees'           => $fees,
                    'net'            => $net,
                    'billing_status' => $d->billing_status?->getLabel() ?? '—',
                    'receipt_status' => $receipt->status?->getLabel() ?? '—',
                    default          => '—',
                };
            }
            $this->allRows[] = $row;
        }

        $this->dataCount = $distributions->count();
        $this->footerRow = $this->dataStart + $this->dataCount;

        // ── Linha de totais (rodapé) ─────────────────────────────────────────
        $footer        = [];
        $labelSet      = false;
        $qtyTotal      = $distributions->sum(fn ($d) => (float) $d->quantity);
        $netFromReceipt = (float) ($receipt->total_net ?? ($totalGross - $totalFees));

        foreach ($cols as $col) {
            if (! $labelSet && ! in_array($col, self::NUMERIC_COLS)) {
                // Primeira coluna de texto → rótulo "TOTAL"
                $footer[] = 'TOTAL';
                $labelSet = true;
            } elseif ($col === 'quantity') {
                $footer[] = $qtyTotal;
            } elseif ($col === 'gross') {
                $footer[] = $totalGross;
            } elseif ($col === 'fees') {
                $footer[] = $totalFees > 0 ? $totalFees : null;
            } elseif ($col === 'net') {
                $footer[] = $netFromReceipt;
            } else {
                // unit_price e outras colunas numéricas sem soma definida
                $footer[] = null;
            }
        }

        // Se todas as colunas são numéricas, coloca "TOTAL" na primeira
        if (! $labelSet && ! empty($footer)) {
            $footer[0] = 'TOTAL';
        }

        $this->allRows[] = $footer;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Interface maatwebsite/excel
    // ─────────────────────────────────────────────────────────────────────────

    public function array(): array
    {
        return $this->allRows;
    }

    public function title(): string
    {
        return 'Cobrança ' . str_replace(['/', '\\', '?', '*', '[', ']', ':'], '-', $this->receipt->formatted_number ?? 'S-N');
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Título
            $this->titleRow => [
                'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
            ],
            // Metadados
            $this->metaRow => [
                'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '475569']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ],
            // Cabeçalho das colunas
            $this->headRow => [
                'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true,
                ],
                'borders' => [
                    'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '475569']],
                ],
            ],
            // Rodapé de totais
            $this->footerRow => [
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '0F172A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                'borders' => [
                    'top'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '64748B']],
                    'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '64748B']],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastCol = Coordinate::stringFromColumnIndex($this->colCount);
                $footer  = $this->footerRow;

                // ── Mesclar título e metadados ────────────────────────────────
                if ($this->colCount > 1) {
                    $sheet->mergeCells("A{$this->titleRow}:{$lastCol}{$this->titleRow}");
                    $sheet->mergeCells("A{$this->metaRow}:{$lastCol}{$this->metaRow}");
                }

                // ── Alturas de linha ──────────────────────────────────────────
                $sheet->getRowDimension($this->titleRow)->setRowHeight(28);
                $sheet->getRowDimension($this->metaRow)->setRowHeight(20);
                $sheet->getRowDimension($this->spacerRow)->setRowHeight(6);
                $sheet->getRowDimension($this->headRow)->setRowHeight(22);
                $sheet->getRowDimension($footer)->setRowHeight(22);

                // ── Congelar painel abaixo do cabeçalho ────────────────────────
                $sheet->freezePane('A' . ($this->headRow + 1));

                // ── Cores alternadas nas linhas de dados ──────────────────────
                for ($row = $this->dataStart; $row < $footer; $row++) {
                    $bg = ($row % 2 === 0) ? 'FFFFFF' : 'F8FAFC';
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($bg);

                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")
                        ->getBorders()->getBottom()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('E2E8F0');
                }

                // ── Formatação numérica e alinhamento por coluna ─────────────
                $colIdx = 1;
                foreach ($this->selectedColumns as $col) {
                    $letter    = Coordinate::stringFromColumnIndex($colIdx);
                    $dataRange = "{$letter}{$this->dataStart}:{$letter}{$footer}";

                    if ($col === 'quantity') {
                        $sheet->getStyle($dataRange)
                            ->getNumberFormat()
                            ->setFormatCode('#,##0.000');
                        $sheet->getStyle("{$letter}{$this->headRow}:{$letter}{$footer}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    } elseif (in_array($col, ['unit_price', 'gross', 'fees', 'net'])) {
                        $sheet->getStyle($dataRange)
                            ->getNumberFormat()
                            ->setFormatCode('"R$ "#,##0.00');
                        $sheet->getStyle("{$letter}{$this->headRow}:{$letter}{$footer}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                    $colIdx++;
                }

                // ── Bordas da tabela (da linha de header ao rodapé) ────────────
                $tableRange = "A{$this->headRow}:{$lastCol}{$footer}";

                // Borda interna vertical (entre colunas)
                $sheet->getStyle($tableRange)
                    ->getBorders()->getVertical()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB('CBD5E1');

                // Borda externa da tabela
                $sheet->getStyle($tableRange)
                    ->getBorders()->getOutline()
                    ->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB('94A3B8');

                // ── Indentação nas células de texto do cabeçalho ─────────────
                $sheet->getStyle("A{$this->titleRow}")
                    ->getAlignment()->setIndent(1);
                $sheet->getStyle("A{$this->metaRow}")
                    ->getAlignment()->setIndent(1);
            },
        ];
    }
}
