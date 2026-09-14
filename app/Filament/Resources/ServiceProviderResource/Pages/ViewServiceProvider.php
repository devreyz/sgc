<?php

namespace App\Filament\Resources\ServiceProviderResource\Pages;

use App\Filament\Pages\ServiceOrdersPaymentReport;
use App\Filament\Resources\ServiceProviderResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceProvider extends ViewRecord
{
    protected static string $resource = ServiceProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('accountability')
                ->label('Prestação de contas')
                ->icon('heroicon-o-document-chart-bar')
                ->url(fn (): string => ServiceOrdersPaymentReport::getUrl())
                ->visible(fn (): bool => auth()->user()?->checkPermissionTo('view_service_reports') ?? false),
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
                                    ->formatStateUsing(fn ($state) => match ($state) {
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
                                Infolists\Components\TextEntry::make('remuneration_help')
                                    ->label('Como a remuneração é definida')
                                    ->state('Por versão do serviço, com valor fixo, quantidade × tarifa ou percentual. A forma de pagamento é escolhida somente na baixa.')
                                    ->columnSpan(2),
                                Infolists\Components\TextEntry::make('service_due')
                                    ->label('Devido em serviços')
                                    ->state(fn ($record) => 'R$ '.number_format((float) $record->serviceObligations()->where('direction', 'payable')->get()->sum('total_amount'), 2, ',', '.'))
                                    ->weight('bold'),
                                Infolists\Components\TextEntry::make('service_balance')
                                    ->label('Saldo a pagar')
                                    ->state(fn ($record) => 'R$ '.number_format((float) $record->serviceObligations()->where('direction', 'payable')->get()->sum('balance'), 2, ',', '.'))
                                    ->color('warning')
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
