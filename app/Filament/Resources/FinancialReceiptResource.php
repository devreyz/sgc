<?php

namespace App\Filament\Resources;

use App\Enums\FinancialReceiptStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\FinancialReceiptResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\BankAccount;
use App\Models\FinancialReceipt;
use App\Models\TenantUser;
use App\Services\FinancialReceiptService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FinancialReceiptResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = FinancialReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?string $navigationLabel = 'Recebimentos e Recibos';

    protected static ?string $modelLabel = 'Recibo de recebimento';

    protected static ?string $pluralModelLabel = 'Recebimentos e recibos';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Pagador e recebimento')->schema([
                Forms\Components\Select::make('payer_type')->label('Tipo de pagador')->options([
                    'customer' => 'Cliente', 'organization' => 'Organização', 'associate' => 'Associado',
                    'supplier' => 'Fornecedor', 'service_provider' => 'Prestador', 'other' => 'Outra pessoa/entidade',
                ])->required()->default('other'),
                Forms\Components\TextInput::make('payer_name')->label('Nome / razão social')->required()->maxLength(255),
                Forms\Components\TextInput::make('payer_document')->label('CPF / CNPJ / documento')->maxLength(30),
                Forms\Components\TextInput::make('payer_contact')->label('Contato')->maxLength(255),
                Forms\Components\DatePicker::make('received_on')->label('Data do recebimento')->required()->default(now()),
                Forms\Components\Select::make('payment_method')->label('Meio de pagamento')->options(PaymentMethod::class)->required()->default(PaymentMethod::DINHEIRO->value),
                Forms\Components\Select::make('bank_account_id')->label('Conta / caixa de entrada')
                    ->options(fn () => BankAccount::query()->active()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()->required(),
                Forms\Components\Select::make('chart_account_id')->label('Classificação no plano de contas')
                    ->relationship('chartAccount', 'name')->searchable()->preload(),
                Forms\Components\TextInput::make('payment_reference')->label('Referência da operação')->maxLength(255)
                    ->helperText('Ex.: NSU, ID PIX, cheque, parcela ou contrato.'),
                Forms\Components\Textarea::make('purpose')->label('Referente a')->rows(2)->columnSpanFull(),
                Forms\Components\TextInput::make('manual_amount')->label('Valor recebido sem itens')->numeric()->minValue(0.01)->prefix('R$')
                    ->helperText('Use quando o recibo tiver apenas uma referência, sem detalhamento de itens.'),
            ])->columns(2),

            Forms\Components\Section::make('Itens do comprovante')->schema([
                Forms\Components\Repeater::make('items')->relationship()->orderColumn('position')
                    ->defaultItems(0)->reorderable()->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('description')->label('Descrição')->required()->maxLength(1000)->columnSpan(4),
                        Forms\Components\TextInput::make('quantity')->label('Quantidade')->numeric()->minValue(0.0001)->required()->default(1)->columnSpan(2),
                        Forms\Components\TextInput::make('unit')->label('Unidade')->required()->default('un')->maxLength(30)->columnSpan(1),
                        Forms\Components\TextInput::make('unit_price')->label('Valor unitário')->numeric()->minValue(0)->required()->prefix('R$')->columnSpan(2),
                        Forms\Components\TextInput::make('reference')->label('Referência')->maxLength(255)->columnSpan(3),
                    ])->columns(12),
            ]),

            Forms\Components\Section::make('Observações internas')->schema([
                Forms\Components\Textarea::make('notes')->label('Observações')->rows(3)->columnSpanFull(),
            ])->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('formatted_number')->label('Número')->searchable(['receipt_number']),
            Tables\Columns\TextColumn::make('received_on')->label('Data')->date('d/m/Y')->sortable(),
            Tables\Columns\TextColumn::make('payer_name')->label('Pagador')->searchable()->limit(45),
            Tables\Columns\TextColumn::make('payment_method')->label('Meio')->badge(),
            Tables\Columns\TextColumn::make('bankAccount.name')->label('Conta')->toggleable(),
            Tables\Columns\TextColumn::make('total_amount')->label('Valor')->money('BRL')->sortable()->weight('bold'),
            Tables\Columns\TextColumn::make('status')->label('Situação')->badge(),
            Tables\Columns\TextColumn::make('issued_by_member_name')->label('Recebido por')
                ->placeholder('Membro não identificado')->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->label('Situação')->options(FinancialReceiptStatus::class),
            Tables\Filters\SelectFilter::make('bank_account_id')->label('Conta')->relationship('bankAccount', 'name'),
            Tables\Filters\SelectFilter::make('payment_method')->label('Meio')->options(PaymentMethod::class),
            Tables\Filters\Filter::make('received_on')->form([
                Forms\Components\DatePicker::make('from')->label('De'),
                Forms\Components\DatePicker::make('until')->label('Até'),
            ])->query(fn ($query, array $data) => $query
                ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('received_on', '>=', $date))
                ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('received_on', '<=', $date))),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make()->visible(fn (FinancialReceipt $record) => $record->isDraft()),
            Tables\Actions\Action::make('issue')->label('Emitir')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn (FinancialReceipt $record) => $record->isDraft() && auth()->user()->can('financial_receipt.issue'))
                ->requiresConfirmation()->modalDescription('A emissão creditará a conta selecionada e bloqueará a edição do recibo.')
                ->action(function (FinancialReceipt $record): void {
                    app(FinancialReceiptService::class)->issue($record, auth()->user());
                    Notification::make()->title('Recibo emitido e conta creditada')->success()->send();
                }),
            Tables\Actions\Action::make('print')->label('Imprimir')->icon('heroicon-o-printer')
                ->visible(fn (FinancialReceipt $record) => ! $record->isDraft())
                ->url(fn (FinancialReceipt $record) => route('financial-receipts.print', $record))->openUrlInNewTab(),
        ])->bulkActions([])->defaultSort('received_on', 'desc');
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof FinancialReceipt && $record->isDraft() && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->addSelect([
            'issued_by_member_name' => TenantUser::query()
                ->select('tenant_name')
                ->whereColumn('tenant_user.tenant_id', 'financial_receipts.tenant_id')
                ->whereColumn('tenant_user.user_id', 'financial_receipts.issued_by')
                ->limit(1),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinancialReceipts::route('/'),
            'create' => Pages\CreateFinancialReceipt::route('/create'),
            'view' => Pages\ViewFinancialReceipt::route('/{record}'),
            'edit' => Pages\EditFinancialReceipt::route('/{record}/edit'),
        ];
    }
}
