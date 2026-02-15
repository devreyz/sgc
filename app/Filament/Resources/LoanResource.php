<?php

namespace App\Filament\Resources;

use App\Enums\LoanPaymentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\LoanResource\Pages;
use App\Filament\Resources\LoanResource\RelationManagers;
use App\Models\Loan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Traits\TenantScoped;

class LoanResource extends Resource
{
    use TenantScoped;
    protected static ?string $model = Loan::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?string $modelLabel = 'Empréstimo';

    protected static ?string $pluralModelLabel = 'Empréstimos';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Empréstimo')
                    ->schema([
                        Forms\Components\Select::make('associate_id')
                            ->label('Associado')
                            ->relationship('associate', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => optional($record->user)->name ?? $record->property_name ?? "#{$record->id}")
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('purpose')
                            ->label('Finalidade')
                            ->required()
                            ->maxLength(191)
                            ->placeholder('Ex: Insumos para plantio, Manutenção de equipamento...'),

                        Forms\Components\DatePicker::make('loan_date')
                            ->label('Data do Empréstimo')
                            ->required()
                            ->default(now()),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(LoanStatus::class)
                            ->required()
                            ->default(LoanStatus::ACTIVE)
                            ->native(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Valores e Parcelas')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Valor Principal')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                $amount = floatval($get('amount') ?? 0);
                                $rate = floatval($get('interest_rate') ?? 0);
                                $installments = intval($get('installments') ?? 1);

                                $interest = $amount * ($rate / 100);
                                $total = $amount + $interest;
                                $installmentValue = $installments > 0 ? $total / $installments : $total;

                                $set('total_with_interest', number_format($total, 2, '.', ''));
                                $set('balance', number_format($total, 2, '.', ''));
                                $set('installment_value', number_format($installmentValue, 2, '.', ''));
                            }),

                        Forms\Components\TextInput::make('interest_rate')
                            ->label('Taxa de Juros (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                $amount = floatval($get('amount') ?? 0);
                                $rate = floatval($get('interest_rate') ?? 0);
                                $installments = intval($get('installments') ?? 1);

                                $interest = $amount * ($rate / 100);
                                $total = $amount + $interest;
                                $installmentValue = $installments > 0 ? $total / $installments : $total;

                                $set('total_with_interest', number_format($total, 2, '.', ''));
                                $set('balance', number_format($total, 2, '.', ''));
                                $set('installment_value', number_format($installmentValue, 2, '.', ''));
                            }),

                        Forms\Components\TextInput::make('installments')
                            ->label('Nº de Parcelas')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                $total = floatval($get('total_with_interest') ?? 0);
                                $installments = intval($get('installments') ?? 1);
                                $installmentValue = $installments > 0 ? $total / $installments : $total;
                                $set('installment_value', number_format($installmentValue, 2, '.', ''));
                            }),

                        Forms\Components\TextInput::make('total_with_interest')
                            ->label('Total com Juros')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly()
                            ->helperText('Calculado: Principal + Juros'),

                        Forms\Components\TextInput::make('installment_value')
                            ->label('Valor da Parcela')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly()
                            ->helperText('Calculado: Total / Parcelas'),

                        Forms\Components\TextInput::make('balance')
                            ->label('Saldo Devedor')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly()
                            ->visibleOn('edit'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Datas de Pagamento')
                    ->schema([
                        Forms\Components\DatePicker::make('first_payment_date')
                            ->label('Primeiro Vencimento')
                            ->required(),

                        Forms\Components\DatePicker::make('last_payment_date')
                            ->label('Último Pagamento')
                            ->disabled()
                            ->visibleOn('edit'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Informações Adicionais')
                    ->schema([
                        Forms\Components\Placeholder::make('paid_info')
                            ->label('Pagamentos')
                            ->content(fn (?Loan $record) => $record 
                                ? "Pago: R$ " . number_format($record->paid_amount, 2, ',', '.') . " | Parcelas pagas: {$record->paid_installments}/{$record->installments}"
                                : '-')
                            ->visibleOn('edit'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),

                Forms\Components\Hidden::make('paid_amount')
                    ->default(0),

                Forms\Components\Hidden::make('paid_installments')
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('associate.full_identification')
                    ->label('Associado')
                    ->searchable()
                    ->sortable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('purpose')
                    ->label('Finalidade')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Principal')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_with_interest')
                    ->label('Total')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Saldo')
                    ->money('BRL')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),

                Tables\Columns\TextColumn::make('installments')
                    ->label('Parcelas')
                    ->formatStateUsing(fn (Loan $record) => "{$record->paid_installments}/{$record->installments}"),

                Tables\Columns\TextColumn::make('loan_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('first_payment_date')
                    ->label('1º Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Criado por')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TrashedFilter::make(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(LoanStatus::class),

                Tables\Filters\SelectFilter::make('associate_id')
                    ->label('Associado')
                    ->options(fn () => \App\Models\Associate::with('user')->get()->mapWithKeys(fn ($a) => [ $a->id => optional($a->user)->name ?? $a->property_name ?? "#{$a->id}" ])->toArray())
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprovar Empréstimo')
                    ->modalDescription(fn (Loan $record) => "Aprovar empréstimo de R$ " . number_format($record->total_with_interest, 2, ',', '.') . " para " . (optional($record->associate->user)->name ?? $record->associate->property_name ?? "#{$record->associate->id}") . "?")
                    ->action(function (Loan $record): void {
                        $record->update([
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Empréstimo aprovado!')
                            ->send();
                    })
                    ->visible(fn (Loan $record) => empty($record->approved_by) && $record->status === LoanStatus::ACTIVE),

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
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'edit' => Pages\EditLoan::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
