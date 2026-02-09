<?php

namespace App\Exports;

use App\Models\Associate;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AssociateLedgerExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize
{
    protected Associate $associate;
    protected ?string $startDate;
    protected ?string $endDate;

    public function __construct(Associate $associate, ?string $startDate = null, ?string $endDate = null)
    {
        $this->associate = $associate;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        $query = $this->associate->ledgerEntries()
            ->orderBy('transaction_date');

        if ($this->startDate) {
            $query->whereDate('transaction_date', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('transaction_date', '<=', $this->endDate);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Data',
            'Tipo',
            'Categoria',
            'Descrição',
            'Valor',
            'Saldo',
        ];
    }

    public function map($entry): array
    {
        $signal = $entry->type->value === 'credit' ? '+' : '-';
        
        return [
            $entry->transaction_date?->format('d/m/Y'),
            $entry->type->label(),
            $entry->category->getLabel(),
            $entry->description,
            $signal . ' R$ ' . number_format($entry->amount, 2, ',', '.'),
            'R$ ' . number_format($entry->balance_after, 2, ',', '.'),
        ];
    }

    public function title(): string
    {
        return 'Extrato';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
