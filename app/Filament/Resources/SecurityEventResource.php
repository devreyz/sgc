<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SecurityEventResource\Pages;
use App\Models\SecurityEvent;
use App\Services\TenantIdentityService;
use App\Services\TenantSecurityAuthorization;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class SecurityEventResource extends Resource
{
    protected static ?string $model = SecurityEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $modelLabel = 'Evento de seguranca';

    protected static ?string $pluralModelLabel = 'Eventos de seguranca';

    protected static ?int $navigationSort = 98;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        try {
            app(TenantSecurityAuthorization::class)->authorize(
                $user,
                (int) session('tenant_id'),
                'security-events.view',
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()?->isSuperAdmin()) {
            $tenantId = (int) session('tenant_id');
            $tenantId > 0 ? $query->where('tenant_id', $tenantId) : $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data e hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Evento')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('result')
                    ->label('Resultado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'denied', 'failed' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('actor_user_id')
                    ->label('Responsavel')
                    ->formatStateUsing(fn ($state, SecurityEvent $record): string => app(TenantIdentityService::class)
                        ->displayName($record->tenant_id, $record->actor_user_id))
                    ->default('Sistema'),
                Tables\Columns\TextColumn::make('correlation_id')
                    ->label('Correlacao')
                    ->copyable()
                    ->limit(12)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('result')
                    ->options(['success' => 'Sucesso', 'denied' => 'Negado', 'failed' => 'Falha']),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSecurityEvents::route('/')];
    }
}
