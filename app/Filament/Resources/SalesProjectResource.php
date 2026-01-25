<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesProjectResource\Pages;
use App\Filament\Resources\SalesProjectResource\RelationManagers;
use App\Filament\Traits\HasExportActions;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\SalesProject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalesProjectResource extends Resource
{
    use HasExportActions;
    
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
                            ->default(ProjectStatus::DRAFT),

                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('contract_number')
                            ->label('Nº Contrato')
                            ->maxLength(50),

                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data Início')
                            ->required(),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data Fim')
                            ->required()
                            ->after('start_date'),

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
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Descrição')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
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
                    ->formatStateUsing(fn (ProjectType $state): string => $state->label())
                    ->color(fn (ProjectType $state): string => $state->color()),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(25),

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
                    ->formatStateUsing(fn (SalesProject $record): string => 
                        number_format($record->progress_percentage, 1, ',', '.') . '%'
                    )
                    ->badge()
                    ->color(fn (SalesProject $record): string => 
                        $record->progress_percentage >= 100 ? 'success' : 
                        ($record->progress_percentage >= 50 ? 'warning' : 'danger')
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ProjectStatus $state): string => $state->label())
                    ->color(fn (ProjectStatus $state): string => $state->color()),
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DemandsRelationManager::class,
            RelationManagers\DeliveriesRelationManager::class,
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
