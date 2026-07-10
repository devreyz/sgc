<?php

namespace App\Filament\Resources;

use App\Enums\CustomerReceiptStatus;
use App\Enums\DeliveryStatus;
use App\Enums\PaymentMethod;
use App\Exports\CustomerBillingReceiptExport;
use App\Filament\Resources\CustomerBillingReceiptResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerBillingReceipt;
use App\Models\Organization;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\CustomerBillingReceiptService;
use App\Services\TemplatedPdfService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Facades\Excel;

class CustomerBillingReceiptResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = CustomerBillingReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Projetos de Venda';

    protected static ?string $modelLabel = 'Cobrança ao Cliente';

    protected static ?string $pluralModelLabel = 'Cobranças aos Clientes';

    protected static ?int $navigationSort = 6;

    // ─────────────────────────────────────────────────────────────────────────
    //  Formulário (somente DRAFT)
    // ─────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Projeto e Destinatário')
                ->description('Selecione o projeto e em seguida o cliente OU a organização (não ambos).')
                ->schema([
                    Forms\Components\Select::make('sales_project_id')
                        ->label('Projeto de Venda')
                        ->options(fn () => SalesProject::where('tenant_id', session('tenant_id'))
                            ->orderBy('title')->pluck('title', 'id'))
                        ->searchable()->required()->live()
                        ->afterStateUpdated(function (Forms\Set $set) {
                            $set('customer_id', null);
                            $set('organization_id', null);
                            $set('delivery_ids', []);
                        }),

                    // ── Cliente (mutuamente exclusivo com organização) ──────────
                    Forms\Components\Select::make('customer_id')
                        ->label('Cliente')
                        ->options(function (Get $get) {
                            $projectId = (int) $get('sales_project_id');
                            if (! $projectId) {
                                return [];
                            }
                            $ids = ProductionDelivery::where('tenant_id', session('tenant_id'))
                                ->where('sales_project_id', $projectId)
                                ->whereNotNull('parent_delivery_id')
                                ->whereNotNull('customer_id')
                                ->where('status', DeliveryStatus::APPROVED->value)
                                ->distinct()->pluck('customer_id');

                            return Customer::whereIn('id', $ids)->orderBy('name')
                                ->pluck('name', 'id')->toArray();
                        })
                        ->searchable()->nullable()
                        ->placeholder('— Selecione um cliente —')
                        ->helperText('Somente clientes com distribuições aprovadas neste projeto.')
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            if ($state) {
                                $set('organization_id', null);
                            }
                            $set('delivery_ids', []);
                        })
                        ->visible(fn (Get $get) => (bool) $get('sales_project_id')),

                    // ── Organização (mutuamente exclusiva com cliente) ──────────
                    Forms\Components\Select::make('organization_id')
                        ->label('Organização')
                        ->options(function (Get $get) {
                            $projectId = (int) $get('sales_project_id');
                            if (! $projectId) {
                                return [];
                            }
                            // Apenas organizações que têm clientes com distribuições aprovadas no projeto
                            $customerIds = ProductionDelivery::where('tenant_id', session('tenant_id'))
                                ->where('sales_project_id', $projectId)
                                ->whereNotNull('parent_delivery_id')
                                ->whereNotNull('customer_id')
                                ->where('status', DeliveryStatus::APPROVED->value)
                                ->distinct()->pluck('customer_id');
                            $orgIds = Customer::whereIn('id', $customerIds)
                                ->whereNotNull('organization_id')
                                ->distinct()->pluck('organization_id');

                            return Organization::whereIn('id', $orgIds)
                                ->where('tenant_id', session('tenant_id'))
                                ->orderBy('name')->pluck('name', 'id')->toArray();
                        })
                        ->searchable()->nullable()
                        ->placeholder('— Ou selecione uma organização —')
                        ->helperText('Agrupa todos os clientes desta organização em um único comprovante.')
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            if ($state) {
                                $set('customer_id', null);
                            }
                            $set('delivery_ids', []);
                        })
                        ->visible(fn (Get $get) => (bool) $get('sales_project_id')),

                    Forms\Components\DatePicker::make('issued_at')
                        ->label('Data de Emissão')
                        ->default(today())->required()->native(false),

                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')->rows(2)->columnSpanFull(),
                ])
                ->columns(2),

            // ── Distribuições ───────────────────────────────────────────────
            Forms\Components\Section::make('Distribuições a Cobrar')
                ->description('Distribuições vinculadas a outro comprovante aparecem em laranja e não podem ser selecionadas. Após emitir, a lista torna-se imutável.')
                ->schema([
                    // Botão rápido: selecionar todos os disponíveis
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('selectAllFree')
                            ->label('Selecionar todos disponíveis')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->size('sm')
                            ->action(function (Get $get, Forms\Set $set, $record) {
                                $pid = (int) $get('sales_project_id');
                                $cid = (int) $get('customer_id');
                                $oid = (int) $get('organization_id');
                                $locked = static::getLockedDistributionIds($record?->id);
                                $all = array_keys(static::buildDistributionOptions($pid, $cid, $oid, $record?->id));
                                $free = array_values(array_diff($all, $locked));
                                $set('delivery_ids', array_map('strval', $free));
                            })
                            ->visible(fn (Get $get) => (bool) $get('sales_project_id')
                                && ((bool) $get('customer_id') || (bool) $get('organization_id'))),

                        Forms\Components\Actions\Action::make('deselectAll')
                            ->label('Desmarcar todos')
                            ->icon('heroicon-o-x-circle')
                            ->color('gray')
                            ->size('sm')
                            ->action(fn (Forms\Set $set) => $set('delivery_ids', []))
                            ->visible(fn (Get $get) => ! empty(array_filter((array) $get('delivery_ids')))),
                    ])
                        ->columnSpanFull(),

                    Forms\Components\CheckboxList::make('delivery_ids')
                        ->label(false)
                        ->options(function (Get $get, $record) {
                            return static::buildDistributionOptions(
                                (int) $get('sales_project_id'),
                                (int) $get('customer_id'),
                                (int) $get('organization_id'),
                                $record?->id
                            );
                        })
                        ->descriptions(function (Get $get, $record) {
                            return static::buildDistributionDescriptions(
                                (int) $get('sales_project_id'),
                                (int) $get('customer_id'),
                                (int) $get('organization_id'),
                                $record?->id
                            );
                        })
                        ->disableOptionWhen(function (int|string $value, Get $get, $record) {
                            return in_array(
                                (int) $value,
                                static::getLockedDistributionIds($record?->id)
                            );
                        })
                        ->searchable()->live()->columnSpanFull()
                        ->helperText(function (Get $get, $record) {
                            $pid = (int) $get('sales_project_id');
                            $cid = (int) $get('customer_id');
                            $oid = (int) $get('organization_id');
                            if (! $pid || (! $cid && ! $oid)) {
                                return 'Selecione um projeto e um cliente ou organização.';
                            }
                            $total = count(static::buildDistributionOptions($pid, $cid, $oid, $record?->id));
                            $locked = count(static::getLockedDistributionIds($record?->id));
                            $free = max(0, $total - $locked);

                            return "{$free} disponível(is) para seleção — {$locked} já em outro comprovante (laranja)";
                        })
                        ->noSearchResultsMessage('Nenhuma distribuição encontrada.')
                        ->visible(fn (Get $get) => (bool) $get('sales_project_id')
                            && ((bool) $get('customer_id') || (bool) $get('organization_id'))),

                    Forms\Components\Placeholder::make('subtotal_preview')
                        ->label('Subtotal bruto selecionado (prévia)')
                        ->content(function (Get $get) {
                            $ids = array_filter((array) $get('delivery_ids'));
                            if (empty($ids)) {
                                return 'R$ 0,00';
                            }
                            $total = ProductionDelivery::whereIn('id', $ids)->get()
                                ->sum(fn ($d) => (float) $d->quantity * (float) $d->unit_price);

                            return 'R$ '.number_format($total, 2, ',', '.');
                        })
                        ->visible(fn (Get $get) => ! empty(array_filter((array) $get('delivery_ids')))),
                ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers — distribuições
    // ─────────────────────────────────────────────────────────────────────────

    /** IDs bloqueados: distribution_ids de OUTROS comprovantes (qualquer status). */
    public static function getLockedDistributionIds(?int $currentReceiptId): array
    {
        return array_keys(static::getLockedDistributionMap($currentReceiptId));
    }

    /**
     * Mapa [delivery_id => formatted_number] dos comprovantes que ocupam cada distribuição.
     * Usado para mostrar qual comprovante está bloqueando cada item.
     */
    public static function getLockedDistributionMap(?int $currentReceiptId): array
    {
        $receipts = CustomerBillingReceipt::where('tenant_id', session('tenant_id'))
            ->when($currentReceiptId, fn ($q) => $q->where('id', '!=', $currentReceiptId))
            ->whereNotNull('delivery_ids')
            ->get(['id', 'delivery_ids', 'receipt_year', 'receipt_number']);

        $map = [];
        foreach ($receipts as $r) {
            $ids = is_array($r->delivery_ids) ? $r->delivery_ids : [];
            foreach ($ids as $did) {
                $map[(int) $did] = $r->formatted_number;
            }
        }

        return $map;
    }

    /** Options [id => label] para o CheckboxList. */
    public static function buildDistributionOptions(int $projectId, int $customerId, int $orgId, ?int $currentReceiptId): array
    {
        $query = static::baseDistributionQuery($projectId, $customerId, $orgId, $currentReceiptId);
        if (! $query) {
            return [];
        }

        return $query->get()
            ->mapWithKeys(fn ($d) => [
                $d->id => sprintf(
                    '%s — %s — %s — %s kg × R$ %s',
                    $d->delivery_date?->format('d/m/Y') ?? '—',
                    $d->customer?->name ?? '—',
                    $d->product?->name ?? 'Produto #'.$d->product_id,
                    number_format((float) $d->quantity, 2, ',', '.'),
                    number_format((float) $d->unit_price, 2, ',', '.')
                ),
            ])
            ->toArray();
    }

    /** Descriptions [id => label] para o CheckboxList — inclui aviso para itens bloqueados. */
    public static function buildDistributionDescriptions(int $projectId, int $customerId, int $orgId, ?int $currentReceiptId): array
    {
        $query = static::baseDistributionQuery($projectId, $customerId, $orgId, $currentReceiptId);
        if (! $query) {
            return [];
        }

        // Mapa delivery_id → número do comprovante que o ocupa
        $lockedMap = static::getLockedDistributionMap($currentReceiptId);

        return $query->get()
            ->mapWithKeys(function ($d) use ($lockedMap) {
                $gross = number_format((float) $d->quantity * (float) $d->unit_price, 2, ',', '.');
                if (isset($lockedMap[$d->id])) {
                    $label = '⚠ Em comprovante '.$lockedMap[$d->id].' — Bruto: R$ '.$gross;
                } else {
                    $label = 'Bruto: R$ '.$gross;
                }

                return [$d->id => $label];
            })
            ->toArray();
    }

    /**
     * Query base: distribuições aprovadas do projeto para o cliente/organização.
     * Inclui as do próprio comprovante em edição (para reexibir sem filtrar).
     * Distribuições em outros comprovantes são incluídas nas opções mas marcadas
     * como desabilitadas via disableOptionWhen().
     */
    private static function baseDistributionQuery(int $projectId, int $customerId, int $orgId, ?int $currentReceiptId)
    {
        if (! $projectId || (! $customerId && ! $orgId)) {
            return null;
        }

        $tenantId = session('tenant_id');
        $query = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED->value)
            ->with(['product', 'customer'])
            ->orderBy('delivery_date');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        } elseif ($orgId) {
            // Todos os clientes da organização com deliveries neste projeto
            $customerIds = Customer::where('organization_id', $orgId)
                ->where('tenant_id', $tenantId)
                ->pluck('id');
            $query->whereIn('customer_id', $customerIds);
        }

        // Inclui: sem vínculo OU vinculado ao próprio comprovante (edição)
        // Distribuições de outros comprovantes também aparecem (serão desabilitadas)
        // → não filtramos por billing_receipt_id aqui intencionalmente

        return $query;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Tabela
    // ─────────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('formatted_number')
                    ->label('Nº Cobrança')->weight('bold')
                    ->searchable(['receipt_year', 'receipt_number'])
                    ->sortable(['receipt_year', 'receipt_number']),

                Tables\Columns\TextColumn::make('project.title')
                    ->label('Projeto')->searchable()->sortable()->limit(35)->default('— Avulso —'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')->searchable()->limit(30)->placeholder('—'),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organização')->searchable()->limit(25)
                    ->placeholder('—')->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Emissão')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? 'Rascunho')
                    ->color(fn ($state) => $state?->getColor() ?? 'gray'),

                Tables\Columns\TextColumn::make('total_net')
                    ->label('Valor a Receber')->money('BRL')->placeholder('—')->weight('bold'),

                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Recebido')->money('BRL')->placeholder('—')
                    ->color(fn ($state, CustomerBillingReceipt $record) => $record->status === CustomerReceiptStatus::PAID ? 'success' : 'info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Quitado em')->dateTime('d/m/Y')->sortable()
                    ->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(CustomerReceiptStatus::cases())
                        ->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])->toArray()),

                Tables\Filters\SelectFilter::make('sales_project_id')
                    ->label('Projeto')
                    ->options(fn () => SalesProject::where('tenant_id', session('tenant_id'))
                        ->orderBy('title')->pluck('title', 'id')),
            ])
            ->actions([
                // ── Imprimir PDF ──────────────────────────────────────────────
                Tables\Actions\Action::make('printPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function (CustomerBillingReceipt $record): mixed {
                        if (empty($record->delivery_ids)) {
                            Notification::make()->warning()
                                ->title('Sem distribuições')->body('Adicione distribuições antes de gerar o PDF.')->send();

                            return null;
                        }
                        $tenant = Tenant::find($record->tenant_id);
                        $project = $record->project;
                        $customer = $record->customer;
                        $organization = $record->organization;
                        $distributions = ProductionDelivery::whereIn('id', $record->delivery_ids)
                            ->with(['product', 'customer.priceTable'])->orderBy('delivery_date')->get();

                        $isOrgReport = $organization && ! $customer;

                        if ($isOrgReport) {
                            // Relatório por organização: agrupa por produto x cliente
                            $view = 'pdf.customer-organization-receipt';
                            $data = static::buildOrganizationReportData($distributions, $record, $tenant, $project, $organization);
                        } else {
                            // Comprovante individual do cliente
                            $view = 'pdf.customer-billing-receipt';
                            $data = static::buildCustomerReceiptData($distributions, $record, $tenant, $project, $customer);
                        }

                        $svc = app(TemplatedPdfService::class);
                        $pdf = $svc->generateSystemPdf($view, $data,
                            ['paper' => 'a4', 'orientation' => 'portrait']);

                        $label = str_replace('/', '-', $record->formatted_number);
                        $name = \Illuminate\Support\Str::slug(
                            $isOrgReport ? ($organization->name ?? 'org') : ($customer?->name ?? 'cliente')
                        );

                        return Response::streamDownload(
                            fn () => print ($pdf->output()),
                            "comprovante-{$label}-{$name}.pdf",
                            ['Content-Type' => 'application/pdf']
                        );
                    }),

                // ── Exportar Excel ────────────────────────────────────────────
                Tables\Actions\Action::make('exportExcel')
                    ->label('Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->modalHeading(fn (CustomerBillingReceipt $r) => 'Exportar Excel — '.$r->formatted_number)
                    ->modalDescription('Selecione as colunas que deseja incluir na planilha exportada.')
                    ->form([
                        Forms\Components\CheckboxList::make('columns')
                            ->label('Colunas')
                            ->options(CustomerBillingReceiptExport::AVAILABLE_COLUMNS)
                            ->default(CustomerBillingReceiptExport::DEFAULT_COLUMNS)
                            ->columns(3)
                            ->bulkToggleable()
                            ->required(),
                    ])
                    ->modalSubmitActionLabel('Exportar')
                    ->action(function (CustomerBillingReceipt $record, array $data): mixed {
                        $columns = $data['columns'] ?? CustomerBillingReceiptExport::DEFAULT_COLUMNS;
                        if (empty($columns)) {
                            Notification::make()->warning()
                                ->title('Selecione ao menos uma coluna')->send();

                            return null;
                        }
                        if (empty($record->delivery_ids)) {
                            Notification::make()->warning()
                                ->title('Sem distribuições')
                                ->body('Adicione distribuições antes de exportar.')->send();

                            return null;
                        }
                        $label = str_replace('/', '-', $record->formatted_number);
                        $name = \Illuminate\Support\Str::slug(
                            $record->customer?->name ?? $record->organization?->name ?? 'cobranca'
                        );

                        return Excel::download(
                            new CustomerBillingReceiptExport($record, $columns),
                            "comprovante-{$label}-{$name}.xlsx"
                        );
                    }),

                // ── Ver distribuições ─────────────────────────────────────────
                Tables\Actions\Action::make('viewDistributions')
                    ->label('Distribuições')
                    ->icon('heroicon-o-list-bullet')->color('gray')
                    ->modalHeading(fn (CustomerBillingReceipt $r) => 'Distribuições — '.$r->formatted_number)
                    ->modalContent(fn (CustomerBillingReceipt $r) => static::renderDistributionsModal($r))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fechar'),

                // ── Editar (somente DRAFT) ────────────────────────────────────
                Tables\Actions\EditAction::make()
                    ->visible(fn (CustomerBillingReceipt $r) => $r->isEditable()),

                // ── Excluir (somente DRAFT) ───────────────────────────────────
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (CustomerBillingReceipt $r) => $r->isEditable())
                    ->before(function (CustomerBillingReceipt $r) {
                        if (! empty($r->delivery_ids)) {
                            ProductionDelivery::whereIn('id', $r->delivery_ids)
                                ->where('billing_receipt_id', $r->id)
                                ->update(['billing_receipt_id' => null]);
                        }
                    }),

                // ── Emitir Cobrança (DRAFT → PENDING_PAYMENT) ─────────────────
                Tables\Actions\Action::make('freeze')
                    ->label('Emitir Cobrança')
                    ->icon('heroicon-o-paper-airplane')->color('warning')
                    ->visible(fn (CustomerBillingReceipt $r) => $r->status === CustomerReceiptStatus::DRAFT || $r->status === null)
                    ->requiresConfirmation()
                    ->modalHeading(fn (CustomerBillingReceipt $r) => 'Emitir Cobrança '.$r->formatted_number)
                    ->modalDescription(function (CustomerBillingReceipt $r) {
                        $count = is_array($r->delivery_ids) ? count($r->delivery_ids) : 0;

                        return "Congela {$count} distribuição(ões) e calcula os valores finais. Após emitir, não é possível editar.";
                    })
                    ->action(function (CustomerBillingReceipt $record): void {
                        if (empty($record->delivery_ids)) {
                            Notification::make()->danger()->title('Sem distribuições')
                                ->body('Adicione ao menos uma distribuição antes de emitir.')->send();

                            return;
                        }
                        try {
                            $distributions = ProductionDelivery::whereIn('id', $record->delivery_ids)->get();
                            app(CustomerBillingReceiptService::class)
                                ->freezeReceipt($record, $distributions, $record->project);
                            Notification::make()->success()->title('Cobrança emitida')
                                ->body('Valor líquido: R$ '.number_format((float) $record->fresh()->total_net, 2, ',', '.'))->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Erro ao emitir cobrança')
                                ->body($e->getMessage())->send();
                        }
                    }),

                // ── Registrar Recebimento (PENDING_PAYMENT / PARTIALLY_PAID) ─────
                Tables\Actions\Action::make('addPayment')
                    ->label(fn (CustomerBillingReceipt $r) => $r->status === CustomerReceiptStatus::PARTIALLY_PAID
                        ? 'Registrar Parcela'
                        : 'Registrar Recebimento')
                    ->icon('heroicon-o-banknotes')->color('success')
                    ->visible(fn (CustomerBillingReceipt $r) => in_array($r->status, [
                        CustomerReceiptStatus::PENDING_PAYMENT,
                        CustomerReceiptStatus::PARTIALLY_PAID,
                    ]))
                    ->modalHeading(fn (CustomerBillingReceipt $r) => 'Registrar Recebimento — '.$r->formatted_number)
                    ->modalDescription(fn (CustomerBillingReceipt $r) => 'Total: R$ '.number_format((float) $r->total_net, 2, ',', '.').
                        ' | Já recebido: R$ '.number_format((float) ($r->amount_paid ?? 0), 2, ',', '.').
                        ' | Restante: R$ '.number_format($r->remaining_amount, 2, ',', '.'))
                    ->form(function (CustomerBillingReceipt $record) {
                        $remaining = $record->remaining_amount;

                        return [
                            Forms\Components\TextInput::make('amount')
                                ->label('Valor a Receber (R$)')
                                ->default(number_format($remaining, 2, '.', ''))
                                ->required()->numeric()->minValue(0.01)
                                ->helperText('Máximo: R$ '.number_format($remaining, 2, ',', '.')),
                            Forms\Components\DatePicker::make('payment_date')->label('Data do Recebimento')
                                ->default(today())->required()->native(false),
                            Forms\Components\Select::make('payment_method')->label('Forma de Recebimento')
                                ->options(collect(PaymentMethod::cases())
                                    ->mapWithKeys(fn ($m) => [$m->value => $m->getLabel()])->toArray())
                                ->required(),
                            Forms\Components\Select::make('bank_account_id')->label('Conta Bancária')
                                ->options(fn () => BankAccount::where('tenant_id', session('tenant_id'))
                                    ->where('status', true)->pluck('name', 'id')->toArray())
                                ->placeholder('— Nenhuma —')
                                ->helperText('Se informada, registra entrada no caixa.'),
                            Forms\Components\TextInput::make('document_number')->label('Nº Documento')->placeholder('Opcional'),
                            Forms\Components\Textarea::make('notes')->label('Observações')->rows(2),
                        ];
                    })
                    ->action(function (CustomerBillingReceipt $record, array $data): void {
                        try {
                            app(CustomerBillingReceiptService::class)->addPayment($record, $data);
                            $fresh = $record->fresh();
                            $body = $fresh->status === CustomerReceiptStatus::PAID
                                ? 'Comprovante quitado integralmente.'
                                : 'Saldo restante: R$ '.number_format($fresh->remaining_amount, 2, ',', '.');
                            Notification::make()->success()->title('Recebimento registrado')->body($body)->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Erro ao registrar recebimento')
                                ->body($e->getMessage())->send();
                        }
                    }),

                // ── Histórico de Recebimentos ─────────────────────────────────
                Tables\Actions\Action::make('viewPayments')
                    ->label('Histórico de Recebimentos')
                    ->icon('heroicon-o-clock')->color('gray')
                    ->visible(fn (CustomerBillingReceipt $r) => in_array($r->status, [
                        CustomerReceiptStatus::PARTIALLY_PAID,
                        CustomerReceiptStatus::PAID,
                    ]))
                    ->modalHeading(fn (CustomerBillingReceipt $r) => 'Recebimentos — '.$r->formatted_number)
                    ->modalContent(function (CustomerBillingReceipt $record): \Illuminate\Contracts\View\View {
                        $payments = $record->payments()->with('bankAccount')->get();

                        return view('filament.modals.receipt-payments-history', [
                            'receipt' => $record,
                            'payments' => $payments,
                            'label' => 'Recebimento',
                        ]);
                    })
                    ->modalSubmitAction(false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(false)
                        ->before(function (Collection $records) {
                            foreach ($records->filter(fn ($r) => $r->isEditable()) as $r) {
                                if (! empty($r->delivery_ids)) {
                                    ProductionDelivery::whereIn('id', $r->delivery_ids)
                                        ->where('billing_receipt_id', $r->id)
                                        ->update(['billing_receipt_id' => null]);
                                }
                            }
                        }),
                ]),
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Dados para PDF — comprovante individual do cliente
    // ─────────────────────────────────────────────────────────────────────────

    private static function buildCustomerReceiptData(
        Collection $distributions,
        CustomerBillingReceipt $receipt,
        ?Tenant $tenant,
        ?SalesProject $project,
        ?Customer $customer
    ): array {
        $productRows = $distributions
            ->groupBy(fn ($d) => $d->product_id)
            ->map(function ($group) {
                $first = $group->first();
                $qty = $group->sum(fn ($d) => (float) $d->quantity);
                $gross = $group->sum(fn ($d) => (float) $d->quantity * (float) $d->unit_price);

                return [
                    'product' => $first->product?->name ?? '—',
                    'unit' => $first->product?->unit ?? 'kg',
                    'quantity' => $qty,
                    'unit_price' => (float) $first->unit_price,
                    'gross' => $gross,
                ];
            })
            ->values()->toArray();

        $totalGross = array_sum(array_column($productRows, 'gross'));
        $totalFees = (float) ($receipt->total_fees ?? 0);
        $totalNet = (float) ($receipt->total_net ?? $totalGross);

        $feeBreakdown = [];
        $snapshot = $receipt->fee_snapshot ?? [];
        if (! empty($snapshot['fees'])) {
            foreach ($snapshot['fees'] as $fee) {
                $feeBreakdown[] = [
                    'name' => $fee['name'] ?? '—',
                    'amount' => (float) ($fee['amount'] ?? 0),
                    'nature' => $fee['nature'] ?? 'discount',
                ];
            }
        }

        // Período das entregas (primeira → última data)
        $dates = $distributions->pluck('delivery_date')->filter()->sort();
        $periodLabel = $dates->isNotEmpty()
            ? ($dates->first()->format('d/m/Y') === $dates->last()->format('d/m/Y')
                ? $dates->first()->format('d/m/Y')
                : $dates->first()->format('d/m/Y').' a '.$dates->last()->format('d/m/Y'))
            : null;

        return compact(
            'tenant', 'project', 'customer', 'receipt',
            'productRows', 'totalGross', 'totalFees', 'totalNet', 'feeBreakdown',
            'periodLabel'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Dados para PDF — relatório de organização
    // ─────────────────────────────────────────────────────────────────────────

    private static function buildOrganizationReportData(
        Collection $distributions,
        CustomerBillingReceipt $receipt,
        ?Tenant $tenant,
        ?SalesProject $project,
        ?Organization $organization
    ): array {
        // Todos os clientes distintos (para o rodapé)
        $customers = $distributions->pluck('customer')->filter()->unique('id')->sortBy('name')->values();

        // Agrupa distribuições pela tabela de preço do cliente (null → chave 0)
        $byPriceTable = $distributions->groupBy(fn ($d) => $d->customer?->price_table_id ?? 0);

        $priceGroups = $byPriceTable->map(function ($groupDists) {
            $groupCustomers = $groupDists->pluck('customer')->filter()->unique('id')->sortBy('name')->values();
            $ptName = $groupCustomers->first()?->priceTable?->name ?? 'Tabela Padrão';

            $table = [];
            foreach ($groupDists as $d) {
                $pid = $d->product_id;
                if (! isset($table[$pid])) {
                    $table[$pid] = [
                        'product' => $d->product?->name ?? 'Produto #'.$pid,
                        'unit' => $d->product?->unit ?? 'kg',
                        'unit_price' => (float) $d->unit_price,
                        'by_customer' => [],
                        'total_qty' => 0.0,
                        'total_gross' => 0.0,
                    ];
                }
                $cid = $d->customer_id;
                $qty = (float) $d->quantity;
                $table[$pid]['by_customer'][$cid] = ($table[$pid]['by_customer'][$cid] ?? 0.0) + $qty;
                $table[$pid]['total_qty'] += $qty;
                $table[$pid]['total_gross'] += $qty * (float) $d->unit_price;
            }

            return [
                'price_table_name' => $ptName,
                'customers' => $groupCustomers,
                'table' => $table,
                'subtotal_gross' => array_sum(array_column($table, 'total_gross')),
            ];
        })->values()->all();

        $totalGross = collect($priceGroups)->sum('subtotal_gross');
        $totalFees = (float) ($receipt->total_fees ?? 0);
        $totalNet = (float) ($receipt->total_net ?? $totalGross);
        $multiplePriceTables = count($priceGroups) > 1;

        // Período das entregas (primeira → última data)
        $dates = $distributions->pluck('delivery_date')->filter()->sort();
        $periodLabel = $dates->isNotEmpty()
            ? ($dates->first()->format('d/m/Y') === $dates->last()->format('d/m/Y')
                ? $dates->first()->format('d/m/Y')
                : $dates->first()->format('d/m/Y').' a '.$dates->last()->format('d/m/Y'))
            : null;

        return compact(
            'tenant', 'project', 'organization', 'receipt',
            'customers', 'priceGroups', 'multiplePriceTables',
            'totalGross', 'totalFees', 'totalNet', 'periodLabel'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Modal de distribuições
    // ─────────────────────────────────────────────────────────────────────────

    private static function renderDistributionsModal(CustomerBillingReceipt $receipt): \Illuminate\View\View
    {
        $rows = [];
        if (! empty($receipt->delivery_ids)) {
            $rows = ProductionDelivery::whereIn('id', $receipt->delivery_ids)
                ->with(['product', 'associate.user', 'customer'])->orderBy('delivery_date')->get()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'date' => $d->delivery_date?->format('d/m/Y') ?? '—',
                    'product' => $d->product?->name ?? '—',
                    'customer' => $d->customer?->name ?? '—',
                    'associate' => $d->associate?->display_name ?? '—',
                    'quantity' => number_format((float) $d->quantity, 4, ',', '.'),
                    'unit_price' => number_format((float) $d->unit_price, 2, ',', '.'),
                    'gross' => number_format((float) $d->quantity * (float) $d->unit_price, 2, ',', '.'),
                    'billing_status' => $d->billing_status?->getLabel() ?? '—',
                ])->toArray();
        }
        $totalGross = array_reduce($rows, fn ($c, $r) => $c + (float) str_replace(['.', ','], ['', '.'], $r['gross']), 0.0);

        return view('filament.modals.customer-billing-distributions',
            compact('receipt', 'rows', 'totalGross'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Páginas
    // ─────────────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerBillingReceipts::route('/'),
            'create' => Pages\CreateCustomerBillingReceipt::route('/create'),
            'edit' => Pages\EditCustomerBillingReceipt::route('/{record}/edit'),
        ];
    }
}
