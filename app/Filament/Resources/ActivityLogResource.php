<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Str;
use App\Models\User;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $modelLabel = 'Log de Atividade';

    protected static ?string $pluralModelLabel = 'Logs de Atividade';

    protected static ?int $navigationSort = 99;

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Tanto Admin quanto SuperAdmin podem ver logs.
     * Admin vê apenas logs da própria organização (filtrado por tenant_id).
     * SuperAdmin vê todos os logs.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Shield may generate permission names with suffixes, e.g. `view_any_activity::log`.
        try {
            $perms = $user->getAllPermissions()->pluck('name')->toArray();
        } catch (\Throwable $e) {
            $perms = [];
        }

        foreach ($perms as $p) {
            if (Str::contains($p, 'view_any_activity')) {
                return true;
            }
        }

        return $user->can('view_any_activity');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        try {
            $perms = $user->getAllPermissions()->pluck('name')->toArray();
        } catch (\Throwable $e) {
            $perms = [];
        }

        foreach ($perms as $p) {
            if (Str::contains($p, 'view_any_activity')) {
                return true;
            }
        }

        return $user->can('view_any_activity');
    }

    /**
     * Filtra logs por tenant automaticamente para Admin.
     * SuperAdmin vê todos.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && ! $user->hasRole('super_admin')) {
            $tenantId = session('tenant_id');
            if ($tenantId) {
                // Filtra por tenant_id na coluna direta OU nas properties (retrocompatibilidade)
                $query->where(function (Builder $q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)
                        ->orWhere('properties->tenant_id', $tenantId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalhes do Log')
                    ->schema([
                        Forms\Components\TextInput::make('log_name')
                            ->label('Nome do Log'),
                        Forms\Components\TextInput::make('description')
                            ->label('Descrição'),
                        Forms\Components\TextInput::make('subject_type')
                            ->label('Tipo do Sujeito'),
                        Forms\Components\TextInput::make('subject_id')
                            ->label('ID do Sujeito'),
                        Forms\Components\TextInput::make('causer_type')
                            ->label('Tipo do Causador'),
                        Forms\Components\TextInput::make('causer_id')
                            ->label('ID do Causador'),
                        Forms\Components\KeyValue::make('properties')
                            ->label('Propriedades')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Usuário')
                    ->searchable(['users.name', 'users.email'])
                    ->sortable()
                    ->default('Sistema')
                    ->description(fn ($record) => $record->causer?->email ?? ''),

                Tables\Columns\TextColumn::make('description')
                    ->label('Ação')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'restored' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'created' => '✓ Criado',
                        'updated' => '✎ Atualizado',
                        'deleted' => '⨯ Excluído',
                        'restored' => '↺ Restaurado',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Entidade')
                    ->formatStateUsing(function ($state, $record) {
                        $basename = class_basename($state);
                        $translations = [
                            'User' => 'Usuário',
                            'Tenant' => 'Organização',
                            'TenantUser' => 'Membro',
                            'Associate' => 'Associado',
                            'ServiceProvider' => 'Prestador',
                            'ServiceOrder' => 'Ordem de Serviço',
                            'Expense' => 'Despesa',
                            'BankAccount' => 'Conta Bancária',
                            'Product' => 'Produto',
                            'CashMovement' => 'Movimento de Caixa',
                        ];
                        $label = $translations[$basename] ?? $basename;

                        return "{$label} #{$record->subject_id}";
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('log_name')
                    ->label('Tipo de Log')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Organização')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: auth()->user()?->hasRole('super_admin') ?? false)
                    ->visible(fn () => auth()->user()?->hasRole('super_admin') ?? false),

                Tables\Columns\TextColumn::make('properties')
                    ->label('Alterações')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return '-';
                        }

                        $attributes = $state['attributes'] ?? [];
                        $old = $state['old'] ?? [];

                        if (empty($attributes) && empty($old)) {
                            return 'Ver detalhes';
                        }

                        $changes = [];
                        foreach ($attributes as $key => $value) {
                            if (isset($old[$key]) && $old[$key] != $value) {
                                $changes[] = "{$key}: {$old[$key]} → {$value}";
                            }
                        }

                        return empty($changes) ? 'Ver detalhes' : implode(', ', array_slice($changes, 0, 2));
                    })
                    ->limit(50)
                    ->tooltip(function ($record) {
                        $props = $record->properties ?? [];
                        $attributes = $props['attributes'] ?? [];
                        $old = $props['old'] ?? [];

                        $lines = [];
                        foreach ($attributes as $key => $value) {
                            if (isset($old[$key]) && $old[$key] != $value) {
                                $lines[] = "• {$key}: {$old[$key]} → {$value}";
                            }
                        }

                        return empty($lines) ? 'Sem alterações registradas' : implode("\n", $lines);
                    })
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('description')
                    ->label('Ação')
                    ->options([
                        'created' => '✓ Criado',
                        'updated' => '✎ Atualizado',
                        'deleted' => '⨯ Excluído',
                        'restored' => '↺ Restaurado',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Entidade')
                    ->options(function () {
                        $types = Activity::query()
                            ->when(! auth()->user()?->hasRole('super_admin'), function ($q) {
                                $tenantId = session('tenant_id');
                                if ($tenantId) {
                                    $q->where(function ($sq) use ($tenantId) {
                                        $sq->where('tenant_id', $tenantId)
                                            ->orWhere('properties->tenant_id', $tenantId);
                                    });
                                }
                            })
                            ->distinct()
                            ->pluck('subject_type')
                            ->mapWithKeys(function ($type) {
                                $basename = class_basename($type);
                                $translations = [
                                    'User' => 'Usuário',
                                    'Tenant' => 'Organização',
                                    'TenantUser' => 'Membro',
                                    'Associate' => 'Associado',
                                    'ServiceProvider' => 'Prestador',
                                    'ServiceOrder' => 'Ordem de Serviço',
                                    'Expense' => 'Despesa',
                                    'BankAccount' => 'Conta Bancária',
                                    'Product' => 'Produto',
                                    'CashMovement' => 'Movimento de Caixa',
                                ];

                                return [$type => $translations[$basename] ?? $basename];
                            })
                            ->toArray();

                        return $types;
                    })
                    ->searchable()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('Usuário')
                    ->options(function () {
                        $query = User::query();

                        // Aplicar escopo por tenant para não-super-admins
                        if (! auth()->user()?->hasRole('super_admin')) {
                            $tenantId = session('tenant_id');
                            if ($tenantId) {
                                $query->whereHas('tenants', function ($q) use ($tenantId) {
                                    $q->where('tenant_id', $tenantId);
                                });
                            } else {
                                return [];
                            }
                        }

                        return $query->orderBy('name')->pluck('name', 'id')->toArray();
                    })
                    ->searchable()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('log_name')
                    ->label('Tipo de Log')
                    ->options(function () {
                        return Activity::query()
                            ->when(! auth()->user()?->hasRole('super_admin'), function ($q) {
                                $tenantId = session('tenant_id');
                                if ($tenantId) {
                                    $q->where(function ($sq) use ($tenantId) {
                                        $sq->where('tenant_id', $tenantId)
                                            ->orWhere('properties->tenant_id', $tenantId);
                                    });
                                }
                            })
                            ->distinct()
                            ->pluck('log_name', 'log_name')
                            ->toArray();
                    })
                    ->multiple(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('De')
                            ->default(now()->subDays(7)),
                        Forms\Components\DatePicker::make('until')
                            ->label('Até')
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['from'] && ! $data['until']) {
                            return null;
                        }

                        $from = $data['from'] ? date('d/m/Y', strtotime($data['from'])) : '';
                        $until = $data['until'] ? date('d/m/Y', strtotime($data['until'])) : '';

                        if ($from && $until) {
                            return "De {$from} até {$until}";
                        }
                        if ($from) {
                            return "A partir de {$from}";
                        }
                        if ($until) {
                            return "Até {$until}";
                        }

                        return null;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export')
                        ->label('Exportar Selecionados')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->action(function ($records) {
                            return response()->streamDownload(function () use ($records) {
                                echo self::generateCsvReport($records);
                            }, 'logs-atividade-'.date('Y-m-d-His').'.csv');
                        }),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_all')
                    ->label('Gerar Relatório Completo')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('format')
                            ->label('Formato')
                            ->options([
                                'csv' => 'CSV (Excel)',
                                'pdf' => 'PDF',
                            ])
                            ->default('csv')
                            ->required(),
                        Forms\Components\DatePicker::make('from')
                            ->label('De')
                            ->default(now()->subDays(30)),
                        Forms\Components\DatePicker::make('until')
                            ->label('Até')
                            ->default(now()),
                    ])
                    ->action(function (array $data) {
                        $query = Activity::query()
                            ->when(! auth()->user()?->hasRole('super_admin'), function ($q) {
                                $tenantId = session('tenant_id');
                                if ($tenantId) {
                                    $q->where(function ($sq) use ($tenantId) {
                                        $sq->where('tenant_id', $tenantId)
                                            ->orWhere('properties->tenant_id', $tenantId);
                                    });
                                }
                            })
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
                            ->orderBy('created_at', 'desc')
                            ->get();

                        if ($data['format'] === 'csv') {
                            return response()->streamDownload(function () use ($query) {
                                echo self::generateCsvReport($query);
                            }, 'relatorio-logs-'.date('Y-m-d-His').'.csv');
                        }

                        // TODO: Implementar export PDF
                        return null;
                    }),
            ]);
    }

    /**
     * Gera relatório CSV dos logs.
     */
    protected static function generateCsvReport($records): string
    {
        $csv = "Data/Hora;Usuário;Ação;Entidade;ID;Descrição\n";

        foreach ($records as $record) {
            $date = $record->created_at->format('d/m/Y H:i:s');
            $user = $record->causer?->name ?? 'Sistema';
            $action = match ($record->description) {
                'created' => 'Criado',
                'updated' => 'Atualizado',
                'deleted' => 'Excluído',
                'restored' => 'Restaurado',
                default => $record->description,
            };
            $entity = class_basename($record->subject_type);
            $id = $record->subject_id;

            // Extrair descrição das mudanças
            $props = $record->properties ?? [];
            $attributes = $props['attributes'] ?? [];
            $old = $props['old'] ?? [];

            $changes = [];
            foreach ($attributes as $key => $value) {
                if (isset($old[$key]) && $old[$key] != $value) {
                    $changes[] = "{$key}: {$old[$key]} -> {$value}";
                }
            }
            $description = empty($changes) ? '-' : implode('; ', $changes);

            $csv .= "\"{$date}\";\" {$user}\";\" {$action}\";\" {$entity}\";{$id};\"{$description}\"\n";
        }

        return "\xEF\xBB\xBF".$csv; // UTF-8 BOM for Excel
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
