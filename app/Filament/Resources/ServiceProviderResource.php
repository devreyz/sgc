<?php

namespace App\Filament\Resources;

use App\Filament\Pages\ServiceOrdersPaymentReport;
use App\Filament\Resources\ServiceProviderResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\ServiceProvider;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;

class ServiceProviderResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = ServiceProvider::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Serviços';

    protected static ?string $modelLabel = 'Prestador de Serviço';

    protected static ?string $pluralModelLabel = 'Prestadores de Serviço';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados Pessoais')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Usuário do Sistema')
                            ->relationship(
                                name: 'user',
                                titleAttribute: 'name',
                                modifyQueryUsing: function ($query) {
                                    $tenantId = session('tenant_id');
                                    if ($tenantId) {
                                        // Filtrar apenas usuários desta organização
                                        $query->whereHas('tenants', function ($q) use ($tenantId) {
                                            $q->where('tenant_id', $tenantId);
                                        });
                                    }

                                    return $query;
                                }
                            )
                            ->getOptionLabelFromRecordUsing(fn (User $record) => $record->display_name)
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->helperText('Vincule a um usuário cadastrado no sistema (associado ou não)'),

                        Forms\Components\TextInput::make('name')
                            ->label('Nome Completo')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('cpf')
                            ->label('CPF')
                            ->mask('999.999.999-99')
                            ->maxLength(14)
                            ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule) {
                                return $rule->where('tenant_id', session('tenant_id'));
                            }),

                        Forms\Components\TextInput::make('rg')
                            ->label('RG')
                            ->maxLength(20),

                        Forms\Components\Select::make('type')
                            ->label('Tipo / Função')
                            ->options([
                                'tratorista' => 'Tratorista',
                                'motorista' => 'Motorista',
                                'diarista' => 'Diarista',
                                'tecnico' => 'Técnico',
                                'consultor' => 'Consultor',
                                'outro' => 'Outro',
                            ])
                            ->required()
                            ->default('outro'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telefone')
                            ->tel()
                            ->mask('(99) 99999-9999')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(191),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Endereço')
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->label('Endereço')
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('city')
                            ->label('Cidade')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('state')
                            ->label('UF')
                            ->maxLength(2),

                        Forms\Components\TextInput::make('zip_code')
                            ->label('CEP')
                            ->mask('99999-999')
                            ->maxLength(10),
                    ])
                    ->columns(3)
                    ->collapsed(),

                Forms\Components\Section::make('Dados Bancários / Pagamento')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Banco')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('bank_agency')
                            ->label('Agência')
                            ->maxLength(10),

                        Forms\Components\TextInput::make('bank_account')
                            ->label('Conta')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('pix_key')
                            ->label('Chave PIX')
                            ->maxLength(191),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Status')->description('Configure a remuneração em Versões e preços do serviço. O cadastro bancário informa onde pagar.')
                    ->schema([
                        Forms\Components\Toggle::make('status')
                            ->label('Ativo')
                            ->default(true),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('user.display_name')
                    ->label('Usuário Vinculado')
                    ->placeholder('Sem vínculo')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('cpf')
                    ->label('CPF')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Função')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'tratorista' => 'Tratorista',
                        'motorista' => 'Motorista',
                        'diarista' => 'Diarista',
                        'tecnico' => 'Técnico',
                        'consultor' => 'Consultor',
                        'outro' => 'Outro',
                        default => ucfirst($state),
                    })
                    ->color(fn ($state): string => match ($state) {
                        'tratorista' => 'success',
                        'motorista' => 'info',
                        'diarista' => 'warning',
                        'tecnico' => 'primary',
                        'consultor' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefone')
                    ->searchable()
                    ->icon('heroicon-o-phone'),

                Tables\Columns\TextColumn::make('service_balance')->label('A pagar')->state(fn (ServiceProvider $record) => $record->serviceObligations()->where('direction', 'payable')->get()->sum('balance'))->money('BRL'),

                Tables\Columns\IconColumn::make('status')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Função')
                    ->options([
                        'tratorista' => 'Tratorista',
                        'motorista' => 'Motorista',
                        'diarista' => 'Diarista',
                        'tecnico' => 'Técnico',
                        'consultor' => 'Consultor',
                        'outro' => 'Outro',
                    ]),
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Ativo'),
                Tables\Filters\Filter::make('has_pending')
                    ->label('Com Pagamento Pendente')
                    ->query(fn (Builder $query) => $query->whereHas('serviceObligations', fn ($q) => $q->where('direction', 'payable')->whereIn('status', ['open', 'partially_paid']))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->headerActions([Tables\Actions\Action::make('report')->label('Prestação de contas')->url(fn () => ServiceOrdersPaymentReport::getUrl())->visible(fn () => auth()->user()->checkPermissionTo('view_service_reports'))]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceProviders::route('/'),
            'create' => Pages\CreateServiceProvider::route('/create'),
            'view' => Pages\ViewServiceProvider::route('/{record}'),
            'edit' => Pages\EditServiceProvider::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
