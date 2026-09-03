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
use App\Models\DeliveryConferenceSheet;
use App\Models\Organization;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\CustomerBillingReceiptService;
use App\Services\DeliveryParentRecoveryService;
use App\Services\ReceiptFeeColumnService;
use App\Services\TemplatedPdfService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class CustomerBillingReceiptResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = CustomerBillingReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Projetos de Venda';

    protected static ?string $modelLabel = 'Cobrança ao Comprador';

    protected static ?string $pluralModelLabel = 'Cobranças aos Compradores';

    protected static ?int $navigationSort = 6;

    // ─────────────────────────────────────────────────────────────────────────
    //  Formulário (somente DRAFT)
    // ─────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Projeto e Destinatário')
                ->description('Selecione um ou mais projetos do mesmo tipo e, em seguida, o comprador OU a organização em comum.')
                ->schema([
                    Forms\Components\Select::make('project_ids')
                        ->disabled(fn ($record): bool => $record !== null)
                        ->dehydrated()
                        ->label('Projetos de Venda')
                        ->options(fn () => SalesProject::where('tenant_id', session('tenant_id'))
                            ->orderBy('start_date')->orderBy('title')->get()
                            ->mapWithKeys(fn (SalesProject $project): array => [
                                $project->id => sprintf(
                                    '%s · %s · %s a %s',
                                    $project->title,
                                    $project->type_label,
                                    $project->start_date?->format('d/m/Y') ?? 'sem início',
                                    $project->end_date?->format('d/m/Y') ?? 'sem fim',
                                ),
                            ]))
                        ->multiple()
                        ->searchable()->preload()->required()->live()
                        ->helperText('Para uma cobrança mista, todos devem ter o mesmo tipo (por exemplo, PNAE) e possuir entregas para o mesmo destinatário.')
                        ->afterStateUpdated(function (Forms\Set $set) {
                            $set('customer_id', null);
                            $set('organization_id', null);
                            $set('delivery_ids', []);
                        }),

                    // ── Comprador (mutuamente exclusivo com organização) ────────
                    Forms\Components\Select::make('customer_id')
                        ->label('Comprador')
                        ->options(function (Get $get) {
                            $projectIds = static::normalizeProjectIds($get('project_ids'));
                            if ($projectIds === []) {
                                return [];
                            }
                            $ids = ProductionDelivery::where('tenant_id', session('tenant_id'))
                                ->whereIn('sales_project_id', $projectIds)
                                ->whereNotNull('parent_delivery_id')
                                ->whereNotNull('customer_id')
                                ->where('status', DeliveryStatus::APPROVED->value)
                                ->select('customer_id')
                                ->groupBy('customer_id')
                                ->havingRaw('COUNT(DISTINCT sales_project_id) = ?', [count($projectIds)])
                                ->pluck('customer_id');

                            return Customer::whereIn('id', $ids)->orderBy('name')
                                ->pluck('name', 'id')->toArray();
                        })
                        ->searchable()->nullable()
                        ->placeholder('— Selecione um comprador —')
                        ->helperText('Somente compradores com distribuições aprovadas em todos os projetos selecionados.')
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            if ($state) {
                                $set('organization_id', null);
                            }
                            $set('delivery_ids', []);
                        })
                        ->visible(fn (Get $get) => static::normalizeProjectIds($get('project_ids')) !== []),

                    // ── Organização (mutuamente exclusiva com comprador) ────────
                    Forms\Components\Select::make('organization_id')
                        ->label('Organização')
                        ->options(function (Get $get) {
                            $projectIds = static::normalizeProjectIds($get('project_ids'));
                            if ($projectIds === []) {
                                return [];
                            }
                            $orgIds = ProductionDelivery::query()
                                ->join('customers', 'customers.id', '=', 'production_deliveries.customer_id')
                                ->where('production_deliveries.tenant_id', session('tenant_id'))
                                ->where('customers.tenant_id', session('tenant_id'))
                                ->whereIn('production_deliveries.sales_project_id', $projectIds)
                                ->whereNotNull('parent_delivery_id')
                                ->whereNotNull('customers.organization_id')
                                ->where('production_deliveries.status', DeliveryStatus::APPROVED->value)
                                ->select('customers.organization_id')
                                ->groupBy('customers.organization_id')
                                ->havingRaw('COUNT(DISTINCT production_deliveries.sales_project_id) = ?', [count($projectIds)])
                                ->pluck('customers.organization_id');

                            return Organization::whereIn('id', $orgIds)
                                ->where('tenant_id', session('tenant_id'))
                                ->orderBy('name')->pluck('name', 'id')->toArray();
                        })
                        ->searchable()->nullable()
                        ->placeholder('— Ou selecione uma organização —')
                        ->helperText('Agrupa os compradores desta organização que possuem distribuições nos projetos selecionados.')
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            if ($state) {
                                $set('customer_id', null);
                            }
                            $set('delivery_ids', []);
                        })
                        ->visible(fn (Get $get) => static::normalizeProjectIds($get('project_ids')) !== []),

                    Forms\Components\DatePicker::make('issued_at')
                        ->label('Data de Emissão')
                        ->default(today())->required()->native(false),

                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')->rows(2)->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Identificadores do Comprovante')
                ->description('Os dois numeros ficam gravados. O projeto decide qual deles sera impresso.')
                ->schema([
                    Forms\Components\TextInput::make('tenant_receipt_number')
                        ->label('Sequencia geral da organizacao')
                        ->numeric()->integer()->minValue(1)->required(),
                    Forms\Components\TextInput::make('tenant_receipt_year')
                        ->label('Ano da sequencia geral')
                        ->numeric()->integer()->minValue(2020)->maxValue(2099)->required(),
                    Forms\Components\TextInput::make('project_receipt_number')
                        ->label('Sequencia deste projeto')
                        ->numeric()->integer()->minValue(1)->required(),
                    Forms\Components\TextInput::make('project_receipt_year')
                        ->label('Ano de referencia do projeto')
                        ->numeric()->integer()->minValue(2020)->maxValue(2099)->required(),
                    Forms\Components\Placeholder::make('numbering_preview')
                        ->label('Numeros disponiveis')
                        ->content(fn ($record): string => $record
                            ? 'Geral: '.$record->tenant_formatted_number.' | Projeto: '.$record->project_formatted_number
                            : 'As duas sequencias serao reservadas ao criar o comprovante.')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->visible(fn ($record): bool => $record !== null)
                ->collapsible(),

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
                                $pids = static::normalizeProjectIds($get('project_ids'));
                                $cid = (int) $get('customer_id');
                                $oid = (int) $get('organization_id');
                                $locked = static::getLockedDistributionIds($record?->id);
                                $all = array_keys(static::buildDistributionOptions($pids, $cid, $oid, $record?->id));
                                $free = array_values(array_diff($all, $locked));
                                $set('delivery_ids', array_map('strval', $free));
                            })
                            ->visible(fn (Get $get) => static::normalizeProjectIds($get('project_ids')) !== []
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
                                static::normalizeProjectIds($get('project_ids')),
                                (int) $get('customer_id'),
                                (int) $get('organization_id'),
                                $record?->id
                            );
                        })
                        ->descriptions(function (Get $get, $record) {
                            return static::buildDistributionDescriptions(
                                static::normalizeProjectIds($get('project_ids')),
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
                            $pids = static::normalizeProjectIds($get('project_ids'));
                            $cid = (int) $get('customer_id');
                            $oid = (int) $get('organization_id');
                            if ($pids === [] || (! $cid && ! $oid)) {
                                return 'Selecione os projetos e um comprador ou organização.';
                            }
                            $total = count(static::buildDistributionOptions($pids, $cid, $oid, $record?->id));
                            $locked = count(static::getLockedDistributionIds($record?->id));
                            $free = max(0, $total - $locked);

                            return "{$free} disponível(is) para seleção — {$locked} já em outro comprovante (laranja)";
                        })
                        ->noSearchResultsMessage('Nenhuma distribuição encontrada.')
                        ->visible(fn (Get $get) => static::normalizeProjectIds($get('project_ids')) !== []
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
            ->with('project')
            ->get([
                'id', 'sales_project_id', 'delivery_ids', 'receipt_year', 'receipt_number', 'receipt_label',
                'tenant_receipt_year', 'tenant_receipt_number', 'project_receipt_year', 'project_receipt_number',
            ]);

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
    public static function buildDistributionOptions(int|array $projectIds, int $customerId, int $orgId, ?int $currentReceiptId): array
    {
        $query = static::baseDistributionQuery($projectIds, $customerId, $orgId, $currentReceiptId);
        if (! $query) {
            return [];
        }

        return $query->get()
            ->mapWithKeys(fn ($d) => [
                $d->id => sprintf(
                    '%s — %s — %s — %s — %s %s × R$ %s',
                    $d->salesProject?->title ?? 'Projeto #'.$d->sales_project_id,
                    $d->delivery_date?->format('d/m/Y') ?? '—',
                    $d->customer?->name ?? '—',
                    $d->product?->name ?? 'Produto #'.$d->product_id,
                    number_format((float) $d->quantity, 2, ',', '.'),
                    $d->product?->unit ?? 'kg',
                    number_format((float) $d->unit_price, 2, ',', '.')
                ),
            ])
            ->toArray();
    }

    /** Descriptions [id => label] para o CheckboxList — inclui aviso para itens bloqueados. */
    public static function buildDistributionDescriptions(int|array $projectIds, int $customerId, int $orgId, ?int $currentReceiptId): array
    {
        $query = static::baseDistributionQuery($projectIds, $customerId, $orgId, $currentReceiptId);
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
     * Query base: distribuições aprovadas do projeto para o comprador/organização.
     * Inclui as do próprio comprovante em edição (para reexibir sem filtrar).
     * Distribuições em outros comprovantes são incluídas nas opções mas marcadas
     * como desabilitadas via disableOptionWhen().
     */
    private static function baseDistributionQuery(int|array $projectIds, int $customerId, int $orgId, ?int $currentReceiptId)
    {
        $projectIds = static::normalizeProjectIds($projectIds);
        if ($projectIds === [] || (! $customerId && ! $orgId)) {
            return null;
        }

        $tenantId = session('tenant_id');
        $query = ProductionDelivery::where('tenant_id', $tenantId)
            ->whereIn('sales_project_id', $projectIds)
            ->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED->value)
            ->with(['salesProject:id,title', 'product', 'customer'])
            ->orderBy('sales_project_id')->orderBy('delivery_date');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        } elseif ($orgId) {
            // Todos os compradores da organização com distribuições neste projeto
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

    /** @return list<int> */
    public static function normalizeProjectIds(mixed $projectIds): array
    {
        return collect(is_array($projectIds) ? $projectIds : [$projectIds])
            ->map(fn ($id): int => (int) $id)
            ->filter()->unique()->values()->all();
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
                    ->sortable(['receipt_year', 'receipt_number'])
                    ->description(fn (CustomerBillingReceipt $record): string => 'Geral '.$record->tenant_formatted_number.' | Projeto '.$record->project_formatted_number),

                Tables\Columns\TextColumn::make('project_summary')
                    ->label('Projeto(s)')->limit(45)->default('— Avulso —'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Comprador')->searchable()->limit(30)->placeholder('—'),

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
                        ->orderBy('title')->pluck('title', 'id'))
                    ->query(function ($query, array $data) {
                        $projectId = (int) ($data['value'] ?? 0);
                        if (! $projectId) {
                            return $query;
                        }

                        return $query->where(function ($nested) use ($projectId): void {
                            $nested->where('sales_project_id', $projectId)
                                ->orWhereHas('projects', fn ($projects) => $projects->whereKey($projectId));
                        });
                    }),
            ])
            ->actions([
                // ── Imprimir PDF ──────────────────────────────────────────────
                Tables\Actions\Action::make('printPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->form(function (CustomerBillingReceipt $record): array {
                        $service = app(ReceiptFeeColumnService::class);
                        $definitions = $record->project
                            ? $service->definitions($record->project, 'customer', $record->fee_snapshot)
                            : [];

                        return [
                            Forms\Components\CheckboxList::make('visible_columns')
                                ->label('Colunas do PDF')
                                ->options([
                                    'unit_price' => 'Valor unitário',
                                    'gross' => 'Valor bruto',
                                    'net' => 'Valor líquido',
                                ] + $service->options($definitions))
                                ->default(fn (): array => session(
                                    "receipt_print.customer.{$record->tenant_id}.{$record->sales_project_id}.columns",
                                    ['unit_price', 'gross'],
                                ))
                                ->columns(2)
                                ->bulkToggleable(),
                            Forms\Components\Select::make('table_scale')
                                ->label('Escala da tabela')
                                ->options([
                                    100 => '100% · Normal',
                                    90 => '90% · Compacta',
                                    80 => '80% · Reduzida',
                                    70 => '70% · Muito reduzida',
                                ])
                                ->default(fn (): int => (int) session(
                                    "receipt_print.customer.{$record->tenant_id}.{$record->sales_project_id}.scale",
                                    100,
                                ))
                                ->required(),
                        ];
                    })
                    ->modalSubmitActionLabel('Gerar PDF')
                    ->action(function (CustomerBillingReceipt $record, array $data): mixed {
                        $integrity = app(DeliveryParentRecoveryService::class)
                            ->diagnosisForCustomerReceipt($record);
                        if ($integrity['recoverable'] > 0 || $integrity['unrecoverable'] > 0) {
                            Notification::make()->danger()
                                ->title('Comprovante com vínculos inconsistentes')
                                ->body($integrity['recoverable'] > 0
                                    ? "Há {$integrity['recoverable']} entrega(s)-pai excluída(s). Use a ação Corrigir integridade antes de imprimir."
                                    : 'Há distribuições sem uma entrega-pai válida. Revise a integridade antes de imprimir.')
                                ->persistent()
                                ->send();

                            return null;
                        }

                        $requestedColumns = is_array($data['visible_columns'] ?? null)
                            ? $data['visible_columns']
                            : ['unit_price', 'gross'];
                        $tableScale = in_array((int) ($data['table_scale'] ?? 100), [70, 80, 90, 100], true)
                            ? (int) $data['table_scale']
                            : 100;
                        if (empty($record->delivery_ids)) {
                            Notification::make()->warning()
                                ->title('Sem distribuições')->body('Adicione distribuições antes de gerar o PDF.')->send();

                            return null;
                        }
                        $tenant = Tenant::find($record->tenant_id);
                        $project = $record->project;
                        $projects = $record->includedProjects();
                        $projectIds = $projects->pluck('id')->map(fn ($id): int => (int) $id)->all();
                        $customer = $record->customer;
                        $organization = $record->organization;
                        $distributions = ProductionDelivery::query()
                            ->where('tenant_id', $record->tenant_id)
                            ->whereIn('sales_project_id', $projectIds)
                            ->whereNotNull('parent_delivery_id')
                            ->whereIn('id', $record->delivery_ids)
                            ->with(['product', 'customer.priceTable'])->orderBy('delivery_date')->get();

                        if ($distributions->count() !== count(array_unique(array_map('intval', $record->delivery_ids)))) {
                            Notification::make()->danger()
                                ->title('Comprovante inconsistente')
                                ->body('Uma ou mais distribuições não pertencem mais a este tenant ou aos projetos da cobrança.')
                                ->send();

                            return null;
                        }

                        $isOrgReport = $organization && ! $customer;

                        if ($isOrgReport) {
                            // Relatório por organização: agrupa por produto x comprador
                            $view = 'pdf.customer-organization-receipt';
                            $data = static::buildOrganizationReportData(
                                $distributions,
                                $record,
                                $tenant,
                                $project,
                                $organization,
                                $projects,
                                $requestedColumns,
                            );
                        } else {
                            // Comprovante individual do comprador
                            $view = 'pdf.customer-billing-receipt';
                            $data = static::buildCustomerReceiptData(
                                $distributions,
                                $record,
                                $tenant,
                                $project,
                                $customer,
                                $projects,
                                $requestedColumns,
                            );
                        }
                        $data['table_scale'] = $tableScale;
                        session([
                            "receipt_print.customer.{$record->tenant_id}.{$record->sales_project_id}.columns" => $data['visibleColumns'],
                            "receipt_print.customer.{$record->tenant_id}.{$record->sales_project_id}.scale" => $tableScale,
                        ]);

                        $svc = app(TemplatedPdfService::class);
                        $pdf = $svc->generateSystemPdf($view, $data,
                            ['paper' => 'a4', 'orientation' => 'portrait']);

                        $label = str_replace('/', '-', $record->formatted_number);
                        $name = Str::slug(
                            $isOrgReport ? ($organization->name ?? 'org') : ($customer?->name ?? 'comprador')
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
                        $name = Str::slug(
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

                Tables\Actions\Action::make('viewConferenceSheets')
                    ->label('Folhas de conferência')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('info')
                    ->visible(fn (): bool => auth()->user()?->can('viewAny', DeliveryConferenceSheet::class) ?? false)
                    ->modalHeading(fn (CustomerBillingReceipt $r) => 'Folhas de conferência — '.$r->formatted_number)
                    ->modalContent(fn (CustomerBillingReceipt $r) => static::renderConferenceSheetsModal($r))
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),

                Tables\Actions\Action::make('repairIntegrity')
                    ->label('Corrigir integridade')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->modalHeading(fn (CustomerBillingReceipt $record): string => 'Verificar '.$record->formatted_number)
                    ->modalDescription(function (CustomerBillingReceipt $record): string {
                        $diagnosis = app(DeliveryParentRecoveryService::class)
                            ->diagnosisForCustomerReceipt($record);
                        if ($diagnosis['recoverable'] === 0 && $diagnosis['unrecoverable'] === 0) {
                            return 'Nenhum vínculo quebrado foi encontrado neste comprovante.';
                        }

                        $message = $diagnosis['recoverable'] > 0
                            ? "{$diagnosis['recoverable']} entrega(s)-pai excluída(s) podem ser restauradas sem alterar valores, distribuições ou pagamentos."
                            : '';
                        if ($diagnosis['unrecoverable'] > 0) {
                            $message .= " {$diagnosis['unrecoverable']} distribuição(ões) exigem revisão manual porque a entrega-pai não existe ou pertence a outro contexto.";
                        }

                        return trim($message);
                    })
                    ->requiresConfirmation()
                    ->modalSubmitActionLabel('Restaurar entregas-pai')
                    ->action(function (CustomerBillingReceipt $record): void {
                        $actor = auth()->user();
                        if (! $actor) {
                            Notification::make()->danger()->title('Sessão expirada')->send();

                            return;
                        }

                        $result = app(DeliveryParentRecoveryService::class)
                            ->restoreForCustomerReceipt($record, $actor);
                        if ($result['restored'] !== []) {
                            Notification::make()->success()
                                ->title('Integridade restaurada')
                                ->body(count($result['restored']).' entrega(s)-pai restaurada(s). Os dados financeiros foram preservados.')
                                ->send();
                        } elseif ($result['unresolved'] === []) {
                            Notification::make()->info()
                                ->title('Nenhuma correção necessária')
                                ->body('Os vínculos deste comprovante já estão íntegros.')
                                ->send();
                        }
                        if ($result['unresolved'] !== []) {
                            Notification::make()->warning()
                                ->title('Revisão adicional necessária')
                                ->body(count($result['unresolved']).' distribuição(ões) não puderam ser corrigidas automaticamente.')
                                ->persistent()
                                ->send();
                        }
                    }),

                // ── Editar (somente DRAFT) ────────────────────────────────────
                Tables\Actions\EditAction::make()
                    ->visible(fn (CustomerBillingReceipt $r) => $r->isEditable()),

                // ── Excluir (somente DRAFT) ───────────────────────────────────
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (CustomerBillingReceipt $r) => $r->isEditable())
                    ->using(function (CustomerBillingReceipt $r): bool {
                        app(CustomerBillingReceiptService::class)->discardDraftReceipt($r);

                        return true;
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
                            Forms\Components\Hidden::make('operation_key')
                                ->default(fn (): string => (string) Str::uuid())
                                ->required(),

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
                        ->using(function (Collection $records): void {
                            foreach ($records as $record) {
                                app(CustomerBillingReceiptService::class)->discardDraftReceipt($record);
                            }
                        }),
                ]),
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Dados para PDF — comprovante individual do comprador
    // ─────────────────────────────────────────────────────────────────────────

    private static function buildCustomerReceiptData(
        Collection $distributions,
        CustomerBillingReceipt $receipt,
        ?Tenant $tenant,
        ?SalesProject $project,
        ?Customer $customer,
        Collection $projects,
        array $requestedColumns = [],
    ): array {
        $feeColumnService = app(ReceiptFeeColumnService::class);
        $feeColumns = $project
            ? $feeColumnService->definitions($project, 'customer', $receipt->fee_snapshot)
            : [];
        $visibleColumns = $feeColumnService->sanitize(
            $requestedColumns,
            $feeColumns,
            ['unit_price', 'gross', 'net'],
        );
        $projectMap = $projects->keyBy('id');
        $projectSnapshots = collect(data_get($receipt->fee_snapshot, 'project_snapshots', []));
        $productRows = $distributions
            ->groupBy(fn ($d) => $d->sales_project_id.'|'.$d->product_id)
            ->map(function ($group) use ($feeColumnService, $projectMap, $projectSnapshots, $receipt, $projects) {
                $first = $group->first();
                $rowProject = $projectMap->get((int) $first->sales_project_id);
                $projectSnapshot = $projectSnapshots->get((string) $first->sales_project_id)
                    ?? ($projects->count() === 1 ? $receipt->fee_snapshot : null);
                $rowFeeColumns = $rowProject
                    ? $feeColumnService->definitions($rowProject, 'customer', $projectSnapshot)
                    : [];
                $qty = $group->sum(fn ($d) => (float) $d->quantity);
                $gross = $group->sum(fn ($d) => (float) $d->quantity * (float) $d->unit_price);
                $feeValues = $feeColumnService->totals($group, $rowFeeColumns);
                $net = $gross;
                foreach ($rowFeeColumns as $fee) {
                    $amount = $feeValues[$fee['key']] ?? 0;
                    $net += $fee['nature'] === 'accrual' ? $amount : -$amount;
                }

                return [
                    'project_id' => (int) $first->sales_project_id,
                    'project' => $rowProject?->title ?? 'Projeto #'.$first->sales_project_id,
                    'product' => $first->product?->name ?? '—',
                    'unit' => $first->product?->unit ?? 'kg',
                    'quantity' => $qty,
                    'unit_price' => (float) $first->unit_price,
                    'gross' => $gross,
                    'fee_values' => $feeValues,
                    'net' => $net,
                ];
            })
            ->values()->toArray();

        $totalGross = array_sum(array_column($productRows, 'gross'));
        $totalFees = (float) ($receipt->total_fees ?? 0);
        $totalNet = (float) ($receipt->total_net ?? $totalGross);

        $feeBreakdown = [];
        $snapshot = $receipt->fee_snapshot ?? [];
        if (! empty($snapshot['fees'])) {
            foreach (array_values($snapshot['fees']) as $fee) {
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

        $projectPeriods = static::projectPeriods($projects, $distributions);
        $isMultiProject = $projects->count() > 1;
        $periodsByProject = collect($projectPeriods)->keyBy(fn (array $item): int => (int) $item['project']->id);
        $projectGroups = $projects->map(function (SalesProject $groupProject) use (
            $productRows,
            $projectSnapshots,
            $receipt,
            $projects,
            $feeColumnService,
            $periodsByProject,
        ): array {
            $rows = collect($productRows)
                ->where('project_id', (int) $groupProject->id)
                ->values();
            $projectSnapshot = $projectSnapshots->get((string) $groupProject->id)
                ?? ($projects->count() === 1 ? $receipt->fee_snapshot : null);
            $groupFeeColumns = $feeColumnService->definitions($groupProject, 'customer', $projectSnapshot);
            $feeTotals = collect($groupFeeColumns)->mapWithKeys(fn (array $fee): array => [
                $fee['key'] => $rows->sum(fn (array $row): float => (float) ($row['fee_values'][$fee['key']] ?? 0)),
            ])->all();

            return [
                'project' => $groupProject,
                'period' => data_get($periodsByProject->get((int) $groupProject->id), 'period', 'Sem entregas'),
                'rows' => $rows->all(),
                'fee_columns' => $groupFeeColumns,
                'fee_totals' => $feeTotals,
                'subtotal_gross' => $rows->sum('gross'),
                'subtotal_net' => $rows->sum('net'),
            ];
        })->filter(fn (array $group): bool => $group['rows'] !== [])->values()->all();

        return compact(
            'tenant', 'project', 'customer', 'receipt',
            'productRows', 'projectGroups', 'totalGross', 'totalFees', 'totalNet', 'feeBreakdown',
            'periodLabel', 'feeColumns', 'visibleColumns', 'projects', 'projectPeriods', 'isMultiProject'
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
        ?Organization $organization,
        Collection $projects,
        array $requestedColumns = [],
    ): array {
        $feeColumnService = app(ReceiptFeeColumnService::class);
        $feeColumns = $project
            ? $feeColumnService->definitions($project, 'customer', $receipt->fee_snapshot)
            : [];
        $visibleColumns = $feeColumnService->sanitize(
            $requestedColumns,
            $feeColumns,
            ['unit_price', 'gross', 'net'],
        );
        // Todos os compradores distintos (para o rodapé)
        $customers = $distributions->pluck('customer')->filter()->unique('id')->sortBy('name')->values();

        // Agrupa distribuições pela tabela de preço do comprador (null → chave 0)
        $projectMap = $projects->keyBy('id');
        $projectSnapshots = collect(data_get($receipt->fee_snapshot, 'project_snapshots', []));
        $byPriceTable = $distributions->groupBy(fn ($d) => $d->sales_project_id.'|'.($d->customer?->price_table_id ?? 0));

        $priceGroups = $byPriceTable->map(function ($groupDists) use ($feeColumnService, $projectMap, $projectSnapshots, $feeColumns, $receipt, $projects) {
            $firstDistribution = $groupDists->first();
            $rowProject = $projectMap->get((int) $firstDistribution->sales_project_id);
            $projectSnapshot = $projectSnapshots->get((string) $firstDistribution->sales_project_id)
                ?? ($projects->count() === 1 ? $receipt->fee_snapshot : null);
            $rowFeeColumns = $rowProject
                ? $feeColumnService->definitions($rowProject, 'customer', $projectSnapshot)
                : [];
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
                        'fee_values' => array_fill_keys(array_column($feeColumns, 'key'), 0.0),
                    ];
                }
                $cid = $d->customer_id;
                $qty = (float) $d->quantity;
                $table[$pid]['by_customer'][$cid] = ($table[$pid]['by_customer'][$cid] ?? 0.0) + $qty;
                $table[$pid]['total_qty'] += $qty;
                $table[$pid]['total_gross'] += $qty * (float) $d->unit_price;
                foreach ($feeColumnService->values($qty * (float) $d->unit_price, $rowFeeColumns) as $key => $amount) {
                    $table[$pid]['fee_values'][$key] = ($table[$pid]['fee_values'][$key] ?? 0) + $amount;
                }
            }

            $feeTotals = $feeColumnService->totals($groupDists, $rowFeeColumns);
            $subtotalGross = array_sum(array_column($table, 'total_gross'));
            $subtotalNet = $subtotalGross;
            foreach ($rowFeeColumns as $fee) {
                $amount = $feeTotals[$fee['key']] ?? 0;
                $subtotalNet += $fee['nature'] === 'accrual' ? $amount : -$amount;
            }

            return [
                'project_id' => (int) $firstDistribution->sales_project_id,
                'project_name' => $rowProject?->title ?? 'Projeto #'.$firstDistribution->sales_project_id,
                'price_table_name' => $ptName,
                'customers' => $groupCustomers,
                'table' => $table,
                'subtotal_gross' => $subtotalGross,
                'subtotal_net' => $subtotalNet,
                'fee_totals' => $feeTotals,
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

        $projectPeriods = static::projectPeriods($projects, $distributions);
        $isMultiProject = $projects->count() > 1;

        return compact(
            'tenant', 'project', 'organization', 'receipt',
            'customers', 'priceGroups', 'multiplePriceTables',
            'totalGross', 'totalFees', 'totalNet', 'periodLabel',
            'feeColumns', 'visibleColumns', 'projects', 'projectPeriods', 'isMultiProject'
        );
    }

    /** @return array<int, array{project: SalesProject, period: string}> */
    private static function projectPeriods(Collection $projects, Collection $distributions): array
    {
        return $projects->map(function (SalesProject $project) use ($distributions): array {
            $dates = $distributions->where('sales_project_id', $project->id)
                ->pluck('delivery_date')->filter()->sort();
            $period = $dates->isEmpty()
                ? 'Sem entregas'
                : ($dates->first()->format('d/m/Y') === $dates->last()->format('d/m/Y')
                    ? $dates->first()->format('d/m/Y')
                    : $dates->first()->format('d/m/Y').' a '.$dates->last()->format('d/m/Y'));

            return ['project' => $project, 'period' => $period];
        })->values()->all();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Modal de distribuições
    // ─────────────────────────────────────────────────────────────────────────

    private static function renderDistributionsModal(CustomerBillingReceipt $receipt): View
    {
        $rows = [];
        if (! empty($receipt->delivery_ids)) {
            $rows = ProductionDelivery::whereIn('id', $receipt->delivery_ids)
                ->with(['salesProject:id,title', 'product', 'associate.user', 'customer'])->orderBy('delivery_date')->get()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'date' => $d->delivery_date?->format('d/m/Y') ?? '—',
                    'project' => $d->salesProject?->title ?? '—',
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

    private static function renderConferenceSheetsModal(CustomerBillingReceipt $receipt): View
    {
        $distributionIds = collect($receipt->delivery_ids)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $projectIds = $receipt->projectIds();
        $sheets = DeliveryConferenceSheet::query()
            ->where('tenant_id', $receipt->tenant_id)
            ->whereIn('sales_project_id', $projectIds)
            ->whereHas('distributions', fn ($query) => $query->whereIn('production_deliveries.id', $distributionIds))
            ->with(['customer:id,name', 'organization:id,name'])
            ->withCount('distributions')
            ->withCount([
                'distributions as receipt_distributions_count' => fn ($query) => $query->whereIn('production_deliveries.id', $distributionIds),
            ])
            ->orderByDesc('issued_at')
            ->orderByDesc('created_at')
            ->get();

        $coveredIds = $distributionIds->isEmpty()
            ? collect()
            : DB::table('delivery_conference_sheet_items as item')
                ->join('delivery_conference_sheets as sheet', 'sheet.id', '=', 'item.delivery_conference_sheet_id')
                ->where('sheet.tenant_id', $receipt->tenant_id)
                ->whereIn('sheet.sales_project_id', $projectIds)
                ->whereNull('sheet.invalidated_at')
                ->whereIn('item.distribution_id', $distributionIds)
                ->pluck('item.distribution_id')
                ->map(fn ($id): int => (int) $id)
                ->unique();

        return view('filament.modals.customer-billing-conference-sheets', [
            'receipt' => $receipt,
            'sheets' => $sheets,
            'totalDistributions' => $distributionIds->count(),
            'coveredDistributions' => $coveredIds->count(),
            'uncoveredDistributions' => $distributionIds->diff($coveredIds)->count(),
        ]);
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
