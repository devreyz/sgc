<?php

namespace App\Filament\Resources\LoanResource\RelationManagers;

use App\Enums\LoanPaymentStatus;
use App\Enums\PaymentMethod;
use App\Models\LoanPayment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Parcelas / Pagamentos';

    protected static ?string $modelLabel = 'Parcela';

    protected static ?string $pluralModelLabel = 'Parcelas';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('installment_number')
                    ->label('Nº Parcela')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('amount')
                    ->label('Valor da Parcela')
                    ->numeric()
                    ->prefix('R$')
                    ->required(),

                Forms\Components\DatePicker::make('due_date')
                    ->label('Vencimento')
                    ->required(),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(LoanPaymentStatus::class)
                    ->required()
                    ->default(LoanPaymentStatus::PENDING)
                    ->native(false),

                Forms\Components\TextInput::make('interest')
                    ->label('Juros')
                    ->numeric()
                    ->prefix('R$')
                    ->default(0),

                Forms\Components\TextInput::make('fine')
                    ->label('Multa')
                    ->numeric()
                    ->prefix('R$')
                    ->default(0),

                Forms\Components\TextInput::make('total_paid')
                    ->label('Total Pago')
                    ->numeric()
                    ->prefix('R$')
                    ->helperText('Parcela + Juros + Multa'),

                Forms\Components\DatePicker::make('payment_date')
                    ->label('Data do Pagamento'),

                Forms\Components\Select::make('payment_method')
                    ->label('Forma de Pagamento')
                    ->options(PaymentMethod::class)
                    ->native(false),

                Forms\Components\Textarea::make('notes')
                    ->label('Observações')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('installment_number')
                    ->label('Parcela')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('interest')
                    ->label('Juros')
                    ->money('BRL')
                    ->default(0),

                Tables\Columns\TextColumn::make('fine')
                    ->label('Multa')
                    ->money('BRL')
                    ->default(0),

                Tables\Columns\TextColumn::make('total_paid')
                    ->label('Total Pago')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (LoanPayment $record): string => 
                        $record->status === LoanPaymentStatus::PENDING && $record->due_date?->isPast() 
                            ? 'danger' 
                            : 'gray'
                    ),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Dt. Pagamento')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Forma')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->defaultSort('installment_number')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Adicionar Parcela')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        return $data;
                    })
                    ->after(function () {
                        $this->getOwnerRecord()->updateBalance();
                    }),

                Tables\Actions\Action::make('generate_installments')
                    ->label('Gerar Parcelas')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Gerar Parcelas Automaticamente')
                    ->modalDescription('Isso criará todas as parcelas com base nos dados do empréstimo. Parcelas já existentes não serão afetadas.')
                    ->action(function () {
                        $loan = $this->getOwnerRecord();
                        $existingCount = $loan->payments()->count();

                        if ($existingCount >= $loan->installments) {
                            Notification::make()
                                ->warning()
                                ->title('Parcelas já geradas')
                                ->body("Já existem {$existingCount} parcelas registradas.")
                                ->send();
                            return;
                        }

                        $startNumber = $existingCount + 1;
                        $baseDate = $loan->first_payment_date;

                        for ($i = $startNumber; $i <= $loan->installments; $i++) {
                            $dueDate = $baseDate->copy()->addMonths($i - 1);

                            LoanPayment::create([
                                'loan_id' => $loan->id,
                                'installment_number' => $i,
                                'amount' => $loan->installment_value,
                                'interest' => 0,
                                'fine' => 0,
                                'total_paid' => 0,
                                'due_date' => $dueDate,
                                'status' => LoanPaymentStatus::PENDING,
                                'created_by' => auth()->id(),
                            ]);
                        }

                        Notification::make()
                            ->success()
                            ->title('Parcelas geradas!')
                            ->body(($loan->installments - $existingCount) . ' parcelas criadas.')
                            ->send();
                    })
                    ->visible(fn () => $this->getOwnerRecord()->payments()->count() < $this->getOwnerRecord()->installments),
            ])
            ->actions([
                Tables\Actions\Action::make('pay')
                    ->label('Pagar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Data do Pagamento')
                            ->required()
                            ->default(now()),
                        Forms\Components\TextInput::make('interest')
                            ->label('Juros')
                            ->numeric()
                            ->prefix('R$')
                            ->default(fn (LoanPayment $record) => $record->interest ?? 0),
                        Forms\Components\TextInput::make('fine')
                            ->label('Multa')
                            ->numeric()
                            ->prefix('R$')
                            ->default(fn (LoanPayment $record) => $record->fine ?? 0),
                        Forms\Components\TextInput::make('total_paid')
                            ->label('Total Pago')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->default(fn (LoanPayment $record) => $record->amount),
                        Forms\Components\Select::make('payment_method')
                            ->label('Forma de Pagamento')
                            ->options(PaymentMethod::class)
                            ->native(false),
                    ])
                    ->action(function (LoanPayment $record, array $data): void {
                        $record->update([
                            'payment_date' => $data['payment_date'],
                            'interest' => $data['interest'] ?? 0,
                            'fine' => $data['fine'] ?? 0,
                            'total_paid' => $data['total_paid'],
                            'payment_method' => $data['payment_method'] ?? null,
                            'status' => LoanPaymentStatus::PAID,
                        ]);

                        $this->getOwnerRecord()->updateBalance();

                        Notification::make()
                            ->success()
                            ->title('Parcela paga!')
                            ->send();
                    })
                    ->visible(fn (LoanPayment $record) => $record->status !== LoanPaymentStatus::PAID),

                Tables\Actions\EditAction::make()
                    ->after(function () {
                        $this->getOwnerRecord()->updateBalance();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->after(function () {
                        $this->getOwnerRecord()->updateBalance();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function () {
                            $this->getOwnerRecord()->updateBalance();
                        }),
                ]),
            ]);
    }
}
