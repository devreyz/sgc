<?php

namespace App\Filament\Resources;

use App\Enums\PaymentMethod;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Filament\Resources\SalesProjectResource\Pages;
use App\Filament\Resources\SalesProjectResource\RelationManagers;
use App\Filament\Traits\HasExportActions;
use App\Filament\Traits\TenantScoped;
use App\Models\Associate;
use App\Models\Organization;
use App\Models\SalesProject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalesProjectResource extends Resource
{
    use HasExportActions;
    use TenantScoped;

    protected static ?string $model = SalesProject::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Projetos de Venda';

    protected static ?string $modelLabel = 'Projeto de Venda';

    protected static ?string $pluralModelLabel = 'Projetos de Venda';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Projeto')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options(ProjectType::class)
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(ProjectStatus::class)
                            ->required()
                            ->default(ProjectStatus::DRAFT)
                            ->disabled()
                            ->dehydrated(fn (string $context): bool => $context === 'create')
                            ->helperText('Status só pode ser alterado por ações específicas'),

                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente (opcional)')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Referência opcional. Clientes reais são definidos nas distribuições das entregas.'),

                        Forms\Components\TextInput::make('contract_number')
                            ->label('Nº Contrato')
                            ->maxLength(50),

                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data Início')
                            ->required(),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data Fim')
                            ->required()
                            ->afterOrEqual('start_date'),

                        Forms\Components\TextInput::make('reference_year')
                            ->label('Ano de Referência')
                            ->numeric()
                            ->default(date('Y'))
                            ->required(),

                        Forms\Components\TextInput::make('total_value')
                            ->label('Valor do Contrato')
                            ->numeric()
                            ->prefix('R$')
                            ->helperText('Valor total estimado ou contratado'),

                        Forms\Components\TextInput::make('admin_fee_percentage')
                            ->label('Taxa de Administração')
                            ->numeric()
                            ->suffix('%')
                            ->default(10)
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('Percentual retido pela cooperativa (padrão 10%)'),

                        Forms\Components\Toggle::make('allow_any_product')
                            ->label('Aceitar qualquer produto')
                            ->helperText('Quando ativo, o projeto pode receber qualquer produto cadastrado sem demandas pré-definidas')
                            ->default(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Clientes (Referência)')
                    ->description('Associação de referência — clientes reais são definidos via distribuição de entregas.')
                    ->schema([
                        Forms\Components\Select::make('customers')
                            ->label('Clientes Adicionais')
                            ->multiple()
                            ->relationship('customers', 'name')
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(fn (string $search) =>
                                \App\Models\Customer::where('tenant_id', session('tenant_id'))
                                    ->where('name', 'like', "%{$search}%")
                                    ->orderBy('name')
                                    ->limit(50)
                                    ->pluck('name', 'id')
                            )
                            ->getOptionLabelUsing(fn ($value) =>
                                \App\Models\Customer::find($value)?->name ?? $value
                            )
                            ->helperText('Opcional. O vínculo financeiro real acontece nas distribuições de cada entrega.')
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->collapsible(),

                Forms\Components\Section::make('Organizações Participantes')
                    ->description('Municípios, entidades ou organizações atendidos por este projeto.')
                    ->schema([
                        Forms\Components\Select::make('organizations')
                            ->label('Organizações')
                            ->multiple()
                            ->relationship('organizations', 'name')
                            ->getSearchResultsUsing(fn (string $search) =>
                                Organization::where('tenant_id', session('tenant_id'))
                                    ->where('active', true)
                                    ->where('name', 'like', "%{$search}%")
                                    ->orderBy('name')
                                    ->limit(50)
                                    ->pluck('name', 'id')
                            )
                            ->getOptionLabelUsing(fn ($value) =>
                                Organization::find($value)?->name ?? $value
                            )
                            ->searchable()
                            ->preload()
                            ->helperText('Municípios, CONAB, Estado, Hospitais etc. que fazem parte do contrato.')
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->collapsible(),

                Forms\Components\Section::make('Descrição')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make('Controle de Participação')
                    ->description('Restrinja quais associados podem registrar entregas neste projeto.')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Forms\Components\Toggle::make('restrict_participants')
                            ->label('Restringir participantes do projeto')
                            ->helperText('Quando ativado, apenas os associados selecionados abaixo poderão registrar entregas.')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('associates')
                            ->label('Associados participantes')
                            ->multiple()
                            ->relationship('associates', 'id')
                            ->getSearchResultsUsing(fn (string $search) =>
                                Associate::query()
                                    ->select('associates.*')
                                    ->join('tenant_user', function ($join) {
                                        $join->on('tenant_user.user_id', '=', 'associates.user_id')
                                            ->where('tenant_user.tenant_id', '=', session('tenant_id'));
                                    })
                                    ->where('associates.tenant_id', session('tenant_id'))
                                    ->where(function (Builder $query) use ($search) {
                                        $query->where('tenant_user.tenant_name', 'like', "%{$search}%")
                                            ->orWhere('associates.member_code', 'like', "%{$search}%")
                                            ->orWhere('associates.registration_number', 'like', "%{$search}%")
                                            ->orWhere('associates.district', 'like', "%{$search}%")
                                            ->orWhere('associates.city', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (Associate $associate) => [$associate->id => self::associateOptionLabel($associate)])
                            )
                            ->getOptionLabelsUsing(fn (array $values): array => Associate::query()
                                ->where('tenant_id', session('tenant_id'))
                                ->whereIn('id', $values)
                                ->get()
                                ->mapWithKeys(fn (Associate $associate) => [$associate->id => self::associateOptionLabel($associate)])
                                ->all())
                            ->preload(false)
                            ->searchable()
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('restrict_participants'))
                            ->saveRelationshipsUsing(function (SalesProject $record, ?array $state): void {
                                $selectedIds = Associate::query()
                                    ->where('tenant_id', $record->tenant_id)
                                    ->whereIn('id', collect($state)->filter()->map(fn ($id) => (int) $id))
                                    ->pluck('id');

                                \Illuminate\Support\Facades\DB::transaction(function () use ($record, $selectedIds): void {
                                    if ($record->restrict_participants) {
                                        $record->projectAssociates()
                                            ->where('tenant_id', $record->tenant_id)
                                            ->when($selectedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('associate_id', $selectedIds))
                                            ->update(['status' => 'blocked', 'updated_by' => auth()->id()]);
                                    }

                                    foreach ($selectedIds as $associateId) {
                                        $link = $record->projectAssociates()->firstOrNew([
                                            'tenant_id' => $record->tenant_id,
                                            'associate_id' => $associateId,
                                        ]);
                                        if (! $link->exists) {
                                            $link->created_by = auth()->id();
                                        }
                                        $link->fill([
                                            'status' => 'active',
                                            'updated_by' => auth()->id(),
                                        ])->save();
                                    }
                                });
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->collapsible(),

                Forms\Components\Section::make('Limites de Faturamento')
                    ->description('Defina limites financeiros e de quantidade por associado.')
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        Forms\Components\TextInput::make('max_total_value_per_associate')
                            ->label('Limite máximo por associado (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->nullable()
                            ->helperText('Valor máximo acumulado que cada associado pode faturar neste projeto. Deixe em branco para sem limite.')
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->collapsible(),
            ]);
    }

    private static function associateOptionLabel(Associate $associate): string
    {
        $code = $associate->member_code ?: $associate->registration_number;
        $details = collect([
            $code ? 'Associado #'.$code : null,
            $associate->district ?: $associate->city,
        ])->filter()->implode(' - ');

        return $associate->display_name.($details !== '' ? ' - '.$details : '');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (ProjectType $state): string => $state->getLabel())
                    ->color(fn (ProjectType $state): string => $state->getColor()),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('organizations_count')
                    ->label('Orgs.')
                    ->counts('organizations')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('contract_number')
                    ->label('Contrato')
                    ->searchable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Progresso')
                    ->formatStateUsing(fn (SalesProject $record): string => number_format($record->progress_percentage, 1, ',', '.').'%'
                    )
                    ->badge()
                    ->color(fn (SalesProject $record): string => $record->progress_percentage >= 100 ? 'success' :
                        ($record->progress_percentage >= 50 ? 'warning' : 'danger')
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ProjectStatus $state): string => $state->getLabel())
                    ->color(fn (ProjectStatus $state): string => $state->getColor()),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(ProjectType::class),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(ProjectStatus::class),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Cliente')
                    ->relationship('customer', 'name'),
            ])
            ->headerActions([
                self::getExportAction(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                // Ativar projeto (Rascunho → Em Execução)
                Tables\Actions\Action::make('activate')
                    ->label('Iniciar Projeto')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (SalesProject $record) => $record->status === ProjectStatus::DRAFT)
                    ->requiresConfirmation()
                    ->modalHeading('Iniciar Projeto')
                    ->modalDescription('Ao iniciar o projeto, ele será marcado como "Em Execução" e passará a aceitar registros de entrega. Deseja continuar?')
                    ->action(function (SalesProject $record) {
                        $record->update(['status' => ProjectStatus::ACTIVE]);

                        Notification::make()
                            ->success()
                            ->title('Projeto iniciado com sucesso!')
                            ->body('O projeto agora está em execução e aceita registros de entrega.')
                            ->send();
                    }),

                // Encerrar entregas (Em Execução → Entregas Encerradas)
                Tables\Actions\Action::make('close_deliveries')
                    ->label('Encerrar Entregas')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->visible(fn (SalesProject $record) => $record->status === ProjectStatus::ACTIVE)
                    ->requiresConfirmation()
                    ->modalHeading('Encerrar Recebimento de Entregas')
                    ->modalDescription('O projeto deixará de aceitar novas recepções. Distribuições e faturamentos ainda serão permitidos.')
                    ->action(function (SalesProject $record) {
                        $record->update(['status' => ProjectStatus::DELIVERIES_CLOSED]);

                        Notification::make()
                            ->success()
                            ->title('Entregas encerradas.')
                            ->send();
                    }),

                // Concluir projeto
                Tables\Actions\Action::make('complete')
                    ->label('Concluir')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SalesProject $record) => in_array($record->status, [
                        ProjectStatus::ACTIVE,
                        ProjectStatus::DELIVERIES_CLOSED,
                    ]))
                    ->requiresConfirmation()
                    ->modalHeading('Concluir Projeto')
                    ->modalDescription('Marcar como concluído definitivamente?')
                    ->action(function (SalesProject $record) {
                        $record->update([
                            'status'       => ProjectStatus::COMPLETED,
                            'completed_at' => now(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Projeto concluído.')
                            ->send();
                    }),

                // Reabrir (Entregas Encerradas / Arquivado → Em Execução)
                Tables\Actions\Action::make('reopen')
                    ->label('Reabrir')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (SalesProject $record) => in_array($record->status, [
                        ProjectStatus::DELIVERIES_CLOSED,
                        ProjectStatus::SUSPENDED,
                        ProjectStatus::ARCHIVED,
                        ProjectStatus::COMPLETED,
                    ]))
                    ->requiresConfirmation()
                    ->modalHeading('Reabrir Projeto')
                    ->modalDescription('O projeto voltará ao status "Em Execução".')
                    ->action(function (SalesProject $record) {
                        $record->update(['status' => ProjectStatus::ACTIVE]);

                        Notification::make()
                            ->success()
                            ->title('Projeto reaberto.')
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DemandsRelationManager::class,
            RelationManagers\BuyerRequestsRelationManager::class,
            RelationManagers\DeliveriesRelationManager::class,
            RelationManagers\AssociatePaymentsRelationManager::class,
            RelationManagers\AssociateProductLimitsRelationManager::class,
            RelationManagers\FeesRelationManager::class,
            RelationManagers\CustomerFeesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesProjects::route('/'),
            'create' => Pages\CreateSalesProject::route('/create'),
            'view' => Pages\ViewSalesProject::route('/{record}'),
            'edit' => Pages\EditSalesProject::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    protected static function getExportColumns(): array
    {
        return [
            'title' => 'Título',
            'type' => 'Tipo',
            'customer.name' => 'Cliente',
            'contract_number' => 'Nº Contrato',
            'start_date' => 'Data Início',
            'end_date' => 'Data Fim',
            'reference_year' => 'Ano Referência',
            'total_value' => 'Valor Total',
            'admin_fee_percentage' => 'Taxa Adm (%)',
            'status' => 'Status',
            'completed_at' => 'Finalizado Em',
        ];
    }
}
