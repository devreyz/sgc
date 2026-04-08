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
                            ->label('Projeto')
                            ->options(fn () => SalesProject::where('tenant_id', session('tenant_id'))
                                ->pluck('title', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('associate_id')
                            ->label('Produtor / Associado')
                            ->options(fn () => Associate::where('tenant_id', session('tenant_id'))
                                ->with('user')
                                ->get()
                                ->pluck('user.name', 'id'))
                            ->searchable()
                            ->required(),

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
                            ->required()
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
                    ->limit(40),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Data Emissão')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('receipt_year')
                    ->label('Ano')
                    ->sortable(),

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
            ])
            ->actions([
                Tables\Actions\Action::make('printReceipt')
                    ->label('Imprimir PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function (AssociateReceipt $record): mixed {
                        $tenantId  = $record->tenant_id;
                        $tenant    = Tenant::find($tenantId);
                        $associate = $record->associate()->with('user')->first();
                        $project   = $record->project;

                        $deliveries = ProductionDelivery::where('tenant_id', $tenantId)
                            ->where('sales_project_id', $record->sales_project_id)
                            ->where('associate_id', $record->associate_id)
                            ->where('status', DeliveryStatus::APPROVED)
                            ->with('product')
                            ->orderBy('delivery_date')
                            ->get();

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

                        $svc = app(\App\Services\TemplatedPdfService::class);
                        $pdf = $svc->generateSystemPdf('pdf.project-associate-receipt', [
                            'tenant'          => $tenant,
                            'project'         => $project,
                            'associate'       => $associate,
                            'receipt'         => $record,
                            'summary'         => $summary,
                            'productsSummary' => $productsSummary,
                        ], ['paper' => 'a4', 'orientation' => 'portrait', 'title' => 'Comprovante de Entrega']);

                        $safeName     = \Illuminate\Support\Str::slug($associate?->user?->name ?? 'associado');
                        $receiptLabel = str_replace('/', '-', $record->formatted_number);

                        return Response::streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, "comprovante-{$receiptLabel}-{$safeName}.pdf", ['Content-Type' => 'application/pdf']);
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
            'index' => Pages\ListAssociateReceipts::route('/'),
            'create' => Pages\CreateAssociateReceipt::route('/create'),
            'edit' => Pages\EditAssociateReceipt::route('/{record}/edit'),
        ];
    }
}
