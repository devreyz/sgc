<?php

namespace App\Exports;

use App\Models\ProductionDelivery;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DeliveriesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected array $columns;
    protected ?int $projectId;
    protected ?string $statusFilter;

    protected $columnLabels = [
        'delivery_date' => 'Data da Entrega',
        'project' => 'Projeto',
        'associate' => 'Produtor',
        'product' => 'Produto',
        'quantity' => 'Quantidade',
        'unit_price' => 'Preço Unitário',
        'gross_value' => 'Valor Bruto',
        'admin_fee' => 'Taxa Admin',
        'net_value' => 'Valor Líquido',
        'quality' => 'Qualidade',
        'status' => 'Status',
    ];

    public function __construct(array $columns = [], ?int $projectId = null, ?string $statusFilter = null)
    {
        $this->columns = $columns ?: array_keys($this->columnLabels);
        $this->projectId = $projectId;
        $this->statusFilter = $statusFilter;
    }

    public function collection()
    {
        $query = ProductionDelivery::with(['salesProject', 'associate.user', 'product']);
        
        if ($this->projectId) {
            $query->where('sales_project_id', $this->projectId);
        }
        
        if ($this->statusFilter && $this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }
        
        return $query->orderBy('delivery_date', 'desc')->get();
    }

    public function headings(): array
    {
        $headings = [];
        foreach ($this->columns as $column) {
            $headings[] = $this->columnLabels[$column] ?? $column;
        }
        return $headings;
    }

    public function map($row): array
    {
        $data = [];
        
        foreach ($this->columns as $column) {
            $data[] = match($column) {
                'delivery_date' => $row->delivery_date?->format('d/m/Y'),
                'project' => $row->salesProject?->title,
                'associate' => $row->associate?->user?->name,
                'product' => $row->product?->name,
                'quantity' => number_format($row->quantity, 2, ',', '.') . ' ' . ($row->product?->unit ?? ''),
                'unit_price' => 'R$ ' . number_format($row->unit_price, 2, ',', '.'),
                'gross_value' => 'R$ ' . number_format($row->gross_value, 2, ',', '.'),
                'admin_fee' => 'R$ ' . number_format($row->admin_fee_amount, 2, ',', '.'),
                'net_value' => 'R$ ' . number_format($row->net_value, 2, ',', '.'),
                'quality' => $row->quality_grade,
                'status' => $row->status?->label() ?? $row->status,
                default => '',
            };
        }
        
        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}
