<?php

namespace App\Filament\Resources\SalesProjectResource\RelationManagers;

use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use App\Models\Associate;
use App\Models\AssociateReceipt;
use App\Models\BankAccount;
use App\Services\AssociateReceiptService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AssociatePaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'associateReceipts';

    protected static ?string $title = 'Comprovantes de Associados';

    protected static ?string $modelLabel = 'Comprovante';

    protected static ?string $pluralModelLabel = 'Comprovantes';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('formatted_number')
                    ->label('Nº Comprovante')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('associate.user.name')
                    ->label('Associado')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ReceiptStatus ? $state->getLabel() : $state)
                    ->color(fn ($state) => $state instanceof ReceiptStatus ? $state->getColor() : 'gray'),

                Tables\Columns\TextColumn::make('total_gross')
                    ->label('Bruto')
                    ->money('BRL')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('total_net')
                    ->label('Líquido')
                    ->money('BRL')
                    ->color('success')
                    ->weight('bold')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Emitido em')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Pago em')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('pay_associate')
                    ->label('Registrar Pagamento')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form(function () {
                        $project  = $this->ownerRecord;
                        $tenantId = session('tenant_id');

                        // Associados que têm comprovantes pendentes neste projeto
                        $pendingReceipts = AssociateReceipt::where('tenant_id', $tenantId)
                            ->where('sales_project_id', $project->id)
                            ->whereIn('status', [ReceiptStatus::DRAFT->value, ReceiptStatus::PENDING_PAYMENT->value])
                            ->with('associate.user')
                            ->get();

                        $associateIds = $pendingReceipts->pluck('associate_id')->unique();

                        $associateOptions = Associate::whereIn('id', $associateIds)
                            ->with('user')
                            ->get()
                            ->mapWithKeys(fn ($a) => [
                                $a->id => optional($a->user)->name ?? "Associado #{$a->id}",
                            ])
                            ->toArray();

                        return [
                            Forms\Components\Section::make('Associado')
                                ->schema([
                                    Forms\Components\Select::make('associate_id')
                                        ->label('Associado')
                                        ->options($associateOptions)
                                        ->required()
                                        ->searchable()
                                        ->live()
                                        ->afterStateUpdated(fn (Set $set) => $set('receipt_id', null))
                                        ->helperText('Apenas associados com comprovantes pendentes de pagamento.'),
                                ])
                                ->columns(1),

                            Forms\Components\Section::make('Comprovante a Pagar')
                                ->schema([
                                    Forms\Components\Select::make('receipt_id')
                                        ->label('Comprovante')
                                        ->options(function (Get $get) use ($project, $tenantId) {
                                            $associateId = $get('associate_id');
                                            if (! $associateId) {
                                                return [];
                                            }

                                            return AssociateReceipt::where('tenant_id', $tenantId)
                                                ->where('sales_project_id', $project->id)
                                                ->where('associate_id', $associateId)
                                                ->whereIn('status', [ReceiptStatus::DRAFT->value, ReceiptStatus::PENDING_PAYMENT->value])
                                                ->get()
                                                ->mapWithKeys(fn ($r) => [
                                                    $r->id => sprintf(
                                                        'Nº %s — %s — R$ %s (%s)',
                                                        $r->formatted_number,
                                                        $r->issued_at?->format('d/m/Y') ?? '—',
                                                        $r->total_net ? number_format((float) $r->total_net, 2, ',', '.') : '?',
                                                        $r->status?->getLabel() ?? 'Rascunho'
                                                    ),
                                                ])
                                                ->toArray();
                                        })
                                        ->required()
                                        ->searchable()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                            if (! $state) {
                                                $set('amount_paid', null);
                                                return;
                                            }
                                            $receipt = AssociateReceipt::find($state);
                                            if ($receipt?->total_net) {
                                                $set('amount_paid', number_format((float) $receipt->total_net, 2, '.', ''));
                                            }
                                        })
                                        ->helperText('Selecione o comprovante que será pago.'),

                                    Forms\Components\Placeholder::make('receipt_summary')
                                        ->label('Resumo do Comprovante')
                                        ->content(function (Get $get) {
                                            $id = $get('receipt_id');
                                            if (! $id) {
                                                return '—';
                                            }
                                            $r = AssociateReceipt::find($id);
                                            if (! $r) {
                                                return '—';
                                            }
                                            $distCount = count($r->delivery_ids ?? []);
                                            $gross     = $r->total_gross ? 'R$ '.number_format((float) $r->total_gross, 2, ',', '.') : '—';
                                            $net       = $r->total_net   ? 'R$ '.number_format((float) $r->total_net, 2, ',', '.') : '—';
                                            return new \Illuminate\Support\HtmlString(
                                                "<div style='font-size:.85rem;line-height:1.6'>"
                                                ."<strong>Distribuições:</strong> {$distCount} &nbsp;|&nbsp; "
                                                ."<strong>Bruto:</strong> {$gross} &nbsp;|&nbsp; "
                                                ."<strong>Líquido:</strong> <span style='color:#059669;font-weight:700'>{$net}</span>"
                                                .'</div>'
                                            );
                                        })
                                        ->live(),
                                ])
                                ->visible(fn (Get $get) => (bool) $get('associate_id')),

                            Forms\Components\Section::make('Dados do Pagamento')
                                ->schema([
                                    Forms\Components\DatePicker::make('payment_date')
                                        ->label('Data do Pagamento')
                                        ->required()
                                        ->default(today())
                                        ->displayFormat('d/m/Y'),

                                    Forms\Components\Select::make('payment_method')
                                        ->label('Forma de Pagamento')
                                        ->options(PaymentMethod::class)
                                        ->required()
                                        ->default(PaymentMethod::PIX->value),

                                    Forms\Components\Select::make('bank_account_id')
                                        ->label('Conta de Débito (Saída)')
                                        ->options(fn () => BankAccount::where('status', true)
                                            ->where('tenant_id', session('tenant_id'))
                                            ->pluck('name', 'id'))
                                        ->searchable()
                                        ->helperText('Conta de onde sairá o pagamento'),

                                    Forms\Components\TextInput::make('amount_paid')
                                        ->label('Valor Pago')
                                        ->numeric()
                                        ->required()
                                        ->prefix('R$')
                                        ->helperText('Preenchido automaticamente com o líquido do comprovante.'),

                                    Forms\Components\TextInput::make('document_number')
                                        ->label('Nº do Documento / Comprovante')
                                        ->maxLength(100),

                                    Forms\Components\Textarea::make('payment_notes')
                                        ->label('Observações')
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ])
                                ->visible(fn (Get $get) => (bool) $get('receipt_id'))
                                ->columns(2),
                        ];
                    })
                    ->modalWidth('4xl')
                    ->modalHeading('Registrar Pagamento a Associado')
                    ->modalSubmitActionLabel('Confirmar Pagamento')
                    ->action(function (array $data) {
                        $receiptId = $data['receipt_id'] ?? null;
                        if (! $receiptId) {
                            Notification::make()->warning()->title('Selecione um comprovante.')->send();
                            return;
                        }

                        $receipt = AssociateReceipt::find($receiptId);
                        if (! $receipt || $receipt->isLocked()) {
                            Notification::make()->warning()->title('Comprovante inválido ou já pago.')->send();
                            return;
                        }

                        try {
                            app(AssociateReceiptService::class)->payReceipt($receipt, [
                                'payment_date'   => $data['payment_date'],
                                'payment_method' => $data['payment_method'],
                                'bank_account_id'=> $data['bank_account_id'] ?? null,
                                'amount_paid'    => $data['amount_paid'],
                                'document_number'=> $data['document_number'] ?? null,
                                'payment_notes'  => $data['payment_notes'] ?? null,
                                'paid_by'        => Auth::id(),
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Pagamento registrado com sucesso!')
                                ->body("Comprovante {$receipt->formatted_number} marcado como PAGO.")
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Erro ao registrar pagamento')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('Detalhes')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalWidth('2xl')
                    ->modalHeading(fn (AssociateReceipt $record) => "Comprovante {$record->formatted_number}")
                    ->modalContent(function (AssociateReceipt $record) {
                        $deliveries = \App\Models\ProductionDelivery::whereIn(
                            'id', array_map('intval', $record->delivery_ids ?? [])
                        )->with('product', 'customer')->orderBy('delivery_date')->get();

                        $rows = $deliveries->map(fn ($d) => sprintf(
                            '<tr><td style="padding:.4rem">%s</td><td style="padding:.4rem">%s</td><td style="padding:.4rem">%s</td>'
                            .'<td style="padding:.4rem;text-align:right">%s</td>'
                            .'<td style="padding:.4rem;text-align:right;color:#059669;font-weight:600">R$ %s</td></tr>',
                            $d->delivery_date?->format('d/m/Y') ?? '—',
                            optional($d->product)->name ?? '—',
                            optional($d->customer)->trade_name ?? optional($d->customer)->name ?? '—',
                            number_format($d->quantity, 3, ',', '.'),
                            number_format($d->net_value, 2, ',', '.')
                        ))->implode('');

                        $gross = $record->total_gross ? 'R$ '.number_format((float) $record->total_gross, 2, ',', '.') : '—';
                        $net   = $record->total_net   ? 'R$ '.number_format((float) $record->total_net, 2, ',', '.') : '—';
                        $paid  = $record->paid_at ? $record->paid_at->format('d/m/Y') : '—';

                        $html = '<div style="font-size:.85rem">'
                            .'<div style="margin-bottom:.75rem;padding:.5rem .75rem;background:#f3f4f6;border-radius:4px">'
                            .'<strong>Status:</strong> '.($record->status?->getLabel() ?? '—')
                            .' &nbsp;|&nbsp; <strong>Bruto:</strong> '.$gross
                            .' &nbsp;|&nbsp; <strong>Líquido:</strong> '.$net
                            .($record->paid_at ? ' &nbsp;|&nbsp; <strong>Pago em:</strong> '.$paid : '')
                            .'</div>'
                            .'<table style="width:100%;border-collapse:collapse">'
                            .'<thead><tr style="background:#f3f4f6;font-weight:700">'
                            .'<th style="padding:.4rem;text-align:left">Data</th>'
                            .'<th style="padding:.4rem;text-align:left">Produto</th>'
                            .'<th style="padding:.4rem;text-align:left">Cliente</th>'
                            .'<th style="padding:.4rem;text-align:right">Qtd</th>'
                            .'<th style="padding:.4rem;text-align:right">Líquido DB</th>'
                            .'</tr></thead><tbody>'.$rows.'</tbody></table></div>';

                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
            ]);
    }
}
