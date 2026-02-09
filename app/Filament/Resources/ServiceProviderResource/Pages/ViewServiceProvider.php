<?php

namespace App\Filament\Resources\ServiceProviderResource\Pages;

use App\Filament\Resources\ServiceProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewServiceProvider extends ViewRecord
{
    protected static string $resource = ServiceProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('generate_statement')
                ->label('Extrato de Serviços')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('start_date')
                        ->label('Data Início')
                        ->required()
                        ->default(now()->startOfMonth()),
                    \Filament\Forms\Components\DatePicker::make('end_date')
                        ->label('Data Fim')
                        ->required()
                        ->default(now()->endOfMonth()),
                ])
                ->action(function (array $data) {
                    $record = $this->record;
                    $works = $record->works()
                        ->whereBetween('work_date', [$data['start_date'], $data['end_date']])
                        ->with(['serviceOrder', 'associate'])
                        ->orderBy('work_date')
                        ->get();

                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.service-provider-statement', [
                        'provider' => $record,
                        'works' => $works,
                        'start_date' => \Carbon\Carbon::parse($data['start_date'])->format('d/m/Y'),
                        'end_date' => \Carbon\Carbon::parse($data['end_date'])->format('d/m/Y'),
                        'total' => $works->sum('total_value'),
                        'total_pending' => $works->where('payment_status', 'pendente')->sum('total_value'),
                        'total_paid' => $works->where('payment_status', 'pago')->sum('total_value'),
                        'generated_at' => now()->format('d/m/Y H:i'),
                    ]);

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, 'extrato-' . \Illuminate\Support\Str::slug($record->name) . '-' . now()->format('Y-m') . '.pdf');
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Dados Pessoais')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Nome')
                                    ->size('lg')
                                    ->weight('bold'),
                                Infolists\Components\TextEntry::make('cpf')
                                    ->label('CPF'),
                                Infolists\Components\TextEntry::make('type')
                                    ->label('Função')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => match($state) {
                                        'tratorista' => 'Tratorista',
                                        'motorista' => 'Motorista',
                                        'diarista' => 'Diarista',
                                        'tecnico' => 'Técnico',
                                        'consultor' => 'Consultor',
                                        default => 'Outro',
                                    }),
                            ]),
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('phone')
                                    ->label('Telefone')
                                    ->icon('heroicon-o-phone'),
                                Infolists\Components\TextEntry::make('email')
                                    ->label('E-mail')
                                    ->icon('heroicon-o-envelope'),
                                Infolists\Components\TextEntry::make('city')
                                    ->label('Cidade'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Valores e Resumo')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('hourly_rate')
                                    ->label('Valor/Hora')
                                    ->money('BRL'),
                                Infolists\Components\TextEntry::make('daily_rate')
                                    ->label('Valor/Diária')
                                    ->money('BRL'),
                                Infolists\Components\TextEntry::make('total_pending_value')
                                    ->label('Total Pendente')
                                    ->state(fn ($record) => 'R$ ' . number_format($record->total_pending, 2, ',', '.'))
                                    ->color('danger')
                                    ->weight('bold')
                                    ->size('lg'),
                                Infolists\Components\TextEntry::make('total_paid_value')
                                    ->label('Total Pago')
                                    ->state(fn ($record) => 'R$ ' . number_format($record->total_paid, 2, ',', '.'))
                                    ->color('success')
                                    ->weight('bold'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Dados Bancários')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('bank_name')
                                    ->label('Banco')
                                    ->placeholder('Não informado'),
                                Infolists\Components\TextEntry::make('bank_agency')
                                    ->label('Agência')
                                    ->placeholder('Não informado'),
                                Infolists\Components\TextEntry::make('bank_account')
                                    ->label('Conta')
                                    ->placeholder('Não informado'),
                                Infolists\Components\TextEntry::make('pix_key')
                                    ->label('Chave PIX')
                                    ->placeholder('Não informado'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
