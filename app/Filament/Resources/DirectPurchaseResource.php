<?php

namespace App\Filament\Resources;

use App\Enums\CashMovementType;
use App\Enums\DirectPurchasePaymentStatus;
use App\Enums\DirectPurchaseStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\DirectPurchaseResource\Pages;
use App\Filament\Resources\DirectPurchaseResource\RelationManagers;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\DirectPurchase;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class DirectPurchaseResource extends Resource
{
    protected static ?string $model = DirectPurchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?string $modelLabel = 'Compra Direta';

    protected static ?string $pluralModelLabel = 'Compras Diretas';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        $defaultBankAccountId = BankAccount::where('is_default', true)->first()?->id;

        return $form
            ->schema([
                Forms\Components\Section::make('Dados da Compra')
                    ->schema([
                        Forms\Components\Select::make('supplier_id')
                            ->label('Fornecedor')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nome')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('cpf_cnpj')
                                    ->label('CPF/CNPJ')
                                    ->maxLength(18),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Telefone')
                                    ->maxLength(20),
                            ]),

                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Data da Compra')
                            ->required()
                            ->default(now()),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(DirectPurchaseStatus::class)
                            ->required()
                            ->default(DirectPurchaseStatus::DRAFT)
                            ->native(false),

                        Forms\Components\Select::make('payment_status')
                            ->label('Status Pagamento')
                            ->options(DirectPurchasePaymentStatus::class)
                            ->required()
                            ->default(DirectPurchasePaymentStatus::PENDING)
                            ->native(false),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Valores')
                    ->schema([
                        Forms\Components\TextInput::make('total_value')
                            ->label('Valor Total')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                $total = floatval($get('total_value') ?? 0);
                                $discount = floatval($get('discount') ?? 0);
                                $set('final_value', number_format($total - $discount, 2, '.', ''));
                            }),

                        Forms\Components\TextInput::make('discount')
                            ->label('Desconto')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                $total = floatval($get('total_value') ?? 0);
                                $discount = floatval($get('discount') ?? 0);
                                $set('final_value', number_format($total - $discount, 2, '.', ''));
                            }),

                        Forms\Components\TextInput::make('final_value')
                            ->label('Valor Final')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->readOnly()
                            ->helperText('Calculado: Total - Desconto'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Pagamento')
                    ->schema([
                        Forms\Components\Select::make('bank_account_id')
                            ->label('Conta Bancária')
                            ->relationship('bankAccount', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default($defaultBankAccountId),

                        Forms\Components\Select::make('payment_method')
                            ->label('Forma de Pagamento')
                            ->options(PaymentMethod::class)
                            ->native(false),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Data de Pagamento'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Entrega')
                    ->schema([
                        Forms\Components\DatePicker::make('expected_delivery_date')
                            ->label('Previsão de Entrega'),

                        Forms\Components\DatePicker::make('actual_delivery_date')
                            ->label('Data de Recebimento'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Forms\Components\Section::make('Documentos')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Nº Nota Fiscal')
                            ->maxLength(191),

                        Forms\Components\FileUpload::make('invoice_path')
                            ->label('Nota Fiscal (Arquivo)')
                            ->disk('public')
                            ->directory('compras/notas-fiscais')
                            ->acceptedFileTypes(['application/pdf', 'image/*']),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Forms\Components\Section::make('Observações')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornecedor')
                    ->searchable()
                    ->sortable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('final_value')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Pagamento')
                    ->badge(),

                Tables\Columns\TextColumn::make('bankAccount.name')
                    ->label('Conta')
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Forma Pgto')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('NF')
                    ->searchable()
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
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TrashedFilter::make(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(DirectPurchaseStatus::class),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Status Pagamento')
                    ->options(DirectPurchasePaymentStatus::class),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Fornecedor')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('purchase_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('purchase_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('purchase_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprovar Compra')
                    ->modalDescription('Confirma a aprovação desta compra direta?')
                    ->action(function (DirectPurchase $record): void {
                        $record->update([
                            'status' => DirectPurchaseStatus::APPROVED,
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Compra aprovada!')
                            ->send();
                    })
                    ->visible(fn (DirectPurchase $record) => in_array($record->status, [
                        DirectPurchaseStatus::DRAFT,
                        DirectPurchaseStatus::REQUESTED,
                    ])),

                Tables\Actions\Action::make('pay')
                    ->label('Pagar')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->form([
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Data do Pagamento')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('bank_account_id')
                            ->label('Conta Bancária')
                            ->options(BankAccount::where('status', true)->pluck('name', 'id'))
                            ->required()
                            ->default(fn () => BankAccount::where('is_default', true)->first()?->id),
                        Forms\Components\Select::make('payment_method')
                            ->label('Forma de Pagamento')
                            ->options(PaymentMethod::class)
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (DirectPurchase $record, array $data): void {
                        DB::transaction(function () use ($record, $data) {
                            $bankAccount = BankAccount::find($data['bank_account_id']);
                            $newBalance = $bankAccount->current_balance - $record->final_value;

                            CashMovement::create([
                                'type' => CashMovementType::EXPENSE,
                                'amount' => $record->final_value,
                                'balance_after' => $newBalance,
                                'description' => "Compra Direta #{$record->id} - {$record->supplier->name}",
                                'movement_date' => $data['payment_date'],
                                'bank_account_id' => $data['bank_account_id'],
                                'payment_method' => $data['payment_method'],
                                'reference_type' => DirectPurchase::class,
                                'reference_id' => $record->id,
                                'created_by' => auth()->id(),
                            ]);

                            $bankAccount->update(['current_balance' => $newBalance]);

                            $record->update([
                                'payment_status' => DirectPurchasePaymentStatus::PAID,
                                'payment_date' => $data['payment_date'],
                                'bank_account_id' => $data['bank_account_id'],
                                'payment_method' => $data['payment_method'],
                            ]);
                        });

                        Notification::make()
                            ->success()
                            ->title('Pagamento registrado!')
                            ->body('Movimentação de caixa criada automaticamente.')
                            ->send();
                    })
                    ->visible(fn (DirectPurchase $record) => $record->payment_status !== DirectPurchasePaymentStatus::PAID),

                Tables\Actions\Action::make('receive')
                    ->label('Receber')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Recebimento')
                    ->modalDescription('Confirma que os itens desta compra foram recebidos?')
                    ->action(function (DirectPurchase $record): void {
                        $record->update([
                            'status' => DirectPurchaseStatus::RECEIVED,
                            'actual_delivery_date' => now(),
                            'received_by' => auth()->id(),
                            'received_at' => now(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Recebimento confirmado!')
                            ->send();
                    })
                    ->visible(fn (DirectPurchase $record) => in_array($record->status, [
                        DirectPurchaseStatus::APPROVED,
                        DirectPurchaseStatus::ORDERED,
                        DirectPurchaseStatus::PARTIAL_RECEIVED,
                    ])),

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
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDirectPurchases::route('/'),
            'create' => Pages\CreateDirectPurchase::route('/create'),
            'edit' => Pages\EditDirectPurchase::route('/{record}/edit'),
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
