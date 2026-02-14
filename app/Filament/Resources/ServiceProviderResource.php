<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceProviderResource\Pages;
use App\Filament\Resources\ServiceProviderResource\RelationManagers;
use App\Models\ServiceProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ServiceProviderResource extends Resource
{
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
                            ->relationship('user', 'name')
                            ->searchable()
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
                            ->unique(ignoreRecord: true),

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

                Forms\Components\Section::make('Valores e Status')
                    ->schema([
                        Forms\Components\TextInput::make('hourly_rate')
                            ->label('Valor por Hora')
                            ->numeric()
                            ->prefix('R$')
                            ->helperText('Valor cobrado por hora de trabalho'),

                        Forms\Components\TextInput::make('daily_rate')
                            ->label('Valor por Diária')
                            ->numeric()
                            ->prefix('R$')
                            ->helperText('Valor cobrado por dia de trabalho'),

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

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuário Vinculado')
                    ->searchable()
                    ->placeholder('Sem vínculo')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('cpf')
                    ->label('CPF')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Função')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match($state) {
                        'tratorista' => 'Tratorista',
                        'motorista' => 'Motorista',
                        'diarista' => 'Diarista',
                        'tecnico' => 'Técnico',
                        'consultor' => 'Consultor',
                        'outro' => 'Outro',
                        default => ucfirst($state),
                    })
                    ->color(fn ($state): string => match($state) {
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

                Tables\Columns\TextColumn::make('hourly_rate')
                    ->label('Valor/Hora')
                    ->money('BRL')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('daily_rate')
                    ->label('Valor/Diária')
                    ->money('BRL')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('pending_receivable')
                    ->label('Saldo a Receber')
                    ->state(fn (ServiceProvider $record): float => $record->pending_receivable)
                    ->money('BRL')
                    ->color(fn ($state): string => $state > 0 ? 'warning' : 'success')
                    ->weight('bold')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query; // Não ordena porque é calculado
                    }),

                Tables\Columns\TextColumn::make('ledger_balance')
                    ->label('Saldo Ledger')
                    ->state(fn (ServiceProvider $record): float => $record->current_balance)
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('pending_total')
                    ->label('Total Pendente (Legado)')
                    ->state(fn (ServiceProvider $record): float => $record->total_pending)
                    ->money('BRL')
                   ->color(fn ($state): string => $state > 0 ? 'danger' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('works_count')
                    ->label('Serviços')
                    ->counts('works')
                    ->sortable(),

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
                    ->query(fn (Builder $query) => $query->whereHas('works', fn ($q) => $q->where('payment_status', 'pendente'))),
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
            ->headerActions([
                Tables\Actions\Action::make('payment_report')
                    ->label('Relatório de Pagamentos')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('info')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data Início')
                            ->required()
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data Fim')
                            ->required()
                            ->default(now()->endOfMonth()),
                        Forms\Components\Select::make('payment_status')
                            ->label('Status Pagamento')
                            ->options([
                                'all' => 'Todos',
                                'pendente' => 'Pendentes',
                                'pago' => 'Pagos',
                            ])
                            ->default('all'),
                    ])
                    ->action(function (array $data) {
                        $query = \App\Models\ServiceProviderWork::with(['serviceProvider', 'serviceOrder', 'associate'])
                            ->whereBetween('work_date', [$data['start_date'], $data['end_date']]);

                        if ($data['payment_status'] !== 'all') {
                            $query->where('payment_status', $data['payment_status']);
                        }

                        $works = $query->orderBy('service_provider_id')->orderBy('work_date')->get();
                        $grouped = $works->groupBy('service_provider_id');

                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.service-providers-report', [
                            'grouped' => $grouped,
                            'start_date' => \Carbon\Carbon::parse($data['start_date'])->format('d/m/Y'),
                            'end_date' => \Carbon\Carbon::parse($data['end_date'])->format('d/m/Y'),
                            'payment_status' => $data['payment_status'],
                            'total' => $works->sum('total_value'),
                            'total_pending' => $works->where('payment_status', 'pendente')->sum('total_value'),
                            'total_paid' => $works->where('payment_status', 'pago')->sum('total_value'),
                            'generated_at' => now()->format('d/m/Y H:i'),
                        ]);

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'relatorio-prestadores-' . now()->format('Y-m-d') . '.pdf');
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\WorksRelationManager::class,
        ];
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
        return static::getModel()::whereHas('works', fn ($q) => $q->where('payment_status', 'pendente'))->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
