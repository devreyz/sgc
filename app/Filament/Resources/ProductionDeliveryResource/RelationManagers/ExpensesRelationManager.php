<?php

namespace App\Filament\Resources\ProductionDeliveryResource\RelationManagers;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Models\BankAccount;
use App\Models\ChartAccount;
use App\Models\Expense;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    protected static ?string $title = 'Despesas Vinculadas';

    protected static ?string $label = 'Despesa';

    protected static ?string $pluralLabel = 'Despesas';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Despesa')
                ->schema([
                    Forms\Components\TextInput::make('description')
                        ->label('Descrição')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('document_number')
                        ->label('Nº Documento')
                        ->maxLength(100),

                    Forms\Components\DatePicker::make('date')
                        ->label('Data')
                        ->default(now()),

                    Forms\Components\DatePicker::make('due_date')
                        ->label('Vencimento'),

                    Forms\Components\TextInput::make('amount')
                        ->label('Valor (R$)')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->prefix('R$'),

                    Forms\Components\TextInput::make('discount')
                        ->label('Desconto (R$)')
                        ->numeric()
                        ->default(0)
                        ->prefix('R$'),

                    Forms\Components\Select::make('chart_account_id')
                        ->label('Plano de Contas')
                        ->options(fn () => ChartAccount::pluck('name', 'id'))
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('supplier_id')
                        ->label('Fornecedor')
                        ->options(fn () => Supplier::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Select::make('bank_account_id')
                        ->label('Conta Bancária')
                        ->options(fn () => BankAccount::pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Select::make('payment_method')
                        ->label('Forma de Pagamento')
                        ->options(PaymentMethod::class)
                        ->nullable(),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(ExpenseStatus::class)
                        ->required()
                        ->default(ExpenseStatus::PENDING),

                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornecedor')
                    ->default('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nova Despesa')
                    ->icon('heroicon-o-plus-circle')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = session('tenant_id');
                        $data['created_by'] = Auth::id();
                        return $data;
                    })
                    ->successNotificationTitle('Despesa criada e vinculada com sucesso'),

                Tables\Actions\Action::make('attachExisting')
                    ->label('Vincular Existente')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('expense_id')
                            ->label('Selecione a Despesa')
                            ->options(function () {
                                $tenantId = session('tenant_id');
                                return Expense::query()
                                    ->where('tenant_id', $tenantId)
                                    ->whereNull('expenseable_type')
                                    ->whereNull('expenseable_id')
                                    ->orderByDesc('id')
                                    ->limit(200)
                                    ->get()
                                    ->mapWithKeys(fn ($e) => [
                                        $e->id => sprintf('[%s] %s — R$ %s',
                                            $e->date?->format('d/m/Y') ?? '—',
                                            $e->description,
                                            number_format($e->amount, 2, ',', '.')
                                        )
                                    ]);
                            })
                            ->searchable()
                            ->required()
                            ->helperText('Apenas despesas sem vínculo atual são listadas'),
                    ])
                    ->action(function (array $data): void {
                        $expense = Expense::find($data['expense_id']);
                        if (! $expense) {
                            Notification::make()->danger()->title('Despesa não encontrada')->send();
                            return;
                        }
                        $expense->update([
                            'expenseable_type' => $this->getOwnerRecord()::class,
                            'expenseable_id'   => $this->getOwnerRecord()->id,
                        ]);

                        activity()
                            ->causedBy(Auth::user())
                            ->performedOn($expense)
                            ->withProperties([
                                'tenant_id'        => session('tenant_id'),
                                'expenseable_type' => $this->getOwnerRecord()::class,
                                'expenseable_id'   => $this->getOwnerRecord()->id,
                            ])
                            ->log('service.attach_expense');

                        Notification::make()->success()->title('Despesa vinculada com sucesso')->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('detach')
                    ->label('Desvincular')
                    ->icon('heroicon-o-x-mark')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Expense $record) {
                        $record->update([
                            'expenseable_type' => null,
                            'expenseable_id'   => null,
                        ]);
                        Notification::make()->success()->title('Despesa desvinculada')->send();
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(ExpenseStatus::class),
            ]);
    }
}
