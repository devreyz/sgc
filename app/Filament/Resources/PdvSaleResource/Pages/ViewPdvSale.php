<?php

namespace App\Filament\Resources\PdvSaleResource\Pages;

use App\Filament\Resources\PdvSaleResource;
use App\Models\PdvSale;
use App\Services\PdvService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewPdvSale extends Page
{
    protected static string $resource = PdvSaleResource::class;
    protected static string $view = 'filament.pages.pdv-sale-view';

    public PdvSale $record;

    public function mount(int|string $record): void
    {
        $this->record = PdvSale::with(['items.product', 'payments', 'fiadoPayments.creator', 'customer', 'creator', 'cancelledByUser'])->findOrFail($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('Imprimir Comprovante')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('pdv.sale.receipt', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('pay_fiado')
                ->label('Receber Fiado')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->visible(fn () => $this->record->is_fiado && $this->record->fiado_remaining > 0)
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label('Valor a Receber (R$)')
                        ->numeric()
                        ->prefix('R$')
                        ->required()
                        ->default(fn () => number_format($this->record->fiado_remaining, 2, '.', ''))
                        ->minValue(0.01),
                    Forms\Components\Select::make('payment_method')
                        ->label('Forma de Pagamento')
                        ->options([
                            'dinheiro' => 'Dinheiro',
                            'pix' => 'PIX',
                            'cartao' => 'Cartão',
                            'transferencia' => 'Transferência',
                            'cheque' => 'Cheque',
                            'outro' => 'Outro',
                        ])
                        ->required()
                        ->default('dinheiro'),
                    Forms\Components\Textarea::make('notes')->label('Observações'),
                ])
                ->action(function (array $data) {
                    try {
                        app(PdvService::class)->payFiado($this->record, (float) $data['amount'], $data['payment_method'], $data['notes'] ?? null);
                        $this->record->refresh();
                        Notification::make()->title('Pagamento registrado com sucesso!')->success()->send();
                    } catch (\Exception $e) {
                        Notification::make()->title('Erro: ' . $e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('cancel')
                ->label('Cancelar Venda')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'completed')
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo do Cancelamento')
                        ->required()
                        ->minLength(5),
                ])
                ->action(function (array $data) {
                    try {
                        app(PdvService::class)->cancelSale($this->record, $data['reason']);
                        $this->record->refresh();
                        Notification::make()->title('Venda cancelada com sucesso.')->warning()->send();
                    } catch (\Exception $e) {
                        Notification::make()->title('Erro: ' . $e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('back')
                ->label('Voltar')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(PdvSaleResource::getUrl('index')),
        ];
    }

    public function getTitle(): string
    {
        return "Venda #{$this->record->code}";
    }
}
