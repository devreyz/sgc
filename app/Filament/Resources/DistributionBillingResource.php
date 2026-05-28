<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DistributionBillingResource\Pages;
use App\Models\Associate;
use App\Models\DistributionBilling;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Services\DistributionBillingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DistributionBillingResource extends Resource
{
    protected static ?string $model = DistributionBilling::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?string $navigationLabel = 'Faturamentos';

    protected static ?string $modelLabel = 'Faturamento';

    protected static ?string $pluralModelLabel = 'Faturamentos';

    protected static ?int $navigationSort = 10;

    // -------------------------------------------------------------------------
    //  FORM  (apenas visualização / edição de metadados)
    // -------------------------------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informações do Lote')->schema([
                Forms\Components\Select::make('sales_project_id')
                    ->label('Projeto')
                    ->relationship('salesProject', 'title')
                    ->disabled()
                    ->columnSpan(2),

                Forms\Components\Select::make('associate_id')
                    ->label('Associado')
                    ->relationship('associate', 'id', fn ($query) => $query->with('user'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->user->name ?? "#{$record->id}")
                    ->disabled(),

                Forms\Components\TextInput::make('reference')
                    ->label('Referência'),

                Forms\Components\DatePicker::make('billing_date')
                    ->label('Data do Faturamento')
                    ->disabled(),

                Forms\Components\DatePicker::make('period_start')
                    ->label('Período - Início')
                    ->disabled(),

                Forms\Components\DatePicker::make('period_end')
                    ->label('Período - Fim')
                    ->disabled(),
            ])->columns(3),

            Forms\Components\Section::make('Totais')->schema([
                Forms\Components\TextInput::make('total_distributions')
                    ->label('Qtd. Distribuições')
                    ->disabled(),

                Forms\Components\TextInput::make('total_gross')
                    ->label('Total Bruto')
                    ->prefix('R$')
                    ->disabled(),

                Forms\Components\TextInput::make('total_admin_fee')
                    ->label('Total Taxa Admin')
                    ->prefix('R$')
                    ->disabled(),

                Forms\Components\TextInput::make('total_net')
                    ->label('Total Líquido')
                    ->prefix('R$')
                    ->disabled(),
            ])->columns(4),

            Forms\Components\Textarea::make('notes')
                ->label('Observações')
                ->columnSpanFull(),
        ]);
    }

    // -------------------------------------------------------------------------
    //  TABLE
    // -------------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('tenant_id', session('tenant_id')))
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Lote #')
                    ->sortable(),

                Tables\Columns\TextColumn::make('billing_date')
                    ->label('Data Faturamento')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('salesProject.title')
                    ->label('Projeto')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('associate.user.name')
                    ->label('Associado')
                    ->searchable()
                    ->default('—'),

                Tables\Columns\TextColumn::make('total_distributions')
                    ->label('Distribuições')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_gross')
                    ->label('Bruto')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_admin_fee')
                    ->label('Taxa Admin')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('total_net')
                    ->label('Líquido')
                    ->money('BRL')
                    ->sortable()
                    ->color('success'),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Referência')
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Criado por')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('sales_project_id')
                    ->label('Projeto')
                    ->options(fn () => SalesProject::where('tenant_id', session('tenant_id'))
                        ->orderBy('title')
                        ->pluck('title', 'id')),

                Tables\Filters\SelectFilter::make('associate_id')
                    ->label('Associado')
                    ->options(fn () => Associate::where('tenant_id', session('tenant_id'))
                        ->with('user')
                        ->get()
                        ->mapWithKeys(fn ($a) => [$a->id => $a->user->name ?? "#{$a->id}"])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // -------------------------------------------------------------------------
    //  PAGES
    // -------------------------------------------------------------------------

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDistributionBillings::route('/'),
            'create' => Pages\CreateDistributionBilling::route('/create'),
            'view'   => Pages\ViewDistributionBilling::route('/{record}'),
        ];
    }
}
