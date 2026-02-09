<?php

namespace App\Filament\Resources\CashMovementResource\Pages;

use App\Filament\Resources\CashMovementResource;
use App\Models\CashMovement;
use App\Models\BankAccount;
use App\Enums\CashMovementType;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Filament\Forms;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericExport;
use Illuminate\Support\Facades\Response;

class ListCashMovements extends ListRecords
{
    protected static string $resource = CashMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_report')
                ->label('Relatório de Caixa')
                ->icon('heroicon-o-document-chart-bar')
                ->color('primary')
                ->modalHeading('Gerar Relatório de Caixa')
                ->modalWidth('lg')
                ->form([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\DatePicker::make('from')
                                ->label('Data Inicial')
                                ->default(now()->startOfMonth())
                                ->required(),
                            
                            Forms\Components\DatePicker::make('until')
                                ->label('Data Final')
                                ->default(now()->endOfMonth())
                                ->required(),
                        ]),
                    
                    Forms\Components\Select::make('bank_account_id')
                        ->label('Conta Bancária')
                        ->options(BankAccount::pluck('name', 'id'))
                        ->placeholder('Todas as contas')
                        ->searchable(),

                    Forms\Components\Select::make('type')
                        ->label('Tipo de Movimento')
                        ->options(CashMovementType::class)
                        ->placeholder('Todos os tipos'),

                    Forms\Components\Radio::make('format')
                        ->label('Formato')
                        ->options([
                            'pdf' => 'PDF',
                            'excel' => 'Excel',
                        ])
                        ->default('pdf')
                        ->required()
                        ->inline(),
                ])
                ->action(function (array $data) {
                    $query = CashMovement::query()
                        ->with(['bankAccount', 'chartAccount', 'creator'])
                        ->whereBetween('movement_date', [$data['from'], $data['until']]);

                    if (isset($data['bank_account_id']) && $data['bank_account_id']) {
                        $query->where('bank_account_id', $data['bank_account_id']);
                    }

                    if (isset($data['type']) && $data['type']) {
                        $query->where('type', $data['type']);
                    }

                    $movements = $query->orderBy('movement_date', 'asc')->get();

                    $totals = [
                        'income' => $movements->where('type', CashMovementType::INCOME)->sum('amount'),
                        'expense' => $movements->where('type', CashMovementType::EXPENSE)->sum('amount'),
                        'transfer' => $movements->where('type', CashMovementType::TRANSFER)->sum('amount'),
                        'balance' => $movements->where('type', CashMovementType::INCOME)->sum('amount') - 
                                   $movements->where('type', CashMovementType::EXPENSE)->sum('amount'),
                    ];

                    $period = [
                        'from' => \Carbon\Carbon::parse($data['from'])->format('d/m/Y'),
                        'until' => \Carbon\Carbon::parse($data['until'])->format('d/m/Y'),
                    ];

                    if ($data['format'] === 'pdf') {
                        $pdf = Pdf::loadView('pdf.cash-movement-report', [
                            'movements' => $movements,
                            'totals' => $totals,
                            'period' => $period,
                            'bank_account' => isset($data['bank_account_id']) && $data['bank_account_id'] 
                                ? BankAccount::find($data['bank_account_id'])->name 
                                : 'Todas',
                            'movement_type' => isset($data['type']) && $data['type']
                                ? CashMovementType::from($data['type'])->getLabel()
                                : 'Todos',
                            'generated_at' => now()->format('d/m/Y H:i'),
                        ])->setPaper('a4', 'landscape');

                        return Response::streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'relatorio-caixa-' . now()->format('Y-m-d') . '.pdf');
                    } else {
                        return Excel::download(
                            new GenericExport(
                                $movements,
                                [
                                    'movement_date' => 'Data',
                                    'type' => 'Tipo',
                                    'description' => 'Descrição',
                                    'bank_account.name' => 'Conta',
                                    'amount' => 'Valor',
                                    'balance_after' => 'Saldo',
                                    'payment_method' => 'Forma Pagto',
                                    'document_number' => 'Nº Doc',
                                ],
                                function ($item) {
                                    return [
                                        'movement_date' => $item->movement_date->format('d/m/Y'),
                                        'type' => $item->type->getLabel(),
                                        'description' => $item->description,
                                        'bank_account.name' => $item->bankAccount?->name,
                                        'amount' => $item->amount,
                                        'balance_after' => $item->balance_after,
                                        'payment_method' => $item->payment_method?->getLabel(),
                                        'document_number' => $item->document_number,
                                    ];
                                }
                            ),
                            'relatorio-caixa-' . now()->format('Y-m-d') . '.xlsx'
                        );
                    }
                }),
        ];
    }
}

