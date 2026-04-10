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
                                    ->with('product')
                                    ->orderBy('delivery_date');

                                if ($projectId) {
                                    $query->where('sales_project_id', $projectId);
                                } else {
                                    $query->whereNull('sales_project_id');
                                }

                                return $query->get()->mapWithKeys(fn ($d) => [
                                    (string) $d->id => (
                                        ($d->delivery_date?->format('d/m/Y') ?? '—').
                                        ' — '.($d->product?->name ?? 'Produto desconhecido').
                                        ' — '.number_format($d->quantity, 3, ',', '.').' '.($d->product?->unit ?? 'un').
                                        ' — R$ '.number_format($d->net_value ?? 0, 2, ',', '.')
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
                        ? 'Assinado em '.$r->acknowledged_at->format('d/m/Y H:i')
                        : 'Aguardando assinatura'),

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
                        $tenantId = $record->tenant_id;
                        $tenant = Tenant::find($tenantId);
                        $associate = $record->associate()->with('user')->first();
                        $project = $record->project;

                        // ── Buscar entregas ──────────────────────────────────
                        // Prioriza IDs explicitamente vinculados; caso contrário filtra por projeto/período
                        $storedIds = $record->delivery_ids ?? [];

                        $query = ProductionDelivery::where('tenant_id', $tenantId)
                            ->where('associate_id', $record->associate_id)
                            ->where('status', DeliveryStatus::APPROVED)
                            ->with('product')
                            ->orderBy('delivery_date');

                        if (! empty($storedIds)) {
                            $query->whereIn('id', array_map('intval', $storedIds));
                        } elseif ($record->sales_project_id) {
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

                        $receiptData = \App\Services\ReceiptDataBuilder::fromDeliveries($deliveries);

                        // ── Marcar como segunda via se já foi assinado ───────
                        $isSecondCopy = $record->acknowledged_at !== null;

                        $svc = app(\App\Services\TemplatedPdfService::class);
                        $pdf = $svc->generateSystemPdf('pdf.project-associate-receipt', [
                            'tenant' => $tenant,
                            'project' => $project,
                            'associate' => $associate,
                            'receipt' => $record,
                            'summary' => $receiptData['summary'],
                            'productsSummary' => $receiptData['productsSummary'],
                            'hasRoundingDivergence' => $receiptData['hasRoundingDivergence'],
                            'isSecondCopy' => $isSecondCopy,
                        ], ['paper' => 'a4', 'orientation' => 'portrait', 'title' => 'Comprovante de Entrega']);

                        $safeName = \Illuminate\Support\Str::slug($associate?->user?->name ?? 'associado');
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
                        $ids = $record->delivery_ids ?? [];

                        $query = ProductionDelivery::where('tenant_id', $record->tenant_id)
                            ->where('associate_id', $record->associate_id)
                            ->where('status', DeliveryStatus::APPROVED)
                            ->with('product')
                            ->orderBy('delivery_date');

                        if (! empty($ids)) {
                            $query->whereIn('id', array_map('intval', $ids));
                        } elseif ($record->sales_project_id) {
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
                            return new \Illuminate\Support\HtmlString('<p style="padding:1rem;color:#888">Nenhuma entrega encontrada.</p>');
                        }

                        $rows = $deliveries->map(fn ($d) => '<tr>'
                            .'<td style="padding:6px 10px;border-bottom:1px solid #eee">'.e($d->delivery_date?->format('d/m/Y') ?? '—').'</td>'
                            .'<td style="padding:6px 10px;border-bottom:1px solid #eee">'.e($d->product?->name ?? '—').'</td>'
                            .'<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right">'.number_format($d->quantity, 3, ',', '.').' '.e($d->product?->unit ?? 'un').'</td>'
                            .'<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right">R$ '.number_format($d->gross_value ?? 0, 2, ',', '.').'</td>'
                            .'<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;color:#c0392b">- R$ '.number_format($d->admin_fee_amount ?? 0, 2, ',', '.').'</td>'
                            .'<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;color:#1a5c3a;font-weight:600">R$ '.number_format($d->net_value ?? 0, 2, ',', '.').'</td>'
                            .'</tr>'
                        )->implode('');

                        $totalGross = $deliveries->sum('gross_value');
                        $totalFee = $deliveries->sum('admin_fee_amount');
                        $totalNet = $deliveries->sum('net_value');

                        $html = '<div style="overflow-x:auto">'
                            .'<table style="width:100%;border-collapse:collapse;font-size:.875rem">'
                            .'<thead><tr style="background:#f4f6f8">'
                            .'<th style="padding:8px 10px;text-align:left">Data</th>'
                            .'<th style="padding:8px 10px;text-align:left">Produto</th>'
                            .'<th style="padding:8px 10px;text-align:right">Qtd.</th>'
                            .'<th style="padding:8px 10px;text-align:right">Bruto</th>'
                            .'<th style="padding:8px 10px;text-align:right">Taxa</th>'
                            .'<th style="padding:8px 10px;text-align:right">Líquido</th>'
                            .'</tr></thead>'
                            .'<tbody>'.$rows.'</tbody>'
                            .'<tfoot><tr style="background:#eef1f5;font-weight:700">'
                            .'<td colspan="3" style="padding:8px 10px">'.$deliveries->count().' entrega(s)</td>'
                            .'<td style="padding:8px 10px;text-align:right">R$ '.number_format($totalGross, 2, ',', '.').'</td>'
                            .'<td style="padding:8px 10px;text-align:right;color:#c0392b">- R$ '.number_format($totalFee, 2, ',', '.').'</td>'
                            .'<td style="padding:8px 10px;text-align:right;color:#1a5c3a">R$ '.number_format($totalNet, 2, ',', '.').'</td>'
                            .'</tr></tfoot></table></div>';

                        return new \Illuminate\Support\HtmlString($html);
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
