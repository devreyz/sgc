<?php

namespace App\Exports;

use App\Models\SalesProject;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectDeliveriesExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize
{
    protected SalesProject $project;

    public function __construct(SalesProject $project)
    {
        $this->project = $project;
    }

    public function collection()
    {
        return $this->project->deliveries()
            ->with(['associate.user', 'product'])
            ->orderBy('delivery_date')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Data',
            'Associado',
            'CPF',
            'Produto',
            'Quantidade',
            'Unidade',
            'Preço Unit.',
            'Valor Bruto',
            'Retenção',
            'Valor Líquido',
            'Status',
        ];
    }

    public function map($delivery): array
    {
        return [
            $delivery->delivery_date?->format('d/m/Y'),
            $delivery->associate->user->name ?? 'N/A',
            $delivery->associate->cpf_cnpj ?? 'N/A',
            $delivery->product->name ?? 'N/A',
            number_format($delivery->quantity, 2, ',', '.'),
            $delivery->product->unit ?? 'kg',
            'R$ ' . number_format($delivery->unit_price, 2, ',', '.'),
            'R$ ' . number_format($delivery->gross_value, 2, ',', '.'),
            'R$ ' . number_format($delivery->retention_value, 2, ',', '.'),
            'R$ ' . number_format($delivery->net_value, 2, ',', '.'),
            $delivery->status->label(),
        ];
    }

    public function title(): string
    {
        return 'Entregas';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
