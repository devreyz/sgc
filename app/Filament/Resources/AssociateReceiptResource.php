<?php

namespace App\Filament\Resources;

use App\Enums\DeliveryStatus;
use App\Filament\Resources\AssociateReceiptResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\Associate;
use App\Models\AssociateReceipt;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Response;

class AssociateReceiptResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = AssociateReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Projetos de Venda';

    protected static ?string $modelLabel = 'Comprovante de Entrega';

    protected static ?string $pluralModelLabel = 'Comprovantes de Entrega';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Comprovante')
                    ->schema([
                        Forms\Components\Select::make('sales_project_id')
                            ->label('Projeto (deixe vazio para entregas avulsas)')
                            ->options(fn () => SalesProject::where('tenant_id', session('tenant_id'))
                                ->pluck('title', 'id'))
                            ->searchable()
                            ->nullable()
                            ->placeholder('— Entregas avulsas —')
                            ->helperText('Deixe em branco para agrupar entregas sem projeto vinculado.'),

                        Forms\Components\Select::make('associate_id')
                            ->label('Produtor / Associado')
                            ->options(fn () => Associate::where('tenant_id', session('tenant_id'))
                                ->with('user')
                                ->get()
                                ->pluck('user.name', 'id'))
                            ->searchable()
                            ->required(),

                        // Período de entregas avulsas (visível só quando sem projeto)
                        Forms\Components\DatePicker::make('from_date')
                            ->label('Data Início (período avulso)')
                            ->visible(fn (Get $get) => empty($get('sales_project_id')))
                            ->helperText('Filtra entregas avulsas a partir desta data.'),

                        Forms\Components\DatePicker::make('to_date')
                            ->label('Data Fim (período avulso)')
                            ->visible(fn (Get $get) => empty($get('sales_project_id')))
                            ->afterOrEqual('from_date')
                            ->helperText('Filtra entregas avulsas até esta data.'),

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
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('formatted_number')
                    ->label('Nº Recibo')
                    ->sortable(['receipt_year', 'receipt_number'])
                    ->searchable(query: fn ($query, $search) => $query->whereRaw("CONCAT(LPAD(receipt_number,4,'0'), '/', receipt_year) LIKE ?", ["%{$search}%"])),

                Tables\Columns\TextColumn::make('associate.user.name')
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
                        ? 'Assinado em ' . $r->acknowledged_at->format('d/m/Y H:i')
                        : 'Aguardando assinatura'),

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

                Tables\Filters\Filter::make('standalone')
                    ->label('Somente avulsos')
                    ->query(fn ($query) => $query->whereNull('sales_project_id'))
                    ->toggle(),

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
                        $tenantId  = $record->tenant_id;
                        $tenant    = Tenant::find($tenantId);
                        $associate = $record->associate()->with('user')->first();
                        $project   = $record->project;

                        // ── Buscar entregas ──────────────────────────────────
                        $query = ProductionDelivery::where('tenant_id', $tenantId)
                            ->where('associate_id', $record->associate_id)
                            ->where('status', DeliveryStatus::APPROVED)
                            ->with('product')
                            ->orderBy('delivery_date');

                        if ($record->sales_project_id) {
                            $query->where('sales_project_id', $record->sales_project_id);
                        } else {
                            $query->whereNull('sales_project_id');
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

                        $summary = [
                            'deliveries_count' => $deliveries->count(),
                            'total_quantity'   => $deliveries->sum('quantity'),
                            'gross_value'      => $deliveries->sum('gross_value'),
                            'admin_fee'        => $deliveries->sum('admin_fee_amount'),
                            'net_value'        => $deliveries->sum('net_value'),
                        ];

                        $productsSummary = $deliveries->groupBy('product_id')->map(function ($items) {
                            $product    = $items->first()->product;
                            $totalQty   = $items->sum('quantity');
                            $totalGross = $items->sum('gross_value');
                            return [
                                'product_name' => $product?->name ?? '—',
                                'unit'         => $product?->unit ?? 'un',
                                'count'        => $items->count(),
                                'quantity'     => $totalQty,
                                'unit_price'   => $totalQty > 0 ? $totalGross / $totalQty : ($items->first()->unit_price ?? 0),
                                'gross'        => $totalGross,
                                'admin_fee'    => $items->sum('admin_fee_amount'),
                                'net'          => $items->sum('net_value'),
                            ];
                        })->values()->all();

                        // ── Marcar como segunda via se já foi assinado ───────
                        $isSecondCopy = $record->acknowledged_at !== null;

                        $svc = app(\App\Services\TemplatedPdfService::class);
                        $pdf = $svc->generateSystemPdf('pdf.project-associate-receipt', [
                            'tenant'          => $tenant,
                            'project'         => $project,
                            'associate'       => $associate,
                            'receipt'         => $record,
                            'summary'         => $summary,
                            'productsSummary' => $productsSummary,
                            'isSecondCopy'    => $isSecondCopy,
                        ], ['paper' => 'a4', 'orientation' => 'portrait', 'title' => 'Comprovante de Entrega']);

                        $safeName     = \Illuminate\Support\Str::slug($associate?->user?->name ?? 'associado');
                        $receiptLabel = str_replace('/', '-', $record->formatted_number);
                        $suffix       = $isSecondCopy ? '-2via' : '';

                        return Response::streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, "comprovante-{$receiptLabel}-{$safeName}{$suffix}.pdf", ['Content-Type' => 'application/pdf']);
                    }),

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

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('receipt_year', 'desc')
            ->defaultSort('receipt_number', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAssociateReceipts::route('/'),
            'create' => Pages\CreateAssociateReceipt::route('/create'),
            'edit'   => Pages\EditAssociateReceipt::route('/{record}/edit'),
        ];
    }
}
