<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceOrderResource\Pages;
use App\Filament\Support\ServiceExecutionForm;
use App\Filament\Traits\TenantScoped;
use App\Models\BankAccount;
use App\Models\Service;
use App\Models\ServiceObligation;
use App\Models\ServiceOrder;
use App\Models\ServiceProvider;
use App\Models\ServiceVersion;
use App\Services\Services\ServiceEvidenceService;
use App\Services\Services\ServiceExecutionWorkflow;
use App\Services\Services\ServicePaymentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ServiceOrderResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = ServiceOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Serviços';

    protected static ?string $modelLabel = 'Ordem de serviço';

    protected static ?string $pluralModelLabel = 'Ordens de serviço';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->checkPermissionTo('view_service_management') ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->checkPermissionTo('view_service_management') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->checkPermissionTo('create_service_order') ?? false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Solicitação')
                ->description('A versão publicada fixa as regras operacionais e financeiras desta ordem.')
                ->schema([
                    Forms\Components\TextInput::make('number')->label('Número')->disabled()->visibleOn(['view', 'edit']),
                    Forms\Components\Select::make('service_id')
                        ->label('Serviço')
                        ->options(fn () => Service::query()->where('status', true)->whereHas('currentVersion', fn ($query) => $query->where('status', 'published'))->orderBy('name')->pluck('name', 'id'))
                        ->live()->searchable()->preload()->required()
                        ->afterStateUpdated(function ($state, callable $set): void {
                            $service = $state ? Service::query()->find($state) : null;
                            $set('service_version_id', $service?->current_version_id);
                            $set('service_provider_id', null);
                        }),
                    Forms\Components\Hidden::make('service_version_id')->required(),
                    Forms\Components\Select::make('service_provider_id')
                        ->label('Prestador habilitado')
                        ->options(fn (callable $get) => $get('service_id')
                            ? ServiceProvider::query()->where('status', true)->whereHas('services', fn ($query) => $query->whereKey($get('service_id')))->orderBy('name')->pluck('name', 'id')
                            : collect())
                        ->searchable()->preload()->required(fn (callable $get) => ServiceVersion::query()->find($get('service_version_id'))?->payable_enabled ?? false),
                    Forms\Components\Select::make('associate_id')->label('Associado/beneficiário')->relationship('associate', 'id')->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name ?? "Associado #{$record->id}")->searchable()->preload(),
                    Forms\Components\TextInput::make('beneficiary_name')->label('Nome ou apelido do beneficiário')->helperText('Para serviço interno, informe a organização ou setor atendido.')->required()->maxLength(191)->afterStateHydrated(fn ($component, $record) => $component->state(data_get($record, 'beneficiary_snapshot.name'))),
                    Forms\Components\Select::make('asset_id')->label('Equipamento/recurso')->relationship('asset', 'name')->searchable()->preload(),
                    Forms\Components\DateTimePicker::make('scheduled_at')->label('Agendamento')->seconds(false)->default(now())->required(),
                    Forms\Components\TextInput::make('location')->label('Local')->maxLength(191)->columnSpanFull(),
                    Forms\Components\Textarea::make('work_description')->label('Descrição ou instruções')->rows(3)->columnSpanFull(),
                    Forms\Components\Textarea::make('notes')->label('Observações internas')->rows(2)->columnSpanFull(),
                ])->columns(2),
            Forms\Components\Section::make('Dados solicitados pelo serviço')->schema(function (Forms\Get $get): array {
                $version = ServiceVersion::query()->find($get('service_version_id'));

                return $version ? ServiceExecutionForm::fields($version->snapshot(), ['order'], 'order_data') : [];
            }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('number')->label('OS')->searchable()->sortable()->weight('semibold'),
            Tables\Columns\TextColumn::make('scheduled_at')->label('Agendada')->dateTime('d/m/Y H:i')->sortable(),
            Tables\Columns\TextColumn::make('service.name')->label('Serviço')->searchable(),
            Tables\Columns\TextColumn::make('beneficiary_snapshot.name')->label('Beneficiário'),
            Tables\Columns\TextColumn::make('serviceProvider.name')->label('Prestador')->searchable(),
            Tables\Columns\TextColumn::make('operational_status')->label('Execução')->badge(),
            Tables\Columns\TextColumn::make('financial_summary')->label('Financeiro')->getStateUsing(function (ServiceOrder $record): string {
                $obligations = $record->execution?->obligations ?? collect();
                $open = $obligations->sum('balance');

                return $obligations->isEmpty() ? 'Ainda não gerado' : 'Saldo R$ '.number_format((float) $open, 2, ',', '.');
            }),
        ])->filters([
            Tables\Filters\SelectFilter::make('operational_status')->label('Execução')->options(['scheduled' => 'Agendada', 'in_progress' => 'Em execução', 'submitted' => 'Aguardando conferência', 'rejected' => 'Correção solicitada', 'validated' => 'Validada']),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\Action::make('beneficiary')
                ->label('Informar beneficiário')->icon('heroicon-o-user-plus')->color('warning')
                ->visible(fn (ServiceOrder $record) => blank(data_get($record->beneficiary_snapshot, 'name')) && (auth()->user()?->checkPermissionTo('edit_service_order') ?? false))
                ->form([Forms\Components\TextInput::make('name')->label('Nome ou apelido do beneficiário')->required()->maxLength(191)])
                ->action(function (ServiceOrder $record, array $data): void {
                    abort_unless(auth()->user()->checkPermissionTo('edit_service_order'), 403);
                    $record = ServiceOrder::query()->where('tenant_id', session('tenant_id'))->whereKey($record->id)->firstOrFail();
                    $record->update(['beneficiary_snapshot' => array_replace($record->beneficiary_snapshot ?? [], ['name' => trim($data['name'])])]);
                    activity('service_order')->performedOn($record)->causedBy(auth()->user())->withProperties(['tenant_id' => $record->tenant_id])->log('Beneficiário da ordem informado');
                    Notification::make()->success()->title('Beneficiário informado')->send();
                }),
            Tables\Actions\Action::make('start')->label('Iniciar execução')->visible(fn ($record) => in_array($record->execution?->status, ['draft', 'rejected'], true) && auth()->user()->checkPermissionTo('edit_service_order'))
                ->form(fn ($record) => array_merge([Forms\Components\Hidden::make('operation_key')->default(fn () => (string) Str::uuid())], ServiceExecutionForm::fields($record->execution->catalog_snapshot, ['start'])))
                ->action(function ($record, array $data): void {
                    abort_unless(auth()->user()->checkPermissionTo('edit_service_order'), 403);
                    app(ServiceExecutionWorkflow::class)->start($record->execution, $data['values'] ?? [], $data['operation_key'], auth()->user());
                }),
            Tables\Actions\Action::make('finish')->label('Finalizar execução')->visible(fn ($record) => $record->execution?->status === 'in_progress' && auth()->user()->checkPermissionTo('edit_service_order'))
                ->form(fn ($record) => array_merge([Forms\Components\Hidden::make('operation_key')->default(fn () => (string) Str::uuid())], ServiceExecutionForm::fields($record->execution->catalog_snapshot, ['execution', 'finish'])))
                ->action(function ($record, array $data): void {
                    abort_unless(auth()->user()->checkPermissionTo('edit_service_order'), 403);
                    app(ServiceExecutionWorkflow::class)->submit($record->execution, $data['values'] ?? [], $data['operation_key'], auth()->user());
                }),
            Tables\Actions\Action::make('evidence')->label('Anexar evidência')->visible(fn ($record) => ! $record->execution?->isFrozen() && auth()->user()->checkPermissionTo('edit_service_order'))
                ->form(fn ($record) => [Forms\Components\Select::make('field_key')->label('Evidência')->options(collect($record->execution->catalog_snapshot['fields'] ?? [])->whereIn('type', ['image', 'file', 'signature'])->pluck('label', 'key'))->required(), Forms\Components\FileUpload::make('file')->label('Arquivo')->storeFiles(false)->maxSize(12288)->required()])
                ->action(function ($record, array $data): void {
                    abort_unless(auth()->user()->checkPermissionTo('edit_service_order'), 403);
                    app(ServiceEvidenceService::class)->upload($record->execution, $data['field_key'], $data['file'], auth()->user());
                }),
            Tables\Actions\Action::make('approve')
                ->label('Aprovar execução')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn (ServiceOrder $record) => $record->execution?->status === 'submitted' && (auth()->user()?->checkPermissionTo('approve_service_execution') ?? false))
                ->requiresConfirmation()
                ->action(function (ServiceOrder $record): void {
                    app(ServiceExecutionWorkflow::class)->approve($record->execution, (string) Str::uuid(), auth()->user());
                    Notification::make()->success()->title('Execução aprovada e obrigações geradas')->send();
                }),
            Tables\Actions\Action::make('correction')
                ->label('Solicitar correção')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(fn (ServiceOrder $record) => $record->execution?->status === 'submitted' && (auth()->user()?->checkPermissionTo('review_service_execution') ?? false))
                ->form([Forms\Components\Textarea::make('reason')->label('Motivo e orientação')->required()])
                ->action(function (ServiceOrder $record, array $data): void {
                    app(ServiceExecutionWorkflow::class)->requestCorrection($record->execution, $data['reason'], (string) Str::uuid(), auth()->user());
                    Notification::make()->success()->title('Correção solicitada')->send();
                }),
            Tables\Actions\Action::make('payment')
                ->label('Registrar pagamento')->icon('heroicon-o-banknotes')->color('primary')
                ->visible(fn (ServiceOrder $record) => $record->execution?->obligations->contains(fn ($obligation) => $obligation->balance > 0) && (auth()->user()?->checkPermissionTo('record_service_payment') ?? false))
                ->form(fn (ServiceOrder $record) => [
                    Forms\Components\Hidden::make('operation_key')->default(fn () => (string) Str::uuid())->required(),
                    Forms\Components\Select::make('obligation_id')->label('Obrigação')->options($record->execution->obligations->filter(fn ($obligation) => $obligation->balance > 0)->mapWithKeys(fn (ServiceObligation $obligation) => [$obligation->id => ($obligation->direction === 'payable' ? 'Pagar prestador' : 'Receber do cliente').' — '.$obligation->number.' — saldo R$ '.number_format((float) $obligation->balance, 2, ',', '.')]))->required(),
                    Forms\Components\TextInput::make('amount')->label('Valor')->numeric()->prefix('R$')->minValue(0.01)->required(),
                    Forms\Components\Select::make('payment_method')->label('Forma')->options(['dinheiro' => 'Dinheiro', 'pix' => 'PIX', 'transferencia' => 'Transferência', 'cheque' => 'Cheque', 'cartao' => 'Cartão', 'boleto' => 'Boleto'])->required(),
                    Forms\Components\DatePicker::make('payment_date')->label('Data')->default(today())->required(),
                    Forms\Components\Select::make('bank_account_id')->label('Conta bancária/caixa')->helperText('Obrigatória para gerar a movimentação financeira correspondente.')->options(BankAccount::query()->where('status', true)->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
                ])
                ->action(function (ServiceOrder $record, array $data): void {
                    abort_unless(auth()->user()->checkPermissionTo('record_service_payment'), 403);
                    $obligation = $record->execution->obligations()->where('tenant_id', session('tenant_id'))->whereKey($data['obligation_id'])->firstOrFail();
                    abort_unless(auth()->user()->checkPermissionTo($obligation->direction === 'payable' ? 'manage_service_payables' : 'manage_service_receivables'), 403);
                    app(ServicePaymentService::class)->record($obligation, (float) $data['amount'], $data['payment_method'], $data['payment_date'], $data['bank_account_id'] ?? null, $data['operation_key'], auth()->user());
                    Notification::make()->success()->title('Pagamento registrado com movimentação financeira')->send();
                }),
        ])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceOrders::route('/'),
            'create' => Pages\CreateServiceOrder::route('/create'),
            'view' => Pages\ViewServiceOrder::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('service_version_id')->with(['execution.obligations', 'service', 'serviceProvider']);
    }
}
