<?php

namespace App\Filament\Resources;

use App\Enums\DeliveryStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use App\Filament\Resources\AssociateReceiptResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\Associate;
use App\Models\AssociateReceipt;
use App\Models\BankAccount;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\AssociateReceiptService;
use App\Services\ProjectFinancialCalculator;
use App\Services\ReceiptDataBuilder;
use App\Services\TemplatedPdfService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class AssociateReceiptResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = AssociateReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Projetos de Venda';

    protected static ?string $modelLabel = 'Comprovante de Entrega';

    protected static ?string $pluralModelLabel = 'Comprovantes de Entrega';

    protected static ?int $navigationSort = 5;

    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && ! $record->hasFinancialLocks();
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && ! $record->hasFinancialLocks();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Comprovante')
                    ->schema([
                        Forms\Components\Select::make('sales_project_id')
                            ->label('Projeto de Venda')
                            ->options(fn () => SalesProject::where('tenant_id', session('tenant_id'))
                                ->pluck('title', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('associate_id')
                            ->label('Produtor / Associado')
                            ->options(fn () => Associate::where('tenant_id', session('tenant_id'))
                                ->get()
                                ->mapWithKeys(fn (Associate $associate) => [$associate->id => $associate->display_name])
                                ->toArray())
                            ->searchable()
                            ->required(),

                        Forms\Components\DatePicker::make('from_date')
                            ->hidden()
                            ->dehydrated(false),

                        Forms\Components\DatePicker::make('to_date')
                            ->hidden()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('receipt_year')
                            ->label('Ano')
                            ->numeric()
                            ->default(now()->year)
                            ->required()
                            ->minValue(2020)
                            ->maxValue(2099),

                        Forms\Components\TextInput::make('receipt_number')
                            ->label('Número do Recibo')
                            ->numeric()
                            ->nullable()
                            ->helperText('Gerado automaticamente se deixado em branco na criação.'),

                        Forms\Components\DatePicker::make('issued_at')
                            ->label('Data de Emissão')
                            ->default(today())
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('existing_receipt_warning')
                            ->label('')
                            ->content(function (Get $get, $record) {
                                $projectId = $get('sales_project_id');
                                $associateId = $get('associate_id');
                                if (! $projectId || ! $associateId) {
                                    return '';
                                }
                                $query = AssociateReceipt::where('tenant_id', session('tenant_id'))
                                    ->where('sales_project_id', $projectId)
                                    ->where('associate_id', $associateId);
                                // Excluir o próprio registro na edição
                                if ($record?->id) {
                                    $query->where('id', '!=', $record->id);
                                }
                                $count = $query->count();
                                if ($count === 0) {
                                    return '';
                                }
                                $label = $count === 1 ? '1 comprovante' : "{$count} comprovantes";

                                return new HtmlString(
                                    '<div style="background:#fef9c3;border:1px solid #ca8a04;border-radius:6px;padding:.65rem 1rem;color:#78350f;">'
                                    .'<strong>⚠️ Atenção:</strong> Já existe(m) <strong>'.$label.'</strong> para este produtor neste projeto. '
                                    .'Criar um comprovante adicional permite dividir as distribuições entre múltiplos comprovantes. '
                                    .'Quando houver mais de um comprovante, certifique-se de que todas as distribuições estão cobertas.'
                                    .'</div>'
                                );
                            })
                            ->live()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Entregas Vinculadas')
                    ->description('Selecione as entregas que compõem este comprovante. Deixe em branco para incluir todas as aprovadas do produtor/projeto.')
                    ->schema([
                        Forms\Components\CheckboxList::make('delivery_ids')
                            ->label('Entregas Aprovadas')
                            ->options(function (Get $get, $record) {
                                $tenantId = session('tenant_id');
                                $associateId = $get('associate_id') ?? $record?->associate_id;
                                $projectId = $get('sales_project_id') ?? $record?->sales_project_id;

                                if (! $associateId) {
                                    return [];
                                }

                                $query = ProductionDelivery::where('tenant_id', $tenantId)
                                    ->where('associate_id', $associateId)
                                    ->where('status', DeliveryStatus::APPROVED)
                                    ->whereNotNull('parent_delivery_id')
                                    ->with('product', 'customer')
                                    ->orderBy('delivery_date');

                                if (! $projectId) {
                                    return [];
                                }
                                $query->where('sales_project_id', $projectId);

                                return $query->get()->mapWithKeys(fn ($d) => [
                                    (string) $d->id => (
                                        ($d->delivery_date?->format('d/m/Y') ?? '—').
                                        ' — '.($d->product?->name ?? 'Produto desconhecido').
                                        ' — '.number_format($d->quantity, 3, ',', '.').' '.($d->product?->unit ?? 'un').
                                        ' — R$ '.number_format($d->net_value ?? 0, 2, ',', '.').
                                        ($d->customer ? ' → '.$d->customer->trade_name ?? $d->customer->name : '')
                                    ),
                                ])->all();
                            })
                            ->dehydrateStateUsing(fn ($state) => is_array($state)
                                ? array_values(array_map('intval', $state))
                                : [])
                            ->live()
                            ->columns(1)
                            ->bulkToggleable(),
                    ])
                    ->collapsible()
                    ->collapsed(fn ($record) => empty($record?->delivery_ids)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('formatted_number')
                    ->label('Nº Recibo')
                    ->sortable(['receipt_year', 'receipt_number'])
                    ->searchable(query: fn ($query, $search) => $query->whereRaw("CONCAT(LPAD(receipt_number,3,'0'), '/', receipt_year) LIKE ?", ["%{$search}%"])),

                Tables\Columns\TextColumn::make('associate.display_name')
                    ->label('Produtor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('project.title')
                    ->label('Projeto')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->default('— Avulso —'),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Data Emissão')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('receipt_year')
                    ->label('Ano')
                    ->sortable(),

                // Coluna de consentimento / assinatura
                Tables\Columns\IconColumn::make('acknowledged_at')
                    ->label('Assinado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (AssociateReceipt $r) => $r->acknowledged_at
                        ? 'Assinado em '.$r->acknowledged_at->format('d/m/Y H:i')
                        : 'Aguardando assinatura'),

                // ── Status financeiro do comprovante ──────────────────────────
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? 'Rascunho')
                    ->color(fn ($state) => $state?->getColor() ?? 'gray'),

                // ── Valor líquido congelado ────────────────────────────────────
                Tables\Columns\TextColumn::make('total_net')
                    ->label('Valor Líquido')
                    ->money('BRL')
                    ->placeholder('—')
                    ->tooltip('Valor congelado no momento da geração do comprovante'),

                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Pago')->money('BRL')->placeholder('—')
                    ->color(fn ($state, AssociateReceipt $record) => $record->status === ReceiptStatus::PAID ? 'success' : 'info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('delivery_ids')
                    ->label('Entregas')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state) && count($state) > 0) {
                            return count($state).' entrega(s)';
                        }

                        return '-';
                    })
                    ->badge()
                    ->color(fn ($state) => (is_array($state) && count($state) > 0) ? 'info' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('receipt_year')
                    ->label('Ano')
                    ->options(fn () => AssociateReceipt::where('tenant_id', session('tenant_id'))
                        ->distinct()
                        ->pluck('receipt_year', 'receipt_year')
                        ->toArray()),

                Tables\Filters\SelectFilter::make('sales_project_id')
                    ->label('Projeto')
                    ->options(fn () => SalesProject::where('tenant_id', session('tenant_id'))
                        ->pluck('title', 'id')),

                Tables\Filters\Filter::make('acknowledged')
                    ->label('Somente assinados')
                    ->query(fn ($query) => $query->whereNotNull('acknowledged_at'))
                    ->toggle(),
            ])
            ->actions([
                // ── IMPRIMIR PDF ──────────────────────────────────────────────
                Tables\Actions\Action::make('printReceipt')
                    ->label('Imprimir PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function (AssociateReceipt $record): mixed {
                        $tenantId = $record->tenant_id;
                        $tenant = Tenant::find($tenantId);
                        $associate = $record->associate()->with('user')->first();
                        $project = $record->project;

                        // ── Buscar entregas ──────────────────────────────────
                        // Reimpressao usa somente distribuicoes vinculadas a este comprovante.
                        $storedIds = collect($record->delivery_ids ?? [])
                            ->map(fn ($id) => (int) $id)
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();
                        $query = ProductionDelivery::where('tenant_id', $tenantId)
                            ->where('associate_id', $record->associate_id)
                            ->where('status', DeliveryStatus::APPROVED)
                            ->whereNotNull('parent_delivery_id')
                            ->with(['product', 'customer', 'parentDelivery'])
                            ->orderBy('delivery_date');

                        if (! empty($storedIds)) {
                            $query->whereIn('id', $storedIds);
                        } elseif ($record->sales_project_id) {
                            $query->where('sales_project_id', $record->sales_project_id);
                            $query->where('associate_receipt_id', $record->id);
                        } else {
                            $query->whereNull('sales_project_id');
                            $query->where('associate_receipt_id', $record->id);
                            if ($record->from_date) {
                                $query->where('delivery_date', '>=', $record->from_date);
                            }
                            if ($record->to_date) {
                                $query->where('delivery_date', '<=', $record->to_date);
                            }
                        }

                        $deliveries = $query->get();

                        if ($deliveries->isEmpty()) {
                            Notification::make()
                                ->warning()
                                ->title('Sem entregas aprovadas')
                                ->body('Nenhuma entrega aprovada encontrada para este comprovante.')
                                ->send();

                            return null;
                        }

                        $receiptData = ReceiptDataBuilder::fromDeliveries($deliveries, null, $project);

                        // ── Marcar como segunda via se já foi assinado ───────
                        $isSecondCopy = $record->acknowledged_at !== null;

                        $svc = app(TemplatedPdfService::class);
                        $pdf = $svc->generateSystemPdf('pdf.project-associate-receipt', [
                            'tenant' => $tenant,
                            'project' => $project,
                            'associate' => $associate,
                            'receipt' => $record,
                            'summary' => $receiptData['summary'],
                            'productsSummary' => $receiptData['productsSummary'],
                            'hasRoundingDivergence' => $receiptData['hasRoundingDivergence'],
                            'feeBreakdown' => $receiptData['feeBreakdown'],
                            'isSecondCopy' => $isSecondCopy,
                        ], ['paper' => 'a4', 'orientation' => 'portrait', 'title' => 'Comprovante de Entrega']);

                        $safeName = Str::slug($associate?->display_name ?? 'associado');
                        $receiptLabel = str_replace('/', '-', $record->formatted_number);
                        $suffix = $isSecondCopy ? '-2via' : '';

                        return Response::streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, "comprovante-{$receiptLabel}-{$safeName}{$suffix}.pdf", ['Content-Type' => 'application/pdf']);
                    }),

                // ── VER ENTREGAS VINCULADAS ──────────────────────────────────
                Tables\Actions\Action::make('viewDeliveries')
                    ->label('Entregas')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->modalHeading(fn (AssociateReceipt $r) => 'Entregas — Comprovante Nº '.$r->formatted_number)
                    ->modalContent(function (AssociateReceipt $record) {
                        $ids = collect($record->delivery_ids ?? [])
                            ->map(fn ($id) => (int) $id)
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();

                        $query = ProductionDelivery::where('tenant_id', $record->tenant_id)
                            ->where('associate_id', $record->associate_id)
                            ->where('status', DeliveryStatus::APPROVED)
                            ->whereNotNull('parent_delivery_id')
                            ->with('product', 'customer')
                            ->orderBy('delivery_date');

                        if (! empty($ids)) {
                            $query->whereIn('id', $ids);
                        } elseif ($record->sales_project_id) {
                            $query->where('sales_project_id', $record->sales_project_id)
                                ->where('associate_receipt_id', $record->id);
                        } else {
                            $query->whereNull('sales_project_id')
                                ->where('associate_receipt_id', $record->id);
                            if ($record->from_date) {
                                $query->where('delivery_date', '>=', $record->from_date);
                            }
                            if ($record->to_date) {
                                $query->where('delivery_date', '<=', $record->to_date);
                            }
                        }

                        $deliveries = $query->get();

                        if ($deliveries->isEmpty()) {
                            return new HtmlString('<p style="padding:1rem;color:#888">Nenhuma entrega encontrada.</p>');
                        }

                        // ── Recalcular taxas via calculator (valores corretos sempre) ──
                        $project = $record->project;
                        $calcMap = [];
                        if ($project) {
                            $calculator = app(ProjectFinancialCalculator::class);
                            foreach ($deliveries as $d) {
                                $gross = (string) ($d->gross_value ?? 0);
                                if (bccomp($gross, '0', 4) > 0) {
                                    $res = $calculator->calculate($project, $gross);
                                    $calcMap[$d->id] = [
                                        'fee' => (float) $res['total_fee'],
                                        'net' => (float) $res['net'],
                                    ];
                                } else {
                                    $calcMap[$d->id] = ['fee' => 0.0, 'net' => 0.0];
                                }
                            }
                        }

                        $getFee = fn ($d) => isset($calcMap[$d->id]) ? $calcMap[$d->id]['fee'] : (float) ($d->admin_fee_amount ?? 0);
                        $getNet = fn ($d) => isset($calcMap[$d->id]) ? $calcMap[$d->id]['net'] : (float) ($d->net_value ?? 0);

                        $totalGross = (float) $deliveries->sum('gross_value');
                        $totalFee = array_sum(array_map($getFee, $deliveries->all()));
                        $totalNet = array_sum(array_map($getNet, $deliveries->all()));

                        $rows = $deliveries->map(fn ($d) => '<tr>'
                            .'<td style="padding:6px 10px;border-bottom:1px solid #eee">'.e($d->delivery_date?->format('d/m/Y') ?? '—').'</td>'
                            .'<td style="padding:6px 10px;border-bottom:1px solid #eee">'.e($d->product?->name ?? '—').'</td>'
                            .'<td style="padding:6px 10px;border-bottom:1px solid #eee">'.e($d->customer?->trade_name ?? $d->customer?->name ?? '—').'</td>'
                            .'<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right">'.number_format($d->quantity, 3, ',', '.').' '.e($d->product?->unit ?? 'un').'</td>'
                            .'<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right">R$ '.number_format($d->gross_value ?? 0, 2, ',', '.').'</td>'
                            .'<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;color:#c0392b">- R$ '.number_format($getFee($d), 2, ',', '.').'</td>'
                            .'<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;color:#1a5c3a;font-weight:600">R$ '.number_format($getNet($d), 2, ',', '.').'</td>'
                            .'</tr>'
                        )->implode('');

                        // ── Nota: se snapshot congelado está disponível, mostra também ──
                        $frozenNote = '';
                        if ($record->total_net && abs($record->total_net - $totalNet) > 0.01) {
                            $frozenNote = '<div style="margin-top:.5rem;padding:.5rem .75rem;background:#fef9c3;border:1px solid #fde047;border-radius:4px;font-size:.78rem;color:#78350f;">'
                                .'⚠️ Valor congelado no comprovante: <strong>R$ '.number_format($record->total_net, 2, ',', '.').'</strong>'
                                .' — Este valor foi calculado no momento da geração do PDF.'
                                .'</div>';
                        }

                        $html = '<div style="overflow-x:auto">'
                            .'<table style="width:100%;border-collapse:collapse;font-size:.875rem">'
                            .'<thead><tr style="background:#f4f6f8">'
                            .'<th style="padding:8px 10px;text-align:left">Data</th>'
                            .'<th style="padding:8px 10px;text-align:left">Produto</th>'
                            .'<th style="padding:8px 10px;text-align:left">Cliente</th>'
                            .'<th style="padding:8px 10px;text-align:right">Qtd.</th>'
                            .'<th style="padding:8px 10px;text-align:right">Bruto</th>'
                            .'<th style="padding:8px 10px;text-align:right">Taxa</th>'
                            .'<th style="padding:8px 10px;text-align:right">Líquido</th>'
                            .'</tr></thead>'
                            .'<tbody>'.$rows.'</tbody>'
                            .'<tfoot><tr style="background:#eef1f5;font-weight:700">'
                            .'<td colspan="4" style="padding:8px 10px">'.$deliveries->count().' distribuição(ões)</td>'
                            .'<td style="padding:8px 10px;text-align:right">R$ '.number_format($totalGross, 2, ',', '.').'</td>'
                            .'<td style="padding:8px 10px;text-align:right;color:#c0392b">- R$ '.number_format($totalFee, 2, ',', '.').'</td>'
                            .'<td style="padding:8px 10px;text-align:right;color:#1a5c3a">R$ '.number_format($totalNet, 2, ',', '.').'</td>'
                            .'</tr></tfoot></table></div>'
                            .$frozenNote;

                        return new HtmlString($html);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),

                // ── CONFIRMAR / DESFAZER ASSINATURA ──────────────────────────
                Tables\Actions\Action::make('acknowledge')
                    ->label(fn (AssociateReceipt $r) => $r->acknowledged_at ? 'Desfazer Assinatura' : 'Confirmar Assinatura')
                    ->icon(fn (AssociateReceipt $r) => $r->acknowledged_at ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (AssociateReceipt $r) => $r->acknowledged_at ? 'warning' : 'primary')
                    ->requiresConfirmation()
                    ->modalHeading(fn (AssociateReceipt $r) => $r->acknowledged_at
                        ? 'Desfazer confirmação de assinatura?'
                        : 'Confirmar que o associado assinou o comprovante?')
                    ->modalDescription(fn (AssociateReceipt $r) => $r->acknowledged_at
                        ? 'O comprovante voltará ao status "aguardando assinatura". A próxima impressão não será marcada como segunda via.'
                        : 'A partir deste momento, qualquer nova impressão deste comprovante será marcada como SEGUNDA VIA.')
                    ->action(function (AssociateReceipt $record): void {
                        if ($record->acknowledged_at) {
                            $record->update(['acknowledged_at' => null]);
                            Notification::make()->success()
                                ->title('Assinatura desfeita')
                                ->send();
                        } else {
                            $record->update(['acknowledged_at' => now()]);
                            Notification::make()->success()
                                ->title('Assinatura confirmada')
                                ->body('Próximas impressões serão marcadas como SEGUNDA VIA.')
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn (AssociateReceipt $r) => static::canEdit($r)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (AssociateReceipt $r) => static::canDelete($r))
                    ->using(function (AssociateReceipt $record): void {
                        if (! static::canDelete($record)) {
                            Notification::make()
                                ->danger()
                                ->title('Comprovante bloqueado')
                                ->body('Comprovantes faturados, pagos ou parcialmente pagos nao podem ser excluidos.')
                                ->send();

                            return;
                        }

                        ProductionDelivery::where('tenant_id', $record->tenant_id)
                            ->where('associate_receipt_id', $record->id)
                            ->update(['associate_receipt_id' => null]);

                        $record->delete();
                    }),

                // ── PAGAR COMPROVANTE (parcial ou total) ──────────────────────
                Tables\Actions\Action::make('addPayment')
                    ->label(fn (AssociateReceipt $r) => $r->status === ReceiptStatus::PARTIALLY_PAID
                        ? 'Registrar Parcela'
                        : 'Pagar Comprovante')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (AssociateReceipt $r) => in_array($r->status, [
                        ReceiptStatus::PENDING_PAYMENT,
                        ReceiptStatus::PARTIALLY_PAID,
                    ]))
                    ->modalHeading(fn (AssociateReceipt $r) => 'Registrar Pagamento — '.$r->formatted_number)
                    ->modalDescription(fn (AssociateReceipt $r) => 'Total: R$ '.number_format((float) $r->total_net, 2, ',', '.').
                        ' | Pago: R$ '.number_format((float) ($r->amount_paid ?? 0), 2, ',', '.').
                        ' | Restante: R$ '.number_format($r->remaining_amount, 2, ',', '.'))
                    ->form(function (AssociateReceipt $record) {
                        $remaining = $record->remaining_amount;

                        return [
                            Forms\Components\TextInput::make('amount')
                                ->label('Valor a Pagar (R$)')
                                ->default(number_format($remaining, 2, '.', ''))
                                ->required()->numeric()->minValue(0.01)
                                ->helperText('Máximo: R$ '.number_format($remaining, 2, ',', '.')),

                            Forms\Components\DatePicker::make('payment_date')
                                ->label('Data do Pagamento')
                                ->default(today())
                                ->required()
                                ->native(false),

                            Forms\Components\Select::make('payment_method')
                                ->label('Forma de Pagamento')
                                ->options(collect(PaymentMethod::cases())
                                    ->mapWithKeys(fn ($m) => [$m->value => $m->getLabel()])
                                    ->toArray())
                                ->required(),

                            Forms\Components\Select::make('bank_account_id')
                                ->label('Conta Bancária (opcional)')
                                ->options(fn () => BankAccount::where('tenant_id', session('tenant_id'))
                                    ->where('status', true)
                                    ->pluck('name', 'id')
                                    ->toArray())
                                ->placeholder('— Nenhuma —')
                                ->helperText('Se informada, registra saída no caixa da cooperativa'),

                            Forms\Components\TextInput::make('document_number')
                                ->label('Nº Documento / Comprovante')
                                ->placeholder('Opcional'),

                            Forms\Components\Textarea::make('notes')
                                ->label('Observações')
                                ->rows(2)
                                ->placeholder('Opcional'),
                        ];
                    })
                    ->action(function (AssociateReceipt $record, array $data): void {
                        try {
                            app(AssociateReceiptService::class)->addPayment($record, $data);
                            $fresh = $record->fresh();
                            $body = $fresh->status === ReceiptStatus::PAID
                                ? 'Comprovante quitado integralmente.'
                                : 'Saldo restante: R$ '.number_format($fresh->remaining_amount, 2, ',', '.');
                            Notification::make()->success()->title('Pagamento registrado')->body($body)->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Erro ao processar pagamento')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                // ── HISTÓRICO DE PAGAMENTOS ───────────────────────────────────
                Tables\Actions\Action::make('viewPayments')
                    ->label('Histórico de Pagamentos')
                    ->icon('heroicon-o-clock')->color('gray')
                    ->visible(fn (AssociateReceipt $r) => in_array($r->status, [
                        ReceiptStatus::PARTIALLY_PAID,
                        ReceiptStatus::PAID,
                    ]))
                    ->modalHeading(fn (AssociateReceipt $r) => 'Pagamentos — '.$r->formatted_number)
                    ->modalContent(function (AssociateReceipt $record): View {
                        $payments = $record->payments()->with('bankAccount')->get();

                        return view('filament.modals.receipt-payments-history', [
                            'receipt' => $record,
                            'payments' => $payments,
                            'label' => 'Pagamento',
                        ]);
                    })
                    ->modalSubmitAction(false),
            ])
            ->modifyQueryUsing(fn ($query) => $query
                ->orderByDesc('receipt_year')
                ->orderByDesc('receipt_number')
                ->orderByDesc('issued_at')
                ->orderByDesc('id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssociateReceipts::route('/'),
            'create' => Pages\CreateAssociateReceipt::route('/create'),
            'edit' => Pages\EditAssociateReceipt::route('/{record}/edit'),
        ];
    }
}
