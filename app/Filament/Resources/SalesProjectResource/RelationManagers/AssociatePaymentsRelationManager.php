<?php

namespace App\Filament\Resources\SalesProjectResource\RelationManagers;

use App\Enums\CashMovementType;
use App\Enums\DeliveryStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProjectPaymentStatus;
use App\Models\Associate;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\ProductionDelivery;
use App\Models\ProjectPayment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class AssociatePaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'associatePayments';

    protected static ?string $title = 'Pagamentos a Associados';

    protected static ?string $modelLabel = 'Pagamento';

    protected static ?string $pluralModelLabel = 'Pagamentos';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('description')
                ->label('Descrição')
                ->required()
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('payment_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('receipt_number')
                    ->label('Extrato Nº')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('associate.user.name')
                    ->label('Associado')
                    ->getStateUsing(fn ($record) => optional(optional($record->associate)->user)->name ?? '—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor Pago')
                    ->money('BRL')
                    ->color('success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('balance_remaining')
                    ->label('Saldo em Aberto')
                    ->money('BRL')
                    ->color(fn ($record) => $record->balance_remaining > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(fn ($state) => $state > 0 ? 'R$ ' . number_format($state, 2, ',', '.') : '—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->getLabel())
                    ->color(fn ($state) => $state->getColor()),

                Tables\Columns\IconColumn::make('finalized_at')
                    ->label('Faturado')
                    ->boolean()
                    ->getStateUsing(fn ($record) => !is_null($record->finalized_at))
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('distributions_count')
                    ->label('Distribs.')
                    ->getStateUsing(fn ($record) => $record->distributions()->count()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('pay_associate')
                    ->label('Pagar Associado')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form(function () {
                        $project = $this->ownerRecord;

                        // Associados que têm distribuições aprovadas não pagas neste projeto
                        $associateIds = ProductionDelivery::where('sales_project_id', $project->id)
                            ->where('status', DeliveryStatus::APPROVED)
                            ->whereNotNull('parent_delivery_id')
                            ->where('paid', false)
                            ->whereNotNull('associate_id')
                            ->pluck('associate_id')
                            ->unique();

                        $associateOptions = Associate::whereIn('id', $associateIds)
                            ->with('user')
                            ->get()
                            ->mapWithKeys(fn ($a) => [
                                $a->id => optional($a->user)->name ?? "Associado #{$a->id}",
                            ])
                            ->toArray();

                        return [
                            Forms\Components\Section::make('Associado e Período')
                                ->schema([
                                    Forms\Components\Select::make('associate_id')
                                        ->label('Associado')
                                        ->options($associateOptions)
                                        ->required()
                                        ->searchable()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                            // Reset seleção ao trocar associado
                                            $set('selected_distribution_ids', []);
                                            $set('amount_paid', null);
                                        })
                                        ->helperText('Apenas associados com distribuições aprovadas não pagas.'),
                                ])
                                ->columns(1),

                            Forms\Components\Section::make('Distribuições a Pagar')
                                ->schema([
                                    Forms\Components\CheckboxList::make('selected_distribution_ids')
                                        ->label('Selecione as distribuições')
                                        ->options(function (Get $get) {
                                            $associateId = $get('associate_id');
                                            if (!$associateId) return [];

                                            return ProductionDelivery::where('sales_project_id', $this->ownerRecord->id)
                                                ->where('associate_id', $associateId)
                                                ->where('status', DeliveryStatus::APPROVED)
                                                ->whereNotNull('parent_delivery_id')
                                                ->where('paid', false)
                                                ->with(['product', 'customer'])
                                                ->orderBy('delivery_date')
                                                ->get()
                                                ->mapWithKeys(fn ($d) => [
                                                    $d->id => sprintf(
                                                        '%s · %s · %s · R$ %s (Líq.)',
                                                        $d->delivery_date?->format('d/m/Y') ?? '—',
                                                        optional($d->product)->name ?? '—',
                                                        optional($d->customer)->trade_name ?? optional($d->customer)->name ?? '—',
                                                        number_format($d->net_value, 2, ',', '.')
                                                    ),
                                                ])
                                                ->toArray();
                                        })
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                            if (empty($state)) {
                                                $set('amount_paid', null);
                                                $set('_total_selected', null);
                                                return;
                                            }
                                            $total = ProductionDelivery::whereIn('id', $state)->sum('net_value');
                                            $set('amount_paid', round($total, 2));
                                            $set('_total_selected', round($total, 2));
                                        })
                                        ->columns(1)
                                        ->bulkToggleable()
                                        ->helperText('Marque todas as distribuições que serão pagas agora.'),
                                ])
                                ->visible(fn (Get $get) => (bool) $get('associate_id')),

                            Forms\Components\Section::make('Valores')
                                ->schema([
                                    Forms\Components\Placeholder::make('total_selected_display')
                                        ->label('Total das distribuições selecionadas')
                                        ->content(function (Get $get) {
                                            $ids = $get('selected_distribution_ids') ?? [];
                                            if (empty($ids)) return '—';
                                            $total = ProductionDelivery::whereIn('id', $ids)->sum('net_value');
                                            return new \Illuminate\Support\HtmlString(
                                                '<span style="font-size:1.1rem;font-weight:700;color:#059669;">R$ ' . number_format($total, 2, ',', '.') . '</span>'
                                            );
                                        })
                                        ->live(),

                                    Forms\Components\TextInput::make('amount_paid')
                                        ->label('Valor a Pagar Agora')
                                        ->numeric()
                                        ->required()
                                        ->prefix('R$')
                                        ->live()
                                        ->helperText('Pode ser menor que o total se for pagamento parcial (gera saldo em aberto).'),

                                    Forms\Components\Hidden::make('_total_selected'),
                                ])
                                ->visible(fn (Get $get) => !empty($get('selected_distribution_ids')))
                                ->columns(2),

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

                                    Forms\Components\TextInput::make('document_number')
                                        ->label('Nº do Documento / Comprovante')
                                        ->maxLength(100),

                                    Forms\Components\Textarea::make('notes')
                                        ->label('Observações')
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ])
                                ->visible(fn (Get $get) => !empty($get('selected_distribution_ids')))
                                ->columns(2),
                        ];
                    })
                    ->modalWidth('4xl')
                    ->modalHeading('Pagar Associado')
                    ->modalSubmitActionLabel('Confirmar Pagamento e Gerar Extrato')
                    ->action(function (array $data) {
                        $project    = $this->ownerRecord;
                        $tenantId   = session('tenant_id');
                        $year       = now()->year;

                        $ids         = $data['selected_distribution_ids'] ?? [];
                        $amountPaid  = (float) $data['amount_paid'];
                        $associateId = (int) $data['associate_id'];

                        if (empty($ids) || $amountPaid <= 0) {
                            Notification::make()->warning()->title('Selecione distribuições e informe o valor.')->send();
                            return;
                        }

                        $distributions = ProductionDelivery::whereIn('id', $ids)
                            ->where('sales_project_id', $project->id)
                            ->where('associate_id', $associateId)
                            ->where('paid', false)
                            ->get();

                        if ($distributions->isEmpty()) {
                            Notification::make()->warning()->title('Nenhuma distribuição válida encontrada.')->send();
                            return;
                        }

                        $totalDistValue = $distributions->sum('net_value');
                        $balance        = max(0, round($totalDistValue - $amountPaid, 2));

                        DB::transaction(function () use (
                            $project, $tenantId, $year, $distributions, $associateId,
                            $amountPaid, $totalDistValue, $balance, $data
                        ) {
                            // 1. Criar registro de pagamento
                            $receiptNumber = ProjectPayment::nextReceiptNumber($tenantId, $year);

                            $payment = ProjectPayment::create([
                                'tenant_id'         => $tenantId,
                                'sales_project_id'  => $project->id,
                                'type'              => 'associate_payment',
                                'status'            => ProjectPaymentStatus::PAID,
                                'associate_id'      => $associateId,
                                'amount'            => $amountPaid,
                                'balance_remaining' => $balance,
                                'description'       => 'Pagamento ao associado — ' . $distributions->count() . ' distribuição(ões)',
                                'payment_date'      => $data['payment_date'],
                                'payment_method'    => $data['payment_method'],
                                'bank_account_id'   => $data['bank_account_id'] ?? null,
                                'document_number'   => $data['document_number'] ?? null,
                                'notes'             => $data['notes'] ?? null,
                                'receipt_number'    => $receiptNumber,
                                'finalized_at'      => now(),
                                'finalized_by'      => Auth::id(),
                                'created_by'        => Auth::id(),
                                'approved_by'       => Auth::id(),
                                'approved_at'       => now(),
                            ]);

                            // 2. Marcar distribuições como pagas
                            $distributions->each(fn ($d) => $d->update([
                                'paid'               => true,
                                'paid_date'          => $data['payment_date'],
                                'project_payment_id' => $payment->id,
                            ]));

                            // 3. Atualizar campo de controle no projeto
                            $project->increment('associates_paid_amount', $amountPaid);

                            // 4. Movimentação de caixa (saída) se conta informada
                            if (!empty($data['bank_account_id'])) {
                                $bankAccount = BankAccount::find($data['bank_account_id']);
                                if ($bankAccount) {
                                    $newBalance = $bankAccount->current_balance - $amountPaid;
                                    CashMovement::create([
                                        'tenant_id'       => $tenantId,
                                        'type'            => CashMovementType::EXPENSE,
                                        'amount'          => $amountPaid,
                                        'balance_after'   => $newBalance,
                                        'description'     => "Pgto associado #{$associateId} — Projeto: {$project->title} — Extrato {$payment->receipt_number}",
                                        'movement_date'   => $data['payment_date'],
                                        'bank_account_id' => $data['bank_account_id'],
                                        'payment_method'  => $data['payment_method'],
                                        'document_number' => $data['document_number'] ?? null,
                                        'reference_type'  => ProjectPayment::class,
                                        'reference_id'    => $payment->id,
                                        'created_by'      => Auth::id(),
                                    ]);
                                    $bankAccount->update(['current_balance' => $newBalance]);
                                }
                            }
                        });

                        Notification::make()
                            ->success()
                            ->title("Pagamento registrado! {$distributions->count()} distribuição(ões) marcada(s) como pagas.")
                            ->body('Saldo em aberto: R$ ' . number_format($balance, 2, ',', '.'))
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download_pdf')
                    ->label('Extrato PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function ($record) {
                        $project      = $this->ownerRecord;
                        $tenant       = \App\Models\Tenant::find(session('tenant_id'));
                        $distributions = $record->distributions()
                            ->with(['product', 'customer', 'parentDelivery.projectDemand.product'])
                            ->orderBy('delivery_date')
                            ->get();

                        $associateName = optional(optional($record->associate)->user)->name ?? '—';
                        $cpf           = optional($record->associate)->cpf_cnpj ?? '—';

                        $svc = app(\App\Services\TemplatedPdfService::class);
                        $pdf = $svc->generateSystemPdf('pdf.associate-payment-statement', [
                            'tenant'        => $tenant,
                            'project'       => $project,
                            'payment'       => $record,
                            'associate_name' => $associateName,
                            'cpf'           => $cpf,
                            'distributions' => $distributions,
                            'generated_at'  => now()->format('d/m/Y H:i'),
                        ], array_merge(
                            $svc->systemPdfOptions('pdf.associate-payment-statement', 'Extrato'),
                            ['paper' => 'a4', 'orientation' => 'portrait']
                        ));

                        return \Illuminate\Support\Facades\Response::streamDownload(
                            fn () => print($pdf->output()),
                            "extrato-{$record->receipt_number}.pdf",
                            ['Content-Type' => 'application/pdf']
                        );
                    }),

                Tables\Actions\Action::make('view_details')
                    ->label('Detalhes')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalWidth('2xl')
                    ->modalHeading(fn ($record) => "Extrato {$record->receipt_number}")
                    ->form(fn ($record) => [
                        Forms\Components\Placeholder::make('info')
                            ->content(function () use ($record) {
                                $distributions = $record->distributions()
                                    ->with(['product', 'customer'])
                                    ->orderBy('delivery_date')
                                    ->get();

                                $rows = $distributions->map(fn ($d) => sprintf(
                                    '<tr><td>%s</td><td>%s</td><td>%s</td><td style="text-align:right">%s</td><td style="text-align:right;color:#059669;font-weight:600">R$ %s</td></tr>',
                                    $d->delivery_date?->format('d/m/Y') ?? '—',
                                    optional($d->product)->name ?? '—',
                                    optional($d->customer)->trade_name ?? optional($d->customer)->name ?? '—',
                                    number_format($d->quantity, 3, ',', '.') . ' ' . (optional($d->product)->unit ?? ''),
                                    number_format($d->net_value, 2, ',', '.')
                                ))->join('');

                                return new \Illuminate\Support\HtmlString(
                                    '<table style="width:100%;border-collapse:collapse;font-size:.85rem">'
                                    . '<thead><tr style="background:#f3f4f6;font-weight:700">'
                                    . '<th style="padding:.4rem;text-align:left">Data</th>'
                                    . '<th style="padding:.4rem;text-align:left">Produto</th>'
                                    . '<th style="padding:.4rem;text-align:left">Cliente</th>'
                                    . '<th style="padding:.4rem;text-align:right">Qtd</th>'
                                    . '<th style="padding:.4rem;text-align:right">Líquido</th>'
                                    . '</tr></thead><tbody>' . $rows . '</tbody>'
                                    . '<tfoot><tr style="font-weight:700;border-top:2px solid #e5e7eb">'
                                    . '<td colspan="4" style="padding:.4rem">Total das Distribuições</td>'
                                    . '<td style="padding:.4rem;text-align:right;color:#059669">R$ ' . number_format($distributions->sum('net_value'), 2, ',', '.') . '</td>'
                                    . '</tr>'
                                    . '<tr><td colspan="4" style="padding:.4rem">Valor Pago</td>'
                                    . '<td style="padding:.4rem;text-align:right;font-weight:700">R$ ' . number_format($record->amount, 2, ',', '.') . '</td></tr>'
                                    . ($record->balance_remaining > 0
                                        ? '<tr><td colspan="4" style="padding:.4rem;color:#d97706">Saldo em Aberto</td>'
                                          . '<td style="padding:.4rem;text-align:right;color:#d97706;font-weight:700">R$ ' . number_format($record->balance_remaining, 2, ',', '.') . '</td></tr>'
                                        : '')
                                    . '</tfoot></table>'
                                );
                            }),
                    ])
                    ->action(fn () => null),
            ]);
    }
}
