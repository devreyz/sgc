<?php

namespace App\Filament\Resources;

use App\Enums\CustomerReceiptStatus;
use App\Enums\DeliveryStatus;
use App\Enums\PaymentMethod;
use App\Exports\CustomerBillingReceiptExport;
use App\Filament\Resources\CustomerBillingReceiptResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Jobs\SyncCustomerBillingReceiptToDrive;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerBillingReceipt;
use App\Models\DeliveryConferenceSheet;
use App\Models\Organization;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Models\TenantCloudStorageConnection;
use App\Services\CustomerBillingProjectContextService;
use App\Services\CustomerBillingReceiptService;
use App\Services\CustomerBillingSelectionService;
use App\Services\DeliveryParentRecoveryService;
use App\Services\FinancialDocumentIdentityService;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class CustomerBillingReceiptResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = CustomerBillingReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Projetos de Venda';

    protected static ?string $modelLabel = 'Faturamento de Cliente';

    protected static ?string $pluralModelLabel = 'Faturamentos de Clientes';

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
                        ->requiredWithout('organization_id')
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
                        ->requiredWithout('customer_id')
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
                ->description('Escolha por período, por comprovantes do associado ou manualmente. O comprovante serve apenas para localizar distribuições; seu valor nunca entra no cálculo.')
                ->schema([
                    Forms\Components\ToggleButtons::make('selection_mode')
                        ->label('Como deseja selecionar?')
                        ->options([
                            'period' => 'Período',
                            'receipts' => 'Comprovantes / QR Code',
                            'manual' => 'Manual',
                        ])
                        ->icons([
                            'period' => 'heroicon-o-calendar-days',
                            'receipts' => 'heroicon-o-qr-code',
                            'manual' => 'heroicon-o-list-bullet',
                        ])
                        ->default('period')->inline()->live()->dehydrated(false)->columnSpanFull(),

                    Forms\Components\DatePicker::make('from_date')
                        ->label('Distribuições de')->native(false)->live(),
                    Forms\Components\DatePicker::make('to_date')
                        ->label('Distribuições até')->native(false)->live()
                        ->afterOrEqual('from_date'),

                    Forms\Components\Textarea::make('associate_receipt_codes')
                        ->label('Código ou link do QR Code dos comprovantes')
                        ->helperText('Informe um código CP-…, número do comprovante ou cole o link lido no QR Code. Um por linha.')
                        ->rows(3)->dehydrated(false)->columnSpanFull()
                        ->visible(fn (Get $get): bool => $get('selection_mode') === 'receipts'),

                    Forms\Components\ViewField::make('associate_receipt_qr_scanner')
                        ->label(false)
                        ->view('filament.forms.customer-billing-qr-scanner')
                        ->dehydrated(false)->columnSpanFull()
                        ->visible(fn (Get $get): bool => $get('selection_mode') === 'receipts'),

                    // Botão rápido: selecionar todos os disponíveis
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('selectEligiblePeriod')
                            ->label('Carregar período')
                            ->icon('heroicon-o-calendar-days')->color('primary')->size('sm')
                            ->action(function (Get $get, Forms\Set $set, $record): void {
                                $ids = app(CustomerBillingSelectionService::class)->eligibleQuery(
                                    (int) session('tenant_id'),
                                    static::normalizeProjectIds($get('project_ids')),
                                    $get('customer_id') ? (int) $get('customer_id') : null,
                                    $get('organization_id') ? (int) $get('organization_id') : null,
                                    $get('from_date') ?: null,
                                    $get('to_date') ?: null,
                                    $record?->id,
                                )->pluck('id')->map(fn ($id): string => (string) $id)->all();
                                $set('delivery_ids', $ids);
                                Notification::make()->success()->title(count($ids).' distribuição(ões) compatível(is) selecionada(s).')->send();
                            })
                            ->visible(fn (Get $get): bool => $get('selection_mode') === 'period'
                                && static::normalizeProjectIds($get('project_ids')) !== []
                                && ((bool) $get('customer_id') || (bool) $get('organization_id'))),

                        Forms\Components\Actions\Action::make('selectByReceipts')
                            ->label('Ler comprovantes')
                            ->icon('heroicon-o-qr-code')->color('primary')->size('sm')
                            ->action(function (Get $get, Forms\Set $set, $record): void {
                                $result = app(CustomerBillingSelectionService::class)->selectFromAssociateReceiptCodes(
                                    (int) session('tenant_id'),
                                    [(string) $get('associate_receipt_codes')],
                                    static::normalizeProjectIds($get('project_ids')),
                                    $get('customer_id') ? (int) $get('customer_id') : null,
                                    $get('organization_id') ? (int) $get('organization_id') : null,
                                    $get('from_date') ?: null,
                                    $get('to_date') ?: null,
                                    $record?->id,
                                );
                                $set('delivery_ids', array_map('strval', $result['selected_ids']));
                                $reasonLabels = [
                                    'outro_projeto' => 'outro projeto',
                                    'outro_destinatario' => 'outro cliente/organização',
                                    'fora_do_periodo' => 'fora do período',
                                    'nao_e_distribuicao' => 'não é distribuição',
                                    'nao_aprovada' => 'não aprovada',
                                    'valor_invalido' => 'quantidade/preço inválido',
                                    'ja_faturada' => 'já faturada',
                                    'incompativel' => 'incompatível',
                                ];
                                $reasons = collect($result['reasons'])->map(
                                    fn (int $count, string $reason): string => $count.' '.($reasonLabels[$reason] ?? $reason)
                                )->implode('; ');
                                $excluded = $result['excluded_count'] > 0
                                    ? " {$result['excluded_count']} ignorada(s): {$reasons}."
                                    : '';
                                Notification::make()->success()
                                    ->title(count($result['selected_ids']).' distribuição(ões) selecionada(s).')
                                    ->body($result['receipt_count'].' comprovante(s) reconhecido(s).'.$excluded)->send();
                            })
                            ->visible(fn (Get $get): bool => $get('selection_mode') === 'receipts'
                                && filled($get('associate_receipt_codes'))),

                        Forms\Components\Actions\Action::make('selectAllFree')
                            ->label('Selecionar todos disponíveis')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->size('sm')
                            ->action(function (Get $get, Forms\Set $set, $record) {
                                $pids = static::normalizeProjectIds($get('project_ids'));
                                $cid = (int) $get('customer_id');
                                $oid = (int) $get('organization_id');
                                $all = array_keys(static::buildDistributionOptions($pids, $cid, $oid, $record?->id));
                                $set('delivery_ids', array_map('strval', $all));
                            })
                            ->visible(fn (Get $get) => static::normalizeProjectIds($get('project_ids')) !== []
                                && ((bool) $get('customer_id') || (bool) $get('organization_id'))
                                && $get('selection_mode') === 'manual'),

                        Forms\Components\Actions\Action::make('deselectAll')
                            ->label('Desmarcar todos')
                            ->icon('heroicon-o-x-circle')
                            ->color('gray')
                            ->size('sm')
                            ->action(fn (Forms\Set $set) => $set('delivery_ids', []))
                            ->visible(fn (Get $get) => ! empty(array_filter((array) $get('delivery_ids')))),
                    ])
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('eligible_summary')
                        ->label('Resumo da seleção')
                        ->content(function (Get $get, $record): HtmlString|string {
                            $ids = collect((array) $get('delivery_ids'))->map(fn ($id): int => (int) $id)->filter()->unique();
                            if ($ids->isEmpty()) {
                                return 'Carregue um período, leia comprovantes ou selecione itens manualmente.';
                            }
                            $tenantId = (int) session('tenant_id');
                            $rows = ProductionDelivery::withoutGlobalScopes()
                                ->where('tenant_id', $tenantId)->whereIn('id', $ids)
                                ->get(['id', 'tenant_id', 'sales_project_id', 'associate_id', 'customer_id', 'product_id', 'quantity', 'unit_price', 'gross_value', 'delivery_date']);
                            $projects = app(CustomerBillingProjectContextService::class)
                                ->projects($tenantId, static::normalizeProjectIds($get('project_ids')));
                            $snapshot = app(CustomerBillingReceiptService::class)->computeSnapshotForProjects($rows, $projects);
                            $originReceipts = ProductionDelivery::withoutGlobalScopes()
                                ->where('tenant_id', $tenantId)->whereIn('id', $ids)
                                ->whereNotNull('associate_receipt_id')->distinct()->count('associate_receipt_id');
                            $cards = [
                                'Distribuições' => $rows->count(),
                                'Produtores' => $rows->pluck('associate_id')->filter()->unique()->count(),
                                'Produtos' => $rows->pluck('product_id')->filter()->unique()->count(),
                                'Unidades recebedoras' => $rows->pluck('customer_id')->filter()->unique()->count(),
                                'Comprovantes de origem' => $originReceipts,
                                'Valor previsto' => 'R$ '.number_format((float) $snapshot['total_net'], 2, ',', '.'),
                            ];
                            $html = collect($cards)->map(fn ($value, string $label): string => '<div style="padding:.65rem .8rem;border:1px solid rgb(229 231 235);border-radius:.75rem">'
                                .'<div style="font-size:.75rem;color:rgb(107 114 128)">'.e($label).'</div>'
                                .'<strong>'.e((string) $value).'</strong></div>'
                            )->implode('');

                            return new HtmlString('<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(9rem,1fr));gap:.5rem">'.$html.'</div>');
                        })
                        ->visible(fn (Get $get): bool => ! empty(array_filter((array) $get('delivery_ids'))))
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
                        ->searchable()->live()->columnSpanFull()
                        ->helperText(function (Get $get, $record) {
                            $pids = static::normalizeProjectIds($get('project_ids'));
                            $cid = (int) $get('customer_id');
                            $oid = (int) $get('organization_id');
                            if ($pids === [] || (! $cid && ! $oid)) {
                                return 'Selecione os projetos e um comprador ou organização.';
                            }
                            $total = count(static::buildDistributionOptions($pids, $cid, $oid, $record?->id));

                            return "{$total} disponível(is) para seleção. Distribuições já incluídas em outro faturamento ficam ocultas.";
                        })
                        ->noSearchResultsMessage('Nenhuma distribuição encontrada.')
                        ->visible(fn (Get $get) => static::normalizeProjectIds($get('project_ids')) !== []
                            && ((bool) $get('customer_id') || (bool) $get('organization_id'))
                            && $get('selection_mode') === 'manual'),

                    Forms\Components\Placeholder::make('subtotal_preview')
                        ->label('Subtotal bruto selecionado (prévia)')
                        ->content(function (Get $get) {
                            $ids = array_filter((array) $get('delivery_ids'));
                            if (empty($ids)) {
                                return 'R$ 0,00';
                            }
                            $total = ProductionDelivery::withoutGlobalScopes()
                                ->where('tenant_id', session('tenant_id'))->whereIn('id', $ids)
                                ->get(['quantity', 'unit_price'])
                                ->reduce(fn (string $sum, $distribution): string => bcadd(
                                    $sum,
                                    bcmul((string) $distribution->quantity, (string) $distribution->unit_price, 8),
                                    8,
                                ), '0');

                            return 'R$ '.number_format($total, 2, ',', '.');
                        })
                        ->visible(fn (Get $get) => ! empty(array_filter((array) $get('delivery_ids')))),
                ]),

            \App\Filament\Forms\ReceiptReportAnnotationFields::section(),
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
        return app(CustomerBillingSelectionService::class)->lockedDistributionMap(
            (int) session('tenant_id'),
            $currentReceiptId,
        );
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

    /** Descriptions [id => label] para as distribuicoes ainda disponiveis. */
    public static function buildDistributionDescriptions(int|array $projectIds, int $customerId, int $orgId, ?int $currentReceiptId): array
    {
        $query = static::baseDistributionQuery($projectIds, $customerId, $orgId, $currentReceiptId);
        if (! $query) {
            return [];
        }

        return $query->get()
            ->mapWithKeys(function ($d) {
                $gross = number_format((float) $d->quantity * (float) $d->unit_price, 2, ',', '.');

                return [$d->id => 'Bruto: R$ '.$gross];
            })
            ->toArray();
    }

    /**
     * Query base: distribuições aprovadas do projeto para o comprador/organização.
     * Inclui as do próprio comprovante em edição (para reexibir sem filtrar).
     * Distribuicoes em outros faturamentos, inclusive rascunhos, nao aparecem.
     */
    private static function baseDistributionQuery(int|array $projectIds, int $customerId, int $orgId, ?int $currentReceiptId)
    {
        $projectIds = static::normalizeProjectIds($projectIds);
        if ($projectIds === [] || (! $customerId && ! $orgId)) {
            return null;
        }

        $tenantId = (int) session('tenant_id');
        $query = app(CustomerBillingSelectionService::class)->eligibleQuery(
            $tenantId,
            $projectIds,
            $customerId ?: null,
            $orgId ?: null,
            null,
            null,
            $currentReceiptId,
        )
            ->with(['salesProject:id,title', 'product', 'customer'])
            ->orderBy('sales_project_id')->orderBy('delivery_date');

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
                    ->label('Nº Faturamento')->weight('bold')
                    ->searchable(['receipt_year', 'receipt_number'])
                    ->sortable(['receipt_year', 'receipt_number'])
                    ->description(fn (CustomerBillingReceipt $record): string => 'Geral '.$record->tenant_formatted_number.' | Projeto '.$record->project_formatted_number),

                Tables\Columns\TextColumn::make('project_summary')
                    ->label('Projeto(s)')->limit(45)->default('— Avulso —'),

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
                                    'delivery_date' => 'Data (agrupar produtos do mesmo dia)',
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
                                    ? "Há {$integrity['recoverable']} entrega(s)-pai removida(s) que podem ser restauradas sem reativar distribuições excluídas. Abra o comprovante e use Corrigir integridade antes de imprimir."
                                    : 'Há distribuições com vínculos inválidos. Abra o comprovante para revisar a integridade antes de imprimir.')
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
                        $distributions = ProductionDelivery::withoutGlobalScopes()
                            ->where('tenant_id', $record->tenant_id)
                            ->whereNull('deleted_at')
                            ->whereIn('sales_project_id', $projectIds)
                            ->whereNotNull('parent_delivery_id')
                            ->whereIn('id', $record->delivery_ids)
                            ->with(['product', 'customer.priceTable'])->orderBy('delivery_date')->get();

                        if ($distributions->count() !== count(array_unique(array_map('intval', $record->delivery_ids)))) {
                            Notification::make()->danger()
                                ->title('Comprovante inconsistente')
                                ->body('Uma ou mais distribuições foram removidas ou deixaram de pertencer aos projetos deste faturamento. Revise a integridade do documento.')
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

                // ── Editar (somente DRAFT) ────────────────────────────────────
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (CustomerBillingReceipt $r) => $r->isEditable()),

                // ── Excluir (somente DRAFT) ───────────────────────────────────
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (CustomerBillingReceipt $r) => $r->isEditable())
                    ->using(function (CustomerBillingReceipt $r): bool {
                        app(CustomerBillingReceiptService::class)->discardDraftReceipt($r);

                        return true;
                    }),

                // ── Emitir faturamento (DRAFT → PENDING_PAYMENT) ──────────────
                Tables\Actions\Action::make('freeze')
                    ->label('Emitir faturamento')
                    ->icon('heroicon-o-paper-airplane')->color('warning')
                    ->visible(fn (CustomerBillingReceipt $r) => $r->status === CustomerReceiptStatus::DRAFT || $r->status === null)
                    ->requiresConfirmation()
                    ->modalHeading(fn (CustomerBillingReceipt $r) => 'Emitir faturamento '.$r->formatted_number)
                    ->modalDescription(function (CustomerBillingReceipt $r) {
                        $count = is_array($r->delivery_ids) ? count($r->delivery_ids) : 0;

                        return "Congela {$count} distribuição(ões) e calcula os valores finais. Após emitir, não é possível editar.";
                    })
                    ->action(function (CustomerBillingReceipt $record): void {
                        abort_unless(auth()->user()?->can('update', $record), 403);
                        if (empty($record->delivery_ids)) {
                            Notification::make()->danger()->title('Sem distribuições')
                                ->body('Adicione ao menos uma distribuição antes de emitir.')->send();

                            return;
                        }
                        try {
                            $distributions = ProductionDelivery::withoutGlobalScopes()
                                ->where('tenant_id', $record->tenant_id)
                                ->whereIn('id', $record->delivery_ids)
                                ->get();
                            app(CustomerBillingReceiptService::class)
                                ->freezeReceipt($record, $distributions, $record->project);
                            Notification::make()->success()->title('Faturamento emitido')
                                ->body('Valor líquido: R$ '.number_format((float) $record->fresh()->total_net, 2, ',', '.'))->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Erro ao emitir faturamento')
                                ->body($e->getMessage())->send();
                        }
                    }),

                // ── Registrar Recebimento (PENDING_PAYMENT / PARTIALLY_PAID) ─────
                Tables\Actions\Action::make('openVerifiedReceipt')
                    ->label('Abrir comprovante e QR')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->url(function (CustomerBillingReceipt $record): string {
                        $identity = app(FinancialDocumentIdentityService::class)
                            ->ensure($record, auth()->user());

                        return $identity ? route('financial-documents.show', $identity->public_id) : '#';
                    })
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('refreshDocument')
                    ->label('Atualizar comprovante')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Atualizar o PDF no Google Drive?')
                    ->modalDescription('Um novo PDF será gerado com as distribuições e valores atualmente salvos. O arquivo existente será atualizado no Google Drive, preservando o mesmo QR Code.')
                    ->action(function (CustomerBillingReceipt $record): void {
                        $connected = TenantCloudStorageConnection::query()
                            ->where('tenant_id', $record->tenant_id)
                            ->where('provider', 'google_drive')
                            ->where('status', 'active')
                            ->exists();
                        if (! $connected) {
                            Notification::make()->danger()
                                ->title('Google Drive não conectado')
                                ->body('Reconecte a conta da organização antes de atualizar o comprovante.')
                                ->persistent()
                                ->send();

                            return;
                        }

                        if (empty($record->delivery_ids) || (float) ($record->total_net ?? 0) <= 0) {
                            Notification::make()->warning()
                                ->title('Comprovante sem distribuições')
                                ->body('Salve ao menos uma distribuição válida antes de atualizar o PDF.')
                                ->send();

                            return;
                        }

                        $identity = app(FinancialDocumentIdentityService::class)
                            ->ensure($record, auth()->user());

                        SyncCustomerBillingReceiptToDrive::dispatch((int) $record->id);

                        Notification::make()
                            ->success()
                            ->title('Atualização enviada')
                            ->body('O PDF está sendo regenerado com as entregas atuais e substituirá a versão anterior no Drive. O QR da versão '.((int) ($identity?->revision ?: 1)).' será mantido.')
                            ->send();
                    }),

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
                                    ->reject(fn ($m) => $m === PaymentMethod::CHEQUE)
                                    ->mapWithKeys(fn ($m) => [$m->value => $m->getLabel()])->toArray())
                                ->helperText('Para cheque, use “Abrir comprovante e QR”; a emissão não liquida até a entrega.')
                                ->required(),
                            Forms\Components\Select::make('bank_account_id')->label('Conta Bancária')
                                ->options(fn () => BankAccount::where('tenant_id', session('tenant_id'))
                                    ->where('status', true)->pluck('name', 'id')->toArray())
                                ->placeholder('Selecione')
                                ->required()
                                ->helperText('Registra a entrada correspondente no caixa da organização.'),
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

    public static function buildCustomerReceiptData(
        Collection $distributions,
        CustomerBillingReceipt $receipt,
        ?Tenant $tenant,
        ?SalesProject $project,
        ?Customer $customer,
        Collection $projects,
        array $requestedColumns = [],
    ): array {
        $presentation = static::billingPresentation($receipt, $distributions, $projects);
        $effectiveFeeSnapshot = $presentation['fee_snapshot'];
        $feeColumnService = app(ReceiptFeeColumnService::class);
        $feeColumns = $project
            ? $feeColumnService->definitions($project, 'customer', $effectiveFeeSnapshot)
            : [];
        $visibleColumns = $feeColumnService->sanitize(
            $requestedColumns,
            $feeColumns,
            ['delivery_date', 'unit_price', 'gross', 'net'],
        );
        $groupByDate = in_array('delivery_date', $visibleColumns, true);
        $projectMap = $projects->keyBy('id');
        $projectSnapshots = collect(data_get($effectiveFeeSnapshot, 'project_snapshots', []));
        $frozenLines = $groupByDate
            ? static::groupPresentationLinesByDate($presentation['lines'], $distributions)
            : $presentation['lines'];
        $productRows = $frozenLines->isNotEmpty()
            ? $frozenLines->map(fn (array $line): array => [
                'distribution_ids' => (array) ($line['distribution_ids'] ?? []),
                'project_id' => (int) ($line['project_id'] ?? 0),
                'project' => (string) ($line['project'] ?? '—'),
                'product_id' => (int) ($line['product_id'] ?? 0),
                'product' => (string) ($line['product'] ?? '—'),
                'delivery_date' => $line['delivery_date'] ?? null,
                'unit' => (string) ($line['unit'] ?? 'un'),
                'quantity' => (string) ($line['quantity'] ?? '0'),
                'unit_price' => (string) ($line['unit_price'] ?? '0'),
                'gross' => (string) ($line['document_gross'] ?? '0.00'),
                'fee_values' => (array) ($line['fee_values'] ?? []),
                'net' => (string) ($line['document_amount'] ?? '0.00'),
            ])->values()->all()
            : $distributions
                ->groupBy(fn ($d) => implode('|', array_filter([
                    $d->sales_project_id,
                    $d->product_id,
                    $d->unit_price,
                    $groupByDate ? static::deliveryDateKey($d) : null,
                ], fn ($value): bool => $value !== null)))
                ->map(function ($group) use ($feeColumnService, $projectMap, $projectSnapshots, $effectiveFeeSnapshot, $projects) {
                    $first = $group->first();
                    $rowProject = $projectMap->get((int) $first->sales_project_id);
                    $projectSnapshot = $projectSnapshots->get((string) $first->sales_project_id)
                        ?? ($projects->count() === 1 ? $effectiveFeeSnapshot : null);
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
                        'distribution_ids' => $group->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                        'project_id' => (int) $first->sales_project_id,
                        'project' => $rowProject?->title ?? 'Projeto #'.$first->sales_project_id,
                        'product_id' => (int) $first->product_id,
                        'product' => $first->product?->name ?? '—',
                        'delivery_date' => $group->pluck('delivery_date')->filter()->min()?->format('Y-m-d'),
                        'unit' => $first->product?->unit ?? 'kg',
                        'quantity' => $qty,
                        'unit_price' => (float) $first->unit_price,
                        'gross' => $gross,
                        'fee_values' => $feeValues,
                        'net' => $net,
                    ];
                })
                ->values()->toArray();

        $totalGross = (float) $presentation['total_gross'];
        $totalFees = (float) $presentation['total_fees'];
        $totalNet = (float) $presentation['total_net'];

        $feeBreakdown = [];
        $snapshot = $effectiveFeeSnapshot;
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
            $effectiveFeeSnapshot,
            $projects,
            $feeColumnService,
            $periodsByProject,
        ): array {
            $rows = collect($productRows)
                ->where('project_id', (int) $groupProject->id)
                ->values();
            $projectSnapshot = $projectSnapshots->get((string) $groupProject->id)
                ?? ($projects->count() === 1 ? $effectiveFeeSnapshot : null);
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

        $productTotals = static::productTotals($productRows);

        return compact(
            'tenant', 'project', 'customer', 'receipt',
            'productRows', 'projectGroups', 'totalGross', 'totalFees', 'totalNet', 'feeBreakdown',
            'periodLabel', 'feeColumns', 'visibleColumns', 'projects', 'projectPeriods', 'isMultiProject',
            'productTotals'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Dados para PDF — relatório de organização
    // ─────────────────────────────────────────────────────────────────────────

    public static function buildOrganizationReportData(
        Collection $distributions,
        CustomerBillingReceipt $receipt,
        ?Tenant $tenant,
        ?SalesProject $project,
        ?Organization $organization,
        Collection $projects,
        array $requestedColumns = [],
    ): array {
        $presentation = static::billingPresentation($receipt, $distributions, $projects);
        $effectiveFeeSnapshot = $presentation['fee_snapshot'];
        $feeColumnService = app(ReceiptFeeColumnService::class);
        $feeColumns = $project
            ? $feeColumnService->definitions($project, 'customer', $effectiveFeeSnapshot)
            : [];
        $visibleColumns = $feeColumnService->sanitize(
            $requestedColumns,
            $feeColumns,
            ['delivery_date', 'unit_price', 'gross', 'net'],
        );
        $groupByDate = in_array('delivery_date', $visibleColumns, true);
        // Todos os compradores distintos (para o rodapé)
        $customers = $distributions->pluck('customer')->filter()->unique('id')->sortBy('name')->values();

        $projectSnapshots = collect(data_get($effectiveFeeSnapshot, 'project_snapshots', []));
        $frozenLines = $groupByDate
            ? static::groupPresentationLinesByDate($presentation['lines'], $distributions)
            : $presentation['lines'];
        $priceGroups = $projects->map(function (SalesProject $rowProject) use ($distributions, $frozenLines, $feeColumnService, $projectSnapshots, $effectiveFeeSnapshot, $projects, $groupByDate) {
            $groupDists = $distributions->where('sales_project_id', $rowProject->id)->values();
            $projectSnapshot = $projectSnapshots->get((string) $rowProject->id)
                ?? ($projects->count() === 1 ? $effectiveFeeSnapshot : null);
            $rowFeeColumns = $feeColumnService->definitions($rowProject, 'customer', $projectSnapshot);
            $groupCustomers = $groupDists->pluck('customer')->filter()->unique('id')->sortBy('name')->values();
            $lines = $frozenLines->where('project_id', (int) $rowProject->id)->values();
            if ($lines->isEmpty() && $groupDists->isNotEmpty()) {
                // Compatibilidade com faturamentos emitidos antes de
                // document_lines existir. Os totais gerais continuam vindo do
                // comprovante congelado; esta recomposição serve só à tabela.
                $lines = $groupDists
                    ->groupBy(fn ($distribution): string => implode('|', [
                        (int) $distribution->product_id,
                        (string) $distribution->unit_price,
                        $groupByDate ? static::deliveryDateKey($distribution) : '',
                    ]))
                    ->map(function (Collection $rows) use ($rowProject, $feeColumnService, $rowFeeColumns, $groupByDate): array {
                        $first = $rows->first();
                        $quantity = $rows->reduce(
                            fn (string $sum, $row): string => bcadd($sum, (string) $row->quantity, 8),
                            '0',
                        );
                        $gross = $rows->reduce(
                            fn (string $sum, $row): string => bcadd(
                                $sum,
                                bcmul((string) $row->quantity, (string) $row->unit_price, 8),
                                8,
                            ),
                            '0',
                        );
                        $feeValues = $feeColumnService->totals($rows, $rowFeeColumns);
                        $net = (float) $gross;
                        foreach ($rowFeeColumns as $fee) {
                            $amount = (float) ($feeValues[$fee['key']] ?? 0);
                            $net += $fee['nature'] === 'accrual' ? $amount : -$amount;
                        }

                        return [
                            'distribution_ids' => $rows->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                            'project_id' => (int) $rowProject->id,
                            'project' => (string) $rowProject->title,
                            'product_id' => (int) $first->product_id,
                            'product' => (string) ($first->product?->name ?? 'Produto #'.$first->product_id),
                            'delivery_date' => $groupByDate ? static::deliveryDateKey($first) : null,
                            'unit' => (string) ($first->product?->unit ?? 'un'),
                            'quantity' => rtrim(rtrim($quantity, '0'), '.') ?: '0',
                            'unit_price' => (string) $first->unit_price,
                            'document_gross' => number_format((float) $gross, 2, '.', ''),
                            'document_amount' => number_format($net, 2, '.', ''),
                            'fee_values' => $feeValues,
                        ];
                    })
                    ->values();
            }
            $table = $lines->map(function (array $line) use ($groupDists): array {
                $matching = $groupDists->filter(fn ($distribution): bool => (int) $distribution->product_id === (int) ($line['product_id'] ?? 0)
                    && bccomp((string) $distribution->unit_price, (string) ($line['unit_price'] ?? 0), 8) === 0
                    && (empty($line['delivery_date']) || static::deliveryDateKey($distribution) === $line['delivery_date'])
                );
                $byCustomer = $matching->groupBy('customer_id')->map(
                    fn (Collection $rows): string => $rows->reduce(
                        fn (string $sum, $row): string => bcadd($sum, (string) $row->quantity, 8),
                        '0',
                    )
                )->all();

                return [
                    'distribution_ids' => (array) ($line['distribution_ids'] ?? $matching->pluck('id')->all()),
                    'product_id' => (int) ($line['product_id'] ?? 0),
                    'product' => (string) ($line['product'] ?? '—'),
                    'delivery_date' => $line['delivery_date'] ?? null,
                    'unit' => (string) ($line['unit'] ?? 'un'),
                    'unit_price' => (string) ($line['unit_price'] ?? '0'),
                    'by_customer' => $byCustomer,
                    'total_qty' => (string) ($line['quantity'] ?? '0'),
                    'total_gross' => (string) ($line['document_gross'] ?? '0.00'),
                    'fee_values' => (array) ($line['fee_values'] ?? []),
                    'net' => (string) ($line['document_amount'] ?? '0.00'),
                ];
            })->all();

            $feeTotals = collect($rowFeeColumns)->mapWithKeys(fn (array $fee): array => [
                $fee['key'] => $lines->reduce(
                    fn (string $sum, array $line): string => bcadd($sum, (string) data_get($line, 'fee_values.'.$fee['key'], '0'), 2),
                    '0.00',
                ),
            ])->all();
            $subtotalGross = $lines->reduce(fn (string $sum, array $line): string => bcadd($sum, (string) ($line['document_gross'] ?? 0), 2), '0.00');
            $subtotalNet = $lines->reduce(fn (string $sum, array $line): string => bcadd($sum, (string) ($line['document_amount'] ?? 0), 2), '0.00');

            return [
                'project_id' => (int) $rowProject->id,
                'project_name' => $rowProject->title,
                'price_table_name' => 'Valores consolidados',
                'customers' => $groupCustomers,
                'table' => $table,
                'subtotal_gross' => $subtotalGross,
                'subtotal_net' => $subtotalNet,
                'fee_totals' => $feeTotals,
            ];
        })->filter(fn (array $group): bool => $group['table'] !== [])->values()->all();

        $totalGross = (float) $presentation['total_gross'];
        $totalFees = (float) $presentation['total_fees'];
        $totalNet = (float) $presentation['total_net'];
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
        $productTotals = static::productTotals(
            collect($priceGroups)->flatMap(fn (array $group): array => $group['table'])->all()
        );

        return compact(
            'tenant', 'project', 'organization', 'receipt',
            'customers', 'priceGroups', 'multiplePriceTables',
            'totalGross', 'totalFees', 'totalNet', 'periodLabel',
            'feeColumns', 'visibleColumns', 'projects', 'projectPeriods', 'isMultiProject',
            'productTotals'
        );
    }

    /**
     * Divide uma linha documental congelada somente para apresentacao por dia.
     * Os centavos da linha original sao rateados e o ultimo grupo recebe o
     * residuo, portanto o agrupamento nunca altera os totais emitidos.
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     * @param  Collection<int, ProductionDelivery>  $distributions
     * @return Collection<int, array<string, mixed>>
     */
    private static function groupPresentationLinesByDate(
        Collection $lines,
        Collection $distributions,
    ): Collection {
        $datedLines = collect();

        foreach ($lines as $line) {
            $lineDistributionIds = collect($line['distribution_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->all();
            $matching = $distributions->filter(function ($distribution) use ($line, $lineDistributionIds): bool {
                if ($lineDistributionIds !== []) {
                    return in_array((int) $distribution->id, $lineDistributionIds, true);
                }

                return (int) $distribution->sales_project_id === (int) ($line['project_id'] ?? 0)
                    && (int) $distribution->product_id === (int) ($line['product_id'] ?? 0)
                    && bccomp((string) $distribution->unit_price, (string) ($line['unit_price'] ?? 0), 8) === 0;
            });

            if ($matching->isEmpty()) {
                $datedLines->push($line);

                continue;
            }

            $dateGroups = $matching
                ->groupBy(fn ($distribution): string => static::deliveryDateKey($distribution))
                ->sortKeys();
            $weights = $dateGroups->map(function (Collection $rows): float {
                $gross = $rows->sum(fn ($row): float => (float) $row->quantity * (float) $row->unit_price);

                return $gross > 0 ? $gross : $rows->sum(fn ($row): float => abs((float) $row->quantity));
            })->all();
            $grossAllocations = static::allocateMoneyByWeight((string) ($line['document_gross'] ?? 0), $weights);
            $feeAllocations = static::allocateMoneyByWeight((string) ($line['document_fees'] ?? 0), $weights);
            $netAllocations = static::allocateMoneyByWeight((string) ($line['document_amount'] ?? 0), $weights);
            $feeValueAllocations = collect((array) ($line['fee_values'] ?? []))
                ->map(fn ($amount): array => static::allocateMoneyByWeight((string) $amount, $weights));

            foreach ($dateGroups as $date => $rows) {
                $quantity = $rows->reduce(
                    fn (string $sum, $row): string => bcadd($sum, (string) $row->quantity, 8),
                    '0',
                );
                $rawAmount = $rows->reduce(
                    fn (string $sum, $row): string => bcadd(
                        $sum,
                        bcmul((string) $row->quantity, (string) $row->unit_price, 8),
                        8,
                    ),
                    '0',
                );
                $datedLine = $line;
                $datedLine['delivery_date'] = $date;
                $datedLine['quantity'] = static::trimDecimal($quantity);
                $datedLine['raw_amount'] = $rawAmount;
                $datedLine['document_gross'] = $grossAllocations[$date];
                $datedLine['document_fees'] = $feeAllocations[$date];
                $datedLine['document_amount'] = $netAllocations[$date];
                $datedLine['distribution_ids'] = $rows->pluck('id')->map(fn ($id): int => (int) $id)->all();
                $datedLine['fee_values'] = $feeValueAllocations
                    ->mapWithKeys(fn (array $allocations, string $key): array => [$key => $allocations[$date]])
                    ->all();
                $datedLines->push($datedLine);
            }
        }

        return $datedLines
            ->sortBy(fn (array $line): string => implode('|', [
                str_pad((string) ($line['project_id'] ?? 0), 12, '0', STR_PAD_LEFT),
                (string) ($line['delivery_date'] ?? ''),
                mb_strtolower((string) ($line['product'] ?? '')),
                (string) ($line['unit_price'] ?? 0),
            ]))
            ->values();
    }

    /** @param array<string, float> $weights @return array<string, string> */
    private static function allocateMoneyByWeight(string $amount, array $weights): array
    {
        if ($weights === []) {
            return [];
        }

        $totalCents = (int) round((float) $amount * 100);
        $sign = $totalCents < 0 ? -1 : 1;
        $remaining = abs($totalCents);
        $weightTotal = array_sum($weights);
        if ($weightTotal <= 0) {
            $weights = array_fill_keys(array_keys($weights), 1.0);
            $weightTotal = count($weights);
        }

        $result = [];
        $lastKey = array_key_last($weights);
        foreach ($weights as $key => $weight) {
            $cents = $key === $lastKey
                ? $remaining
                : min($remaining, (int) round(abs($totalCents) * ($weight / $weightTotal)));
            $remaining -= $cents;
            $result[$key] = number_format(($cents * $sign) / 100, 2, '.', '');
        }

        return $result;
    }

    private static function deliveryDateKey(mixed $distribution): string
    {
        $date = $distribution->delivery_date ?? null;

        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        $date = trim((string) $date);

        return $date !== '' ? substr($date, 0, 10) : 'sem-data';
    }

    private static function trimDecimal(string $value): string
    {
        $value = rtrim(rtrim($value, '0'), '.');

        return $value === '' || $value === '-0' ? '0' : $value;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{product:string,unit:string,quantity:string,gross:string,adjustments:string,net:string}>
     */
    private static function productTotals(array $rows): array
    {
        return collect($rows)
            ->groupBy(fn (array $row): string => ($row['product_id'] ?? 0) > 0
                ? 'id:'.(int) $row['product_id'].'|'.mb_strtolower((string) ($row['unit'] ?? 'un'))
                : 'name:'.mb_strtolower((string) ($row['product'] ?? '')).'|'.mb_strtolower((string) ($row['unit'] ?? 'un')))
            ->map(function (Collection $group): array {
                $first = $group->first();
                $quantity = $group->reduce(
                    fn (string $sum, array $row): string => bcadd(
                        $sum,
                        (string) ($row['quantity'] ?? $row['total_qty'] ?? 0),
                        8,
                    ),
                    '0',
                );
                $gross = $group->reduce(
                    fn (string $sum, array $row): string => bcadd(
                        $sum,
                        (string) ($row['gross'] ?? $row['total_gross'] ?? 0),
                        2,
                    ),
                    '0.00',
                );
                $net = $group->reduce(
                    fn (string $sum, array $row): string => bcadd($sum, (string) ($row['net'] ?? 0), 2),
                    '0.00',
                );

                return [
                    'product' => (string) ($first['product'] ?? '—'),
                    'unit' => (string) ($first['unit'] ?? 'un'),
                    'quantity' => static::trimDecimal($quantity),
                    'gross' => $gross,
                    'adjustments' => bcsub($net, $gross, 2),
                    'net' => $net,
                ];
            })
            ->sortBy(fn (array $row): string => mb_strtolower($row['product']))
            ->values()
            ->all();
    }

    /**
     * Resolve a fonte documental sem persistir recalculos.
     *
     * Rascunhos sempre refletem as distribuicoes selecionadas atualmente. Uma
     * cobranca emitida continua usando seu snapshot imutavel; o recalculo so e
     * usado como compatibilidade de apresentacao quando um documento legado nao
     * possui linhas congeladas.
     *
     * @return array{
     *     fee_snapshot: array<string, mixed>,
     *     lines: Collection<int, array<string, mixed>>,
     *     total_gross: string|float,
     *     total_fees: string|float,
     *     total_net: string|float
     * }
     */
    private static function billingPresentation(
        CustomerBillingReceipt $receipt,
        Collection $distributions,
        Collection $projects,
    ): array {
        $storedSnapshot = is_array($receipt->fee_snapshot) ? $receipt->fee_snapshot : [];
        $storedLines = collect($receipt->documentLines());
        $calculated = null;

        if ($receipt->isEditable()
            && $distributions->isNotEmpty()
            && $projects->isNotEmpty()
        ) {
            $calculated = app(CustomerBillingReceiptService::class)
                ->computeSnapshotForProjects($distributions, $projects);
        }

        $effectiveSnapshot = is_array($calculated['fee_snapshot'] ?? null)
            ? $calculated['fee_snapshot']
            : $storedSnapshot;
        $lines = collect(data_get($effectiveSnapshot, 'document_lines', $storedLines->all()));

        // Snapshots antigos de projeto unico nao registravam project_id. Sem
        // esta normalizacao as linhas existem, mas o agrupamento as descarta.
        if ($projects->count() === 1) {
            $projectId = (int) $projects->first()->id;
            $projectTitle = (string) $projects->first()->title;
            $lines = $lines->map(function (array $line) use ($projectId, $projectTitle): array {
                if (empty($line['project_id'])) {
                    $line['project_id'] = $projectId;
                }
                if (blank($line['project'] ?? null)) {
                    $line['project'] = $projectTitle;
                }

                return $line;
            });
        }

        $lineGross = $lines->reduce(
            fn (string $sum, array $line): string => bcadd($sum, (string) ($line['document_gross'] ?? 0), 2),
            '0.00',
        );
        $lineFees = $lines->reduce(
            fn (string $sum, array $line): string => bcadd($sum, (string) ($line['document_fees'] ?? 0), 2),
            '0.00',
        );
        $lineNet = $lines->reduce(
            fn (string $sum, array $line): string => bcadd($sum, (string) ($line['document_amount'] ?? 0), 2),
            '0.00',
        );
        $useCalculatedTotals = $receipt->isEditable() && $calculated !== null;

        return [
            'fee_snapshot' => $effectiveSnapshot,
            'lines' => $lines->values(),
            'total_gross' => $useCalculatedTotals
                ? $calculated['total_gross']
                : ($receipt->total_gross ?? $calculated['total_gross'] ?? $lineGross),
            'total_fees' => $useCalculatedTotals
                ? $calculated['total_fees']
                : ($receipt->total_fees ?? $calculated['total_fees'] ?? $lineFees),
            'total_net' => $useCalculatedTotals
                ? $calculated['total_net']
                : ($receipt->total_net ?? $calculated['total_net'] ?? $lineNet),
        ];
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
            $rows = ProductionDelivery::withoutGlobalScopes()
                ->where('tenant_id', $receipt->tenant_id)
                ->whereIn('id', $receipt->delivery_ids)
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
            'view' => Pages\ViewCustomerBillingReceipt::route('/{record}'),
            'edit' => Pages\EditCustomerBillingReceipt::route('/{record}/edit'),
        ];
    }
}
